<?php

namespace App\Jobs;

use App\Enums\ProcessingStage;
use App\Jobs\Concerns\HandlesPipelineFailures;
use App\Jobs\Concerns\UniqueSubmissionStage;
use App\Models\Submission;
use App\Services\Pipeline\SubmissionStateMachine;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessSubmission implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, HandlesPipelineFailures, InteractsWithQueue, Queueable, SerializesModels, UniqueSubmissionStage;

    public int $tries = 3;

    public int $timeout = 30;

    public function retryUntil(): \DateTime
    {
        return now()->addMinutes(10);
    }

    public function __construct(public Submission $submission) {}

    public function handle(SubmissionStateMachine $state): void
    {
        $this->submission->refresh();
        if ($this->submission->isTerminal()) {
            return;
        }

        try {
            ExtractSubmissionText::dispatch($this->submission->getKey())
                ->onQueue(in_array($this->submission->input_type, ['image', 'video'], true) ? 'extract-media' : 'extract-text');
        } catch (\Throwable $exception) {
            $this->handlePipelineFailure(
                $this->submission,
                ProcessingStage::Queued,
                $exception,
                $state,
                $this->tries,
                config('pipeline.retry.backoff', [5, 15, 60]),
            );
        }
    }

    public function failed(\Throwable $exception): void
    {
        $submission = $this->submission->exists ? $this->submission : Submission::find($this->submission->getKey());
        if (! $submission) {
            return;
        }

        try {
            app(SubmissionStateMachine::class)->markFailedFinal(
                $submission,
                ProcessingStage::Queued,
                $exception,
                $this->attempts(),
            );
        } catch (\Throwable) {
        }
    }
}
