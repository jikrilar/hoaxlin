<?php

namespace App\Jobs;

use App\Exceptions\AiServiceException;
use App\Models\ModelVersion;
use App\Services\Bert\BertTrainingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class StartBertTrainingJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public int $timeout = 60;

    public function __construct(public readonly int $modelVersionId) {}

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [15, 45, 90, 180];
    }

    public function uniqueId(): string
    {
        return (string) $this->modelVersionId;
    }

    public function uniqueFor(): int
    {
        return 600;
    }

    public function handle(BertTrainingService $trainingService): void
    {
        Cache::lock("model-version:{$this->modelVersionId}:start-training", 60)->block(5, function () use ($trainingService): void {
            /** @var ModelVersion|null $version */
            $version = DB::transaction(function (): ?ModelVersion {
                $record = ModelVersion::query()->lockForUpdate()->find($this->modelVersionId);

                if ($record === null || ! $record->isPending()) {
                    return null;
                }

                // Transition pending -> training before sending HTTP POST /train so an
                // ultra-fast callback can never be overwritten by a post-response save.
                $record->forceFill([
                    'status' => ModelVersion::STATUS_TRAINING,
                    'error_message' => null,
                ])->save();

                return $record;
            });

            if ($version === null) {
                return;
            }

            try {
                $trainingService->startTraining($version);

                Log::info('Dispatched incremental BERT training job to bert-service.', [
                    'version_id' => $version->getKey(),
                    'version_name' => $version->version_name,
                ]);
            } catch (AiServiceException $exception) {
                $attempt = max(1, $this->attempts());

                if ($exception->retryable && $attempt < $this->tries) {
                    $this->revertTrainingToPending();

                    $backoff = $this->backoff();
                    $delay = $exception->retryAfterSeconds ?? ($backoff[$attempt - 1] ?? 180);

                    Log::info('Releasing BERT training job for retry.', [
                        'version_id' => $this->modelVersionId,
                        'attempt' => $attempt,
                        'delay_seconds' => $delay,
                        'status_code' => $exception->statusCode,
                    ]);

                    $this->release($delay);

                    return;
                }

                $this->markFailedIfNonTerminal($exception->getMessage());

                Log::warning('BERT training start failed permanently.', [
                    'version_id' => $this->modelVersionId,
                    'attempt' => $attempt,
                    'status_code' => $exception->statusCode,
                ]);

                $this->fail($exception);
            } catch (Throwable $exception) {
                $this->markFailedIfNonTerminal('Gagal memulai pelatihan model pada layanan BERT.');

                Log::warning('BERT training start encountered unexpected failure.', [
                    'version_id' => $this->modelVersionId,
                    'exception_class' => $exception::class,
                ]);

                $this->fail($exception);
            }
        });
    }

    public function failed(?Throwable $exception): void
    {
        $message = $exception instanceof AiServiceException
            ? $exception->getMessage()
            : 'Gagal memulai pelatihan model pada antrean.';

        $this->markFailedIfNonTerminal($message);
    }

    private function revertTrainingToPending(): void
    {
        DB::transaction(function (): void {
            $record = ModelVersion::query()->lockForUpdate()->find($this->modelVersionId);

            if ($record !== null && $record->isTraining()) {
                $record->forceFill([
                    'status' => ModelVersion::STATUS_PENDING,
                ])->save();
            }
        });
    }

    private function markFailedIfNonTerminal(string $message): void
    {
        $safeMessage = trim($message) !== ''
            ? mb_substr(trim($message), 0, 2000)
            : 'Gagal memulai pelatihan model pada layanan BERT.';

        DB::transaction(function () use ($safeMessage): void {
            $record = ModelVersion::query()->lockForUpdate()->find($this->modelVersionId);

            if ($record === null || $record->isCompleted() || $record->isFailed()) {
                return;
            }

            $record->forceFill([
                'status' => ModelVersion::STATUS_FAILED,
                'error_message' => $safeMessage,
            ])->save();
        });
    }
}
