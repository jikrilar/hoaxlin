<?php

namespace App\Jobs;

use App\Contracts\Classifier;
use App\Enums\ProcessingStage;
use App\Jobs\Concerns\HandlesPipelineFailures;
use App\Models\DetectionResult;
use App\Models\Submission;
use App\Services\Pipeline\ProcessingEventRecorder;
use App\Services\Pipeline\SubmissionStateMachine;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Throwable;

class ClassifySubmission implements ShouldQueue
{
    use Dispatchable, HandlesPipelineFailures, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public int $timeout = 45;

    public function __construct(public readonly int $submissionId) {}

    public function backoff(): array
    {
        return config('services.bert.backoff', [5, 15, 45, 120, 300]);
    }

    public function handle(Classifier $classifier, ProcessingEventRecorder $events, SubmissionStateMachine $state): void
    {
        Cache::lock("submission:{$this->submissionId}:classify", 60)->block(5, function () use ($classifier, $state): void {
            $submission = Submission::findOrFail($this->submissionId);
            $existing = DetectionResult::where('submission_id', $submission->id)->first();

            if ($existing !== null) {
                GenerateSubmissionExplanation::dispatch($submission->id)->onQueue('explanation');

                return;
            }

            $started = hrtime(true);
            $state->markProcessing($submission, ProcessingStage::Classifying, $this->attempts());

            try {
                $classification = $classifier->classify((string) $submission->analysis_text);
                $state->markClassified($submission, $classification, $this->attempts(), (int) ((hrtime(true) - $started) / 1_000_000));
                GenerateSubmissionExplanation::dispatch($submission->id)->onQueue('explanation');
            } catch (Throwable $exception) {
                $state->markFailed($submission, ProcessingStage::Classifying, $exception, $this->attempts());
                throw $exception;
            }
        });
    }

    /**
     * Final failure handler — marks the submission as failed so it never stays
     * in "processing" after the last retry (C7).
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
                ProcessingStage::Classifying,
                $exception,
                $this->attempts(),
            );
        } catch (Throwable) {
        }
    }
}
