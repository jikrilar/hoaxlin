<?php

namespace App\Jobs\Concerns;

use App\Enums\ProcessingStage;
use App\Models\Submission;
use App\Services\Pipeline\PipelineFailureReporter;
use App\Services\Pipeline\PipelineRetryPolicy;
use App\Services\Pipeline\SubmissionStateMachine;
use Throwable;

trait HandlesPipelineFailures
{
    private function handlePipelineFailure(
        Submission $submission,
        ProcessingStage $stage,
        Throwable $exception,
        SubmissionStateMachine $state,
        int $maxAttempts,
        array $backoff,
    ): void {
        $attempt = max(1, $this->attempts());
        $failure = $state->markFailed(
            $submission,
            $stage,
            $exception,
            $attempt,
            $attempt >= $maxAttempts,
        );

        if (! app(PipelineRetryPolicy::class)->shouldRetry($failure, $attempt, $maxAttempts)) {
            $this->fail(app(PipelineFailureReporter::class)->sanitizedException($failure));

            return;
        }

        $this->release(app(PipelineRetryPolicy::class)->delay($failure, $attempt, $backoff));
    }
}
