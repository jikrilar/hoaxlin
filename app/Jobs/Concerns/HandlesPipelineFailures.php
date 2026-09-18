<?php

namespace App\Jobs\Concerns;

use App\Enums\ProcessingStage;
use App\Models\Submission;
use App\Services\Pipeline\ProcessingEventRecorder;
use App\Services\Pipeline\SubmissionStateMachine;
use Throwable;

trait HandlesPipelineFailures
{
    private function handlePipelineFailure(
        Submission $submission,
        ProcessingStage $stage,
        Throwable $exception,
        ProcessingEventRecorder $events,
    ): void {
        app(SubmissionStateMachine::class)->markFailed(
            $submission,
            $stage,
            $exception,
            $this->attempts(),
        );
    }
}
