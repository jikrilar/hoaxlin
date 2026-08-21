<?php

namespace App\Jobs;

use App\Enums\EventOutcome;
use App\Enums\ProcessingStage;
use App\Jobs\Concerns\HandlesPipelineFailures;
use App\Models\Submission;
use App\Services\Extraction\TextExtractorResolver;
use App\Services\Pipeline\ProcessingEventRecorder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Throwable;

class ExtractSubmissionText implements ShouldQueue
{
    use Dispatchable, HandlesPipelineFailures, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 75;

    public function __construct(public readonly int $submissionId) {}

    public function backoff(): array
    {
        return [10, 60, 180];
    }

    public function handle(TextExtractorResolver $resolver, ProcessingEventRecorder $events): void
    {
        Cache::lock("submission:{$this->submissionId}:extract", 90)->block(5, function () use ($resolver, $events): void {
            $submission = Submission::findOrFail($this->submissionId);

            if (filled($submission->content_hash)) {
                $this->continueToClassification($submission->id);

                return;
            }

            $started = hrtime(true);
            $submission->update([
                'status' => 'processing',
                'processing_stage' => ProcessingStage::Extracting->value,
                'processing_started_at' => $submission->processing_started_at ?? now(),
            ]);
            $events->record($submission, ProcessingStage::Extracting, EventOutcome::Started, attempt: $this->attempts());

            try {
                $extracted = $resolver->resolve($submission)->extract($submission);
                $submission->update([
                    'extracted_text' => $extracted->normalized(),
                    'content_hash' => $extracted->contentHash(),
                    'failure_reason' => null,
                    'last_error_service' => null,
                    'last_error_code' => null,
                ]);
                $events->record($submission, ProcessingStage::Extracting, EventOutcome::Succeeded, $extracted->provider, $this->attempts(), (int) ((hrtime(true) - $started) / 1_000_000), metadata: ['cached' => $extracted->cached]);
                $this->continueToClassification($submission->id);
            } catch (Throwable $exception) {
                $this->handlePipelineFailure($submission, ProcessingStage::Extracting, $exception, $events);
                throw $exception;
            }
        });
    }

    private function continueToClassification(int $submissionId): void
    {
        ClassifySubmission::dispatch($submissionId)->onQueue('inference');
    }

    /**
     * Called by the queue worker after the job has exceeded max retries.
     * Ensures the submission does not remain stuck in "processing" forever.
     */
    public function failed(\Throwable $exception): void
    {
        $submission = Submission::find($this->submissionId);
        if (! $submission) {
            return;
        }

        $service = $exception instanceof \App\Exceptions\AiServiceException ? $exception->service : 'pipeline';
        $code = $exception instanceof \App\Exceptions\AiServiceException
            ? ($exception->statusCode ? "HTTP_{$exception->statusCode}" : 'SERVICE_UNAVAILABLE')
            : class_basename($exception);

        $submission->update([
            'status' => 'failed',
            'processing_stage' => ProcessingStage::Extracting->value,
            'processing_completed_at' => now(),
            'failure_reason' => $exception->getMessage(),
            'last_error_service' => $service,
            'last_error_code' => $code,
            'attempt_count' => $this->attempts(),
        ]);

        try {
            app(\App\Services\Pipeline\ProcessingEventRecorder::class)->record(
                $submission,
                ProcessingStage::Extracting,
                \App\Enums\EventOutcome::Failed,
                $service,
                $this->attempts(),
                errorCode: $code,
            );
        } catch (\Throwable) {
            // Recording must not mask the original failure
        }
    }
}
