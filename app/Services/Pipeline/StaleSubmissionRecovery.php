<?php

namespace App\Services\Pipeline;

use App\Enums\EventOutcome;
use App\Enums\ProcessingStage;
use App\Jobs\ClassifySubmission;
use App\Jobs\ExtractSubmissionText;
use App\Jobs\ProcessSubmission;
use App\Jobs\RetrieveSubmissionEvidence;
use App\Jobs\TranslateSubmissionText;
use App\Models\Submission;
use Illuminate\Database\Eloquent\Builder;

class StaleSubmissionRecovery
{
    public function __construct(private readonly ProcessingEventRecorder $events) {}

    public function staleAfterMinutes(): int
    {
        return max(1, (int) config('pipeline.stale_after_minutes', 45));
    }

    public function staleQuery(?int $submissionId = null): Builder
    {
        return Submission::query()
            ->whereIn('status', ['pending', 'processing'])
            ->where('updated_at', '<=', now()->subMinutes($this->staleAfterMinutes()))
            ->when($submissionId !== null, fn (Builder $query) => $query->whereKey($submissionId));
    }

    /**
     * Atomically claims and recovers a stale submission.
     *
     * @return string|null Recovery action, or null when another watchdog already claimed it.
     */
    public function recover(Submission $candidate): ?string
    {
        $claimed = Submission::query()
            ->whereKey($candidate->getKey())
            ->whereIn('status', ['pending', 'processing'])
            ->where('updated_at', '<=', now()->subMinutes($this->staleAfterMinutes()))
            ->update(['updated_at' => now()]);

        if ($claimed !== 1) {
            return null;
        }

        $submission = Submission::with('detectionResult')->findOrFail($candidate->getKey());
        $stage = ProcessingStage::tryFrom((string) $submission->processing_stage);

        if ($submission->detectionResult !== null) {
            return $this->dispatchRecovery(
                $submission,
                ProcessingStage::Retrieving,
                RetrieveSubmissionEvidence::class,
                'retrieval',
            );
        }

        return match ($stage) {
            ProcessingStage::Queued => $this->dispatchRecovery($submission, $stage, ProcessSubmission::class, 'default'),
            ProcessingStage::Extracting => filled($submission->content_hash)
                ? $this->dispatchRecovery($submission, $stage, TranslateSubmissionText::class, 'inference')
                : $this->dispatchRecovery(
                    $submission,
                    $stage,
                    ExtractSubmissionText::class,
                    in_array($submission->input_type, ['image', 'video'], true) ? 'extract-media' : 'extract-text',
                ),
            ProcessingStage::Translating => filled($submission->source_language)
                ? $this->dispatchRecovery($submission, $stage, ClassifySubmission::class, 'inference')
                : $this->dispatchRecovery($submission, $stage, TranslateSubmissionText::class, 'inference'),
            ProcessingStage::Classifying => $this->dispatchRecovery($submission, $stage, ClassifySubmission::class, 'inference'),
            ProcessingStage::Retrieving, ProcessingStage::Explaining, ProcessingStage::Done, null => $this->failUnsafeRecovery(
                $submission,
                $stage ?? ProcessingStage::Queued,
            ),
        };
    }

    /** @param class-string $jobClass */
    private function dispatchRecovery(
        Submission $submission,
        ProcessingStage $stage,
        string $jobClass,
        string $queue,
    ): string {
        $this->events->record(
            $submission,
            $stage,
            EventOutcome::Retried,
            'watchdog',
            max(1, $submission->attempt_count + 1),
            errorCode: 'STALE_PROCESSING',
            metadata: [
                'recovery_job' => class_basename($jobClass),
                'queue' => $queue,
                'stale_after_minutes' => $this->staleAfterMinutes(),
            ],
        );

        if ($jobClass === ProcessSubmission::class) {
            ProcessSubmission::dispatch($submission)->onQueue($queue);
        } else {
            $jobClass::dispatch($submission->getKey())->onQueue($queue);
        }

        return $queue;
    }

    private function failUnsafeRecovery(Submission $submission, ProcessingStage $stage): string
    {
        $submission->update([
            'status' => 'failed',
            'processing_completed_at' => now(),
            'failure_reason' => 'Pemrosesan tidak dapat dipulihkan secara aman.',
            'last_error_service' => 'watchdog',
            'last_error_code' => 'STALE_RECOVERY_UNSAFE',
        ]);

        $this->events->record(
            $submission,
            $stage,
            EventOutcome::Failed,
            'watchdog',
            max(1, $submission->attempt_count + 1),
            errorCode: 'STALE_RECOVERY_UNSAFE',
            metadata: ['stale_after_minutes' => $this->staleAfterMinutes()],
        );

        return 'failed';
    }
}
