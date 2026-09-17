<?php

namespace App\Console\Commands;

use App\Services\Pipeline\StaleSubmissionRecovery;
use Illuminate\Console\Command;

class RecoverStaleSubmissions extends Command
{
    protected $signature = 'submissions:recover-stale
                            {--id= : Recover one stale processing submission}
                            {--dry-run : Report stale submissions without changing state or dispatching jobs}';

    protected $description = 'Detect and safely recover stale processing submissions';

    public function handle(StaleSubmissionRecovery $recovery): int
    {
        $id = $this->option('id');
        $query = $recovery->staleQuery($id !== null ? (int) $id : null);
        $count = (clone $query)->count();

        $this->info("Found {$count} stale in-flight submissions (threshold: {$recovery->staleAfterMinutes()} minutes).");

        if ($this->option('dry-run')) {
            $this->info('Dry run - no state changed and no jobs dispatched.');

            return self::SUCCESS;
        }

        $recovered = 0;
        $skipped = 0;
        $failedSafely = 0;
        $queueCounts = [];

        $query->chunkById(100, function ($submissions) use ($recovery, &$recovered, &$skipped, &$failedSafely, &$queueCounts): void {
            foreach ($submissions as $submission) {
                $action = $recovery->recover($submission);

                if ($action === null) {
                    $skipped++;
                } elseif ($action === 'failed') {
                    $failedSafely++;
                } else {
                    $recovered++;
                    $queueCounts[$action] = ($queueCounts[$action] ?? 0) + 1;
                }
            }
        });

        $this->info("Recovered {$recovered}; failed safely {$failedSafely}; skipped {$skipped}.");

        if ($queueCounts !== []) {
            ksort($queueCounts);
            $queues = collect($queueCounts)
                ->map(fn (int $total, string $queue): string => "{$queue}={$total}")
                ->implode(', ');
            $this->line("Recovery queues: {$queues}.");
        }

        return self::SUCCESS;
    }
}
