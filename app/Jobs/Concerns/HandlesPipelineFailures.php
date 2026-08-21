<?php

namespace App\Jobs\Concerns;

use App\Enums\EventOutcome;
use App\Enums\ProcessingStage;
use App\Exceptions\AiServiceException;
use App\Models\Submission;
use App\Services\Pipeline\ProcessingEventRecorder;
use Throwable;

trait HandlesPipelineFailures
{
    private function handlePipelineFailure(
        Submission $submission,
        ProcessingStage $stage,
        Throwable $exception,
        ProcessingEventRecorder $events,
    ): void {
        $retryable = $exception instanceof AiServiceException && $exception->retryable;
        $service = $exception instanceof AiServiceException ? $exception->service : 'pipeline';
        $code = $exception instanceof AiServiceException
            ? ($exception->statusCode ? "HTTP_{$exception->statusCode}" : 'SERVICE_UNAVAILABLE')
            : class_basename($exception);

        $events->record(
            $submission,
            $stage,
            $retryable ? EventOutcome::Retried : EventOutcome::Failed,
            $service,
            $this->attempts(),
            errorCode: $code,
        );

        $submission->update([
            'attempt_count' => $this->attempts(),
            'last_error_service' => $service,
            'last_error_code' => $code,
            'failure_reason' => $exception->getMessage(),
            'status' => $retryable ? 'processing' : 'failed',
            'processing_completed_at' => $retryable ? null : now(),
        ]);

        if (! $retryable) {
            $this->fail($exception);
        }
    }
}
