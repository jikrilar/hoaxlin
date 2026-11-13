<?php

namespace App\Services\Pipeline;

use App\Jobs\ProcessSubmission;
use App\Models\Submission;
use Illuminate\Database\Eloquent\Builder;

/**
 * Shared retry path for failed submissions (T3.4).
 *
 * Both `submissions:recover` and the admin "Coba lagi" action go through here,
 * so the state reset and the job dispatch exist in exactly one place. The failed
 * path is a conditional UPDATE: two concurrent clicks (or a click racing the
 * watchdog) can never queue the same submission twice, because only the update
 * that still sees `failed` and the expected `updated_at` wins.
 *
 * Stalled rows are handed to the watchdog's recovery, which resumes the correct
 * pipeline stage instead of restarting from the beginning.
 */
class SubmissionRecovery
{
    public const ACTION = 'submission_retried';

    public function __construct(private readonly StaleSubmissionRecovery $stale) {}

    public function failedQuery(?int $submissionId = null, ?int $days = null): Builder
    {
        return Submission::query()
            ->where('status', 'failed')
            ->when($submissionId !== null, fn (Builder $query) => $query->whereKey($submissionId))
            ->when(
                $submissionId === null && $days !== null,
                fn (Builder $query) => $query->where('processing_completed_at', '>=', now()->subDays($days)),
            );
    }

    public function isRetryable(Submission $submission): bool
    {
        return $submission->isFailed() || $this->isStalled($submission);
    }

    public function isStalled(Submission $submission): bool
    {
        return in_array($submission->status, ['pending', 'processing'], true)
            && $submission->updated_at !== null
            && $submission->updated_at->lte(now()->subMinutes($this->stale->staleAfterMinutes()));
    }

    /**
     * Requeue one submission.
     *
     * @return bool True when this call claimed and dispatched it, false when the
     *              submission had already moved on (completed, requeued, deleted).
     */
    public function retry(Submission $submission): bool
    {
        if ($submission->isFailed()) {
            return $this->retryFailed($submission);
        }

        if (! $this->isStalled($submission)) {
            return false;
        }

        // The watchdog claims atomically and returns null when it lost the race.
        return $this->stale->recover($submission) !== null;
    }

    /**
     * @return array{recovered: int, skipped: int}
     */
    public function retryQuery(Builder $query, int $chunkSize = 100): array
    {
        $recovered = 0;
        $skipped = 0;

        $query->chunkById($chunkSize, function ($submissions) use (&$recovered, &$skipped): void {
            foreach ($submissions as $submission) {
                $this->retryFailed($submission) ? $recovered++ : $skipped++;
            }
        });

        return ['recovered' => $recovered, 'skipped' => $skipped];
    }

    private function retryFailed(Submission $submission): bool
    {
        $claimed = Submission::query()
            ->whereKey($submission->getKey())
            ->where('status', 'failed')
            ->where('updated_at', $submission->updated_at)
            ->update([
                'status' => 'pending',
                'processing_stage' => 'queued',
                'failure_reason' => null,
                'last_error_service' => null,
                'last_error_code' => null,
                'processing_completed_at' => null,
                'updated_at' => now(),
            ]);

        if ($claimed !== 1) {
            return false;
        }

        ProcessSubmission::dispatch($submission->fresh());

        return true;
    }
}
