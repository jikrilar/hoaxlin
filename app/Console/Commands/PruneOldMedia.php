<?php

namespace App\Console\Commands;

use App\Models\Submission;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class PruneOldMedia extends Command
{
    protected $signature = 'media:prune
        {--hours= : Hours to retain media after terminal processing}
        {--dry-run : Show what would be deleted}';

    protected $description = 'Delete old private media files for retention compliance (C12)';

    public function handle(): int
    {
        $hours = max(1, (int) ($this->option('hours') ?? config('data_retention.media_hours', 24)));
        $dryRun = (bool) $this->option('dry-run');
        $cutoff = now()->subHours($hours);

        $query = Submission::whereNotNull('media_path')
            ->whereIn('status', ['completed', 'failed'])
            ->whereNotNull('processing_completed_at')
            ->where('processing_completed_at', '<=', $cutoff);

        $count = $query->count();
        $this->info("Found {$count} terminal submissions with media older than {$hours} hours.");

        if ($dryRun) {
            $this->info('Dry run — no files deleted.');

            return self::SUCCESS;
        }

        $disk = config('filesystems.media_disk', config('filesystems.default', 'local'));
        $deletedFiles = 0;
        $clearedRecords = 0;
        $failedFiles = 0;
        $query->chunkById(100, function ($submissions) use (&$deletedFiles, &$clearedRecords, &$failedFiles, $disk) {
            foreach ($submissions as $submission) {
                $path = $submission->media_path;
                if ($path && Storage::disk($disk)->exists($path)) {
                    if (! Storage::disk($disk)->delete($path)) {
                        $failedFiles++;
                        $this->error("Failed to prune media for submission {$submission->getKey()}.");

                        continue;
                    }

                    $deletedFiles++;
                }

                $submission->update(['media_path' => null]);
                $clearedRecords++;
            }
        });

        $this->info("Pruned {$deletedFiles} media files and cleared {$clearedRecords} media references.");
        Log::info('Media retention pruning completed.', [
            'retention_hours' => $hours,
            'cutoff' => $cutoff->toIso8601String(),
            'deleted_files' => $deletedFiles,
            'cleared_records' => $clearedRecords,
            'failed_files' => $failedFiles,
        ]);

        return $failedFiles === 0 ? self::SUCCESS : self::FAILURE;
    }
}
