<?php

namespace App\Jobs;

use App\Contracts\Classifier;
use App\Enums\EventOutcome;
use App\Enums\ProcessingStage;
use App\Jobs\Concerns\HandlesPipelineFailures;
use App\Models\DetectionResult;
use App\Models\Submission;
use App\Services\Pipeline\ProcessingEventRecorder;
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

    public function handle(Classifier $classifier, ProcessingEventRecorder $events): void
    {
        Cache::lock("submission:{$this->submissionId}:classify", 60)->block(5, function () use ($classifier, $events): void {
            $submission = Submission::findOrFail($this->submissionId);
            $existing = DetectionResult::where('submission_id', $submission->id)->first();

            if ($existing !== null) {
                GenerateSubmissionExplanation::dispatch($submission->id)->onQueue('explanation');

                return;
            }

            $started = hrtime(true);
            $submission->update(['processing_stage' => ProcessingStage::Classifying->value]);
            $events->record($submission, ProcessingStage::Classifying, EventOutcome::Started, 'bert', $this->attempts());

            try {
                $classification = $classifier->classify((string) $submission->extracted_text);
                DetectionResult::updateOrCreate(['submission_id' => $submission->id], [
                    'label' => $classification->label->value,
                    'confidence_score' => $classification->confidenceScore,
                    'model_version' => $classification->modelVersion,
                    'raw_scores' => $classification->rawScores,
                    'inference_ms' => $classification->inferenceMs,
                    'classifier_cached' => $classification->cached,
                    'explanation_status' => 'pending',
                ]);
                $events->record($submission, ProcessingStage::Classifying, EventOutcome::Succeeded, 'bert', $this->attempts(), (int) ((hrtime(true) - $started) / 1_000_000), metadata: ['cached' => $classification->cached, 'model_version' => $classification->modelVersion]);
                GenerateSubmissionExplanation::dispatch($submission->id)->onQueue('explanation');
            } catch (Throwable $exception) {
                $this->handlePipelineFailure($submission, ProcessingStage::Classifying, $exception, $events);
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

        $service = $exception instanceof \App\Exceptions\AiServiceException ? $exception->service : 'pipeline';
        $code = $exception instanceof \App\Exceptions\AiServiceException
            ? ($exception->statusCode ? "HTTP_{$exception->statusCode}" : 'SERVICE_UNAVAILABLE')
            : class_basename($exception);

        $submission->update([
            'status' => 'failed',
            'processing_stage' => ProcessingStage::Classifying->value,
            'processing_completed_at' => now(),
            'failure_reason' => $exception->getMessage(),
            'last_error_service' => $service,
            'last_error_code' => $code,
            'attempt_count' => $this->attempts(),
        ]);

        try {
            app(\App\Services\Pipeline\ProcessingEventRecorder::class)->record(
                $submission,
                ProcessingStage::Classifying,
                \App\Enums\EventOutcome::Failed,
                $service,
                $this->attempts(),
                errorCode: $code,
            );
        } catch (Throwable) {
        }
    }
}
