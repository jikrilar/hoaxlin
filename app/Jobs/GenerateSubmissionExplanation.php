<?php

namespace App\Jobs;

use App\Contracts\Explainer;
use App\DataObjects\Classification;
use App\Enums\DetectionLabel;
use App\Enums\EventOutcome;
use App\Enums\ExplanationStatus;
use App\Enums\ProcessingStage;
use App\Models\Submission;
use App\Services\Pipeline\ProcessingEventRecorder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

class GenerateSubmissionExplanation implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 60;

    public function __construct(public readonly int $submissionId) {}

    public function backoff(): array
    {
        return config('services.openai.backoff', [10, 60, 180]);
    }

    public function handle(Explainer $explainer, ProcessingEventRecorder $events): void
    {
        Cache::lock("submission:{$this->submissionId}:explain", 75)->block(5, function () use ($explainer, $events): void {
            $submission = Submission::with('detectionResult')->findOrFail($this->submissionId);
            $result = $submission->detectionResult;

            if ($result === null) {
                return;
            }

            if ($result->explanation_status === ExplanationStatus::Ready->value) {
                $this->complete($submission, $events);

                return;
            }

            $submission->update(['processing_stage' => ProcessingStage::Explaining->value]);
            $events->record($submission, ProcessingStage::Explaining, EventOutcome::Started, 'openai', $this->attempts());
            $classification = new Classification(
                DetectionLabel::from($result->label),
                (float) $result->confidence_score,
                $result->model_version,
                $result->raw_scores ?? [],
                $result->inference_ms,
                $result->classifier_cached,
            );
            $explanation = $explainer->explain($classification, mb_substr((string) $submission->extracted_text, 0, 1500));
            $result->update([
                'explanation' => $explanation->narrative,
                'explanation_status' => $explanation->status->value,
                'explanation_model' => $explanation->model,
                'explanation_cached' => $explanation->cached,
                'prompt_tokens' => $explanation->promptTokens,
                'completion_tokens' => $explanation->completionTokens,
                'estimated_cost_usd' => $explanation->estimatedCostUsd,
            ]);
            $events->record($submission, ProcessingStage::Explaining, $explanation->isReady() ? EventOutcome::Succeeded : EventOutcome::Degraded, 'openai', $this->attempts(), metadata: ['cached' => $explanation->cached]);
            $this->complete($submission, $events);
        });
    }

    private function complete(Submission $submission, ProcessingEventRecorder $events): void
    {
        $submission->update([
            'status' => 'completed',
            'processing_stage' => ProcessingStage::Done->value,
            'processing_completed_at' => now(),
            'failure_reason' => null,
            'last_error_service' => null,
            'last_error_code' => null,
        ]);
        $events->record($submission, ProcessingStage::Done, EventOutcome::Succeeded, attempt: $this->attempts());
    }

    public function failed(Throwable $exception): void
    {
        $submission = Submission::find($this->submissionId);
        if (! $submission) {
            return;
        }

        // Explanation failures are degraded, not hard failures — but if the job
        // itself exceeds retries (e.g., queue timeout), mark the submission so
        // it does not stay in "processing". The classification result is kept.
        $service = $exception instanceof \App\Exceptions\AiServiceException ? $exception->service : 'openai';
        $code = $exception instanceof \App\Exceptions\AiServiceException
            ? ($exception->statusCode ? "HTTP_{$exception->statusCode}" : 'SERVICE_UNAVAILABLE')
            : class_basename($exception);

        $submission->update([
            'status' => 'failed',
            'processing_stage' => ProcessingStage::Explaining->value,
            'processing_completed_at' => now(),
            'failure_reason' => $exception->getMessage(),
            'last_error_service' => $service,
            'last_error_code' => $code,
            'attempt_count' => $this->attempts(),
        ]);

        try {
            app(\App\Services\Pipeline\ProcessingEventRecorder::class)->record(
                $submission,
                ProcessingStage::Explaining,
                \App\Enums\EventOutcome::Failed,
                $service,
                $this->attempts(),
                errorCode: $code,
            );
        } catch (Throwable) {
        }
    }
}
