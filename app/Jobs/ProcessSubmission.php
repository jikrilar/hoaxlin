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

        $submission->update([
            'status' => 'failed',
            'processing_stage' => \App\Enums\ProcessingStage::Queued->value,
            'processing_completed_at' => now(),
            'failure_reason' => $exception->getMessage(),
            'last_error_service' => 'pipeline',
            'last_error_code' => class_basename($exception),
            'attempt_count' => $this->attempts(),
        ]);

        try {
            app(\App\Services\Pipeline\ProcessingEventRecorder::class)->record(
                $submission,
                \App\Enums\ProcessingStage::Queued,
                \App\Enums\EventOutcome::Failed,
                'pipeline',
                $this->attempts(),
                errorCode: class_basename($exception),
            );
        } catch (\Throwable) {
        }
    }
}
