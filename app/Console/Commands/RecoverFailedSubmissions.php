<?php

namespace App\Console\Commands;

use App\Jobs\ProcessSubmission;
use App\Models\Submission;
use Illuminate\Console\Command;

class RecoverFailedSubmissions extends Command
{
    protected $signature = 'submissions:recover
                            {--id= : Specific submission ID to recover}
                            {--days=7 : Only recover failed submissions from last N days}
                            {--dry-run : Show what would be recovered without dispatching}';

    protected $description = 'Re-dispatch failed submissions for retry (D8)';

    public function handle(): int
    {
        $query = Submission::where('status', 'failed');

        if ($id = $this->option('id')) {
            $query->whereKey($id);
        } else {
            $days = (int) $this->option('days');
            $query->where('processing_completed_at', '>=', now()->subDays($days));
        }

        $count = $query->count();
        $this->info("Found {$count} failed submissions to recover.");

        if ($this->option('dry-run')) {
            $this->info('Dry run — no jobs dispatched.');
            return self::SUCCESS;
        }

        $recovered = 0;
        $query->chunkById(100, function ($submissions) use (&$recovered) {
            foreach ($submissions as $submission) {
                $submission->update([
                    'status' => 'pending',
                    'processing_stage' => 'queued',
                    'failure_reason' => null,
                    'last_error_service' => null,
                    'last_error_code' => null,
                    'processing_completed_at' => null,
                ]);
                ProcessSubmission::dispatch($submission);
                $recovered++;
            }
        });

        $this->info("Recovered {$recovered} submissions (re-dispatched).");

        return self::SUCCESS;
    }
}
