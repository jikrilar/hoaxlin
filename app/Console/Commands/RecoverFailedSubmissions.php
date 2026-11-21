<?php

namespace App\Console\Commands;

use App\Services\Pipeline\SubmissionRecovery;
use Illuminate\Console\Command;

class RecoverFailedSubmissions extends Command
{
    protected $signature = 'submissions:recover
                            {--id= : Specific submission ID to recover}
                            {--days=7 : Only recover failed submissions from last N days}
                            {--dry-run : Show what would be recovered without dispatching}';

    protected $description = 'Re-dispatch failed submissions for retry (D8)';

    public function handle(SubmissionRecovery $recovery): int
    {
        $id = $this->option('id');
        $query = $recovery->failedQuery(
            $id !== null ? (int) $id : null,
            (int) $this->option('days'),
        );

        $count = (clone $query)->count();
        $this->info("Found {$count} failed submissions to recover.");

        if ($this->option('dry-run')) {
            $this->info('Dry run — no jobs dispatched.');

            return self::SUCCESS;
        }

        $result = $recovery->retryQuery($query);
        $this->info("Recovered {$result['recovered']} submissions (re-dispatched).");

        return self::SUCCESS;
    }
}
