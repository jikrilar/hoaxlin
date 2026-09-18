<?php

namespace App\Services\Pipeline;

use App\DataObjects\Classification;
use App\DataObjects\Explanation;
use App\DataObjects\PipelineFailure;
use App\Enums\EventOutcome;
use App\Enums\ProcessingStage;
use App\Models\DetectionResult;
use App\Models\Submission;
use Throwable;

class SubmissionStateMachine
{
    public function __construct(
        private readonly ProcessingEventRecorder $events,
        private readonly PipelineFailureReporter $failures,
    ) {}

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

    public function markClassified(Submission $submission, Classification $classification, int $attempt, int $durationMs): void
    {
        $submission->update(['processing_stage' => ProcessingStage::Classifying->value]);

        DetectionResult::updateOrCreate(['submission_id' => $submission->id], [
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

    public function markExplained(Submission $submission, Explanation $explanation, int $attempt): void
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

    public function markFailed(Submission $submission, ProcessingStage $stage, Throwable $exception, int $attempt, bool $isFinal = false): PipelineFailure
    {
        $failure = $this->failures->report($submission, $stage, $exception, $attempt);
        $shouldFail = ! $failure->retryable || $isFinal;

        try {
            $this->events->record(
                $submission,
                $stage,
                $shouldFail ? EventOutcome::Failed : EventOutcome::Retried,
                $failure->service,
                $attempt,
                errorCode: $failure->errorCode,
                metadata: ['error_reference' => $failure->reference],
            );

            $submission->update([
                'attempt_count' => $attempt,
                'last_error_service' => $failure->service,
                'last_error_code' => $failure->errorCode,
                'failure_reason' => $shouldFail ? $failure->publicMessage : null,
                'status' => $shouldFail ? 'failed' : 'processing',
                'processing_stage' => $stage->value,
                'processing_completed_at' => $shouldFail ? now() : null,
            ]);
        } catch (Throwable $persistenceException) {
            $persistenceFailure = $this->failures->report($submission, $stage, $persistenceException, $attempt);

            return $persistenceFailure;
        }

        return $failure;
    }

    public function markFailedFinal(Submission $submission, ProcessingStage $stage, Throwable $exception, int $attempt): void
    {
        $described = $this->failures->describe($exception);
        $submission->refresh();

        if ($submission->isCompleted()) {
            return;
        }

        if ($submission->isFailed()
            && $submission->last_error_service === $described->service
            && $submission->last_error_code === $described->errorCode
            && (int) $submission->attempt_count >= $attempt) {
            return;
        }

        $failure = $this->failures->report($submission, $stage, $exception, $attempt);

        try {
            $submission->update([
                'status' => 'failed',
                'processing_stage' => $stage->value,
                'processing_completed_at' => now(),
                'failure_reason' => $failure->publicMessage,
                'last_error_service' => $failure->service,
                'last_error_code' => $failure->errorCode,
                'attempt_count' => $attempt,
            ]);

            $this->events->record(
                $submission,
                $stage,
                EventOutcome::Failed,
                $failure->service,
                $attempt,
                errorCode: $failure->errorCode,
                metadata: ['error_reference' => $failure->reference],
            );
        } catch (Throwable $persistenceException) {
            $this->failures->report($submission, $stage, $persistenceException, $attempt);
        }
    }
}
