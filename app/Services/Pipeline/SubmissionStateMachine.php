<?php

namespace App\Services\Pipeline;

use App\Enums\EventOutcome;
use App\Enums\ProcessingStage;
use App\Exceptions\AiServiceException;
use App\Models\Submission;
use Throwable;

class SubmissionStateMachine
{
    public function __construct(private readonly ProcessingEventRecorder $events) {}

    public function markProcessing(Submission $submission, ProcessingStage $stage, int $attempt = 1): void
    {
        $submission->update([
            'status' => 'processing',
            'processing_stage' => $stage->value,
            'processing_started_at' => $submission->processing_started_at ?? now(),
            'failure_reason' => null,
            'last_error_service' => null,
            'last_error_code' => null,
        ]);

        $this->events->record($submission, $stage, EventOutcome::Started, attempt: $attempt);
    }

    public function markExtracted(Submission $submission, string $contentHash, string $provider, bool $cached, int $attempt, int $durationMs, string $normalizedText): void
    {
        $submission->update([
            'extracted_text' => $normalizedText,
            'content_hash' => $contentHash,
            'failure_reason' => null,
            'last_error_service' => null,
            'last_error_code' => null,
        ]);

        $this->events->record(
            $submission,
            ProcessingStage::Extracting,
            EventOutcome::Succeeded,
            $provider,
            $attempt,
            $durationMs,
            metadata: ['cached' => $cached],
        );
    }

    public function markClassified(Submission $submission, \App\DataObjects\Classification $classification, int $attempt, int $durationMs): void
    {
        $submission->update(['processing_stage' => ProcessingStage::Classifying->value]);

        \App\Models\DetectionResult::updateOrCreate(['submission_id' => $submission->id], [
            'label' => $classification->label->value,
            'confidence_score' => $classification->confidenceScore,
            'model_version' => $classification->modelVersion,
            'raw_scores' => $classification->rawScores,
            'inference_ms' => $classification->inferenceMs,
            'classifier_cached' => $classification->cached,
            'explanation_status' => 'pending',
        ]);

        $this->events->record(
            $submission,
            ProcessingStage::Classifying,
            EventOutcome::Succeeded,
            'bert',
            $attempt,
            $durationMs,
            metadata: ['cached' => $classification->cached, 'model_version' => $classification->modelVersion],
        );
    }

    public function markExplained(Submission $submission, \App\DataObjects\Explanation $explanation, int $attempt): void
    {
        $result = $submission->detectionResult;
        if ($result) {
            $result->update([
                'explanation' => $explanation->narrative,
                'explanation_status' => $explanation->status->value,
                'explanation_model' => $explanation->model,
                'explanation_cached' => $explanation->cached,
                'prompt_tokens' => $explanation->promptTokens,
                'completion_tokens' => $explanation->completionTokens,
                'estimated_cost_usd' => $explanation->estimatedCostUsd,
            ]);
        }

        $this->events->record(
            $submission,
            ProcessingStage::Explaining,
            $explanation->isReady() ? EventOutcome::Succeeded : EventOutcome::Degraded,
            'openai',
            $attempt,
            metadata: ['cached' => $explanation->cached],
        );
    }

    public function markCompleted(Submission $submission, int $attempt = 1): void
    {
        $submission->update([
            'status' => 'completed',
            'processing_stage' => ProcessingStage::Done->value,
            'processing_completed_at' => now(),
            'failure_reason' => null,
            'last_error_service' => null,
            'last_error_code' => null,
        ]);

        $this->events->record($submission, ProcessingStage::Done, EventOutcome::Succeeded, attempt: $attempt);
    }

    public function markFailed(Submission $submission, ProcessingStage $stage, Throwable $exception, int $attempt, bool $isFinal = false): void
    {
        $retryable = $exception instanceof AiServiceException && $exception->retryable;
        $service = $exception instanceof AiServiceException ? $exception->service : 'pipeline';
        $code = $exception instanceof AiServiceException
            ? ($exception->statusCode ? "HTTP_{$exception->statusCode}" : 'SERVICE_UNAVAILABLE')
            : class_basename($exception);

        $this->events->record(
            $submission,
            $stage,
            $isFinal ? EventOutcome::Failed : ($retryable ? EventOutcome::Retried : EventOutcome::Failed),
            $service,
            $attempt,
            errorCode: $code,
        );

        // Only mark the submission as failed if it's a permanent error or this is the final attempt
        $shouldFail = ! $retryable || $isFinal;

        $isRetrying = $retryable && ! $shouldFail;

        $submission->update([
            'attempt_count' => $attempt,
            'last_error_service' => $service,
            'last_error_code' => $code,
            // Don't expose "mode pemulihan" to the user while still retrying — keep UI on "Sedang Menganalisis"
            'failure_reason' => $shouldFail ? $exception->getMessage() : null,
            'status' => $shouldFail ? 'failed' : 'processing',
            'processing_stage' => $stage->value,
            'processing_completed_at' => $shouldFail ? now() : null,
        ]);

        // Always throw to let the queue handle retry/backoff, but the UI won't show the raw message while retrying
        throw $exception;
    }

    public function markFailedFinal(Submission $submission, ProcessingStage $stage, Throwable $exception, int $attempt): void
    {
        $service = $exception instanceof AiServiceException ? $exception->service : 'pipeline';
        $code = $exception instanceof AiServiceException
            ? ($exception->statusCode ? "HTTP_{$exception->statusCode}" : 'SERVICE_UNAVAILABLE')
            : class_basename($exception);

        $submission->update([
            'status' => 'failed',
            'processing_stage' => $stage->value,
            'processing_completed_at' => now(),
            'failure_reason' => $exception->getMessage(),
            'last_error_service' => $service,
            'last_error_code' => $code,
            'attempt_count' => $attempt,
        ]);

        try {
            $this->events->record($submission, $stage, EventOutcome::Failed, $service, $attempt, errorCode: $code);
        } catch (Throwable) {
        }
    }
}
