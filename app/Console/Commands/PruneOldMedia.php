<?php

namespace App\Console\Commands;

use App\Models\Submission;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class PruneOldMedia extends Command
{
    protected $signature = 'media:prune {--days=30 : Days to retain media} {--dry-run : Show what would be deleted}';

    protected $description = 'Delete old private media files for retention compliance (C12)';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $dryRun = (bool) $this->option('dry-run');
        $cutoff = now()->subDays($days);

        $query = Submission::whereNotNull('media_path')
            ->where('created_at', '<', $cutoff);

        $count = $query->count();
        $this->info("Found {$count} submissions with media older than {$days} days (before {$cutoff->toDateString()}).");

        if ($dryRun) {
            $this->info('Dry run — no files deleted.');
            return self::SUCCESS;
        }

        $disk = config('filesystems.media_disk', config('filesystems.default', 'local'));
        $deleted = 0;
        $query->chunkById(100, function ($submissions) use (&$deleted, $disk) {
            foreach ($submissions as $submission) {
                $path = $submission->media_path;
                if ($path && Storage::disk($disk)->exists($path)) {
                    Storage::disk($disk)->delete($path);
                    $deleted++;
                }
                // Keep the submission record but clear the path for audit
                $submission->update(['media_path' => null]);
            }
        });

        $this->info("Pruned {$deleted} media files.");

        return self::SUCCESS;
    }
}
