<?php

namespace App\Services\Admin;

use App\Models\AdminLog;
use App\Models\Feedback;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * "Tandai sudah ditinjau" for feedback (T3.7).
 *
 * The claim is a conditional UPDATE on `reviewed_at IS NULL`, so two admins
 * clicking at the same time cannot overwrite who reviewed first, and a repeat
 * click is a no-op instead of a second audit entry.
 */
final class FeedbackReview
{
    public const ACTION = 'feedback_reviewed';

    public function pendingQuery(): Builder
    {
        return Feedback::query()->pendingReview();
    }

    public function markReviewed(Feedback $feedback): bool
    {
        $admin = auth()->user();

        if ($admin === null || ! $admin->is_admin || ! $this->claim($feedback)) {
            return false;
        }

        $this->recordAudit([$feedback->getKey()], $admin->getKey());
        DashboardMetrics::forget(['needs-action', 'kpis', 'breakdown']);

        return true;
    }

    /**
     * Claim one row without writing an audit entry.
     *
     * Bulk review uses this so the whole action produces a single audit row
     * instead of one per record.
     */
    private function claim(Feedback $feedback): bool
    {
        $admin = auth()->user();

        if ($admin === null || ! $admin->is_admin) {
            return false;
        }

        return Feedback::query()
            ->whereKey($feedback->getKey())
            ->whereNull('reviewed_at')
            ->update([
                'reviewed_at' => now(),
                'reviewed_by' => $admin->getKey(),
            ]) === 1;
    }

    /**
     * @param  Collection<int, Feedback>  $records
     * @return array{reviewed: list<int>, skipped: int}
     */
    public function markManyReviewed(Collection $records): array
    {
        $reviewed = [];
        $skipped = 0;

        foreach ($records as $record) {
            if ($record instanceof Feedback && $this->claim($record)) {
                $reviewed[] = $record->getKey();
            } else {
                $skipped++;
            }
        }

        // One audit row per bulk action, not one per record.
        if ($reviewed !== []) {
            $this->recordAudit($reviewed, (int) auth()->id());
            DashboardMetrics::forget(['needs-action', 'kpis', 'breakdown']);
        }

        return ['reviewed' => $reviewed, 'skipped' => $skipped];
    }

    /**
     * Allowlist only: action, count, and feedback IDs. The comment is user text
     * and is never copied into the audit log.
     *
     * @param  list<int>  $feedbackIds
     */
    private function recordAudit(array $feedbackIds, int $adminId): void
    {
        AdminLog::create([
            'admin_id' => $adminId,
            'action' => self::ACTION,
            'target_table' => 'feedback',
            'target_id' => $feedbackIds[0],
            'metadata' => [
                'count' => count($feedbackIds),
                'feedback_ids' => array_values($feedbackIds),
            ],
        ]);
    }
}
