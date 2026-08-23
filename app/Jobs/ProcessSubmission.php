<?php

namespace App\Jobs;

use App\Models\Submission;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessSubmission implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 30;

    public function retryUntil(): \DateTime
    {
        return now()->addMinutes(10);
    }

    public function __construct(public Submission $submission) {}

    public function handle(): void
    {
        ExtractSubmissionText::dispatch($this->submission->getKey())
            ->onQueue(in_array($this->submission->input_type, ['image', 'video'], true) ? 'extract-media' : 'extract-text');
    }

    public function failed(\Throwable $exception): void
    {
        $submission = $this->submission->exists ? $this->submission : Submission::find($this->submission->getKey());
        if (! $submission) {
            return;
        }

        try {
            app(\App\Services\Pipeline\SubmissionStateMachine::class)->markFailedFinal(
                $submission,
                \App\Enums\ProcessingStage::Queued,
                $exception,
                $this->attempts(),
            );
        } catch (\Throwable) {
        }
    }
}
