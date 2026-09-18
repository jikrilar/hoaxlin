<?php

namespace App\Jobs;

use App\Enums\ProcessingStage;
use App\Jobs\Concerns\HandlesPipelineFailures;
use App\Jobs\Concerns\UniqueSubmissionStage;
use App\Models\Submission;
use App\Services\Extraction\TextExtractorResolver;
use App\Services\Pipeline\SubmissionStateMachine;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Throwable;

class ExtractSubmissionText implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, HandlesPipelineFailures, InteractsWithQueue, Queueable, SerializesModels, UniqueSubmissionStage;

    public int $tries = 3;

    public int $timeout = 75;

    public function __construct(public readonly int $submissionId) {}

    public function backoff(): array
    {
        return [10, 60, 180];
    }

    public function handle(TextExtractorResolver $resolver, SubmissionStateMachine $state): void
    {
        Cache::lock("submission:{$this->submissionId}:extract", 90)->block(5, function () use ($resolver, $state): void {
            $submission = Submission::findOrFail($this->submissionId);

            if ($submission->isTerminal()) {
                return;
            }

            if (filled($submission->content_hash)) {
                $this->continueToTranslation($submission->id);

                return;
            }

            $started = hrtime(true);

            try {
                $state->markProcessing($submission, ProcessingStage::Extracting, $this->attempts());
                $extracted = $resolver->resolve($submission)->extract($submission);
                $state->markExtracted($submission, $extracted->contentHash(), $extracted->provider, $extracted->cached, $this->attempts(), (int) ((hrtime(true) - $started) / 1_000_000), $extracted->normalized());
                $this->continueToTranslation($submission->id);
            } catch (Throwable $exception) {
                $this->handlePipelineFailure($submission, ProcessingStage::Extracting, $exception, $state, $this->tries, $this->backoff());
            }
        });
    }

    private function continueToTranslation(int $submissionId): void
    {
        TranslateSubmissionText::dispatch($submissionId)->onQueue('inference');
    }

    /**
     * Called by the queue worker after the job has exceeded max retries.
     * Ensures the submission does not remain stuck in "processing" forever.
     */
    public function failed(Throwable $exception): void
    {
        $submission = Submission::find($this->submissionId);
        if (! $submission) {
            return;
        }

        try {
            app(SubmissionStateMachine::class)->markFailedFinal(
                $submission,
                ProcessingStage::Extracting,
                $exception,
                $this->attempts(),
            );
        } catch (Throwable) {
        }
    }
}
