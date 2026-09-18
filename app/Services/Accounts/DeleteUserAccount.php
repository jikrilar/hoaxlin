<?php

namespace App\Services\Accounts;

use App\Models\AdminLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class DeleteUserAccount
{
    public function handle(User $user): void
    {
        $mediaPaths = $user->submissions()
            ->whereNotNull('media_path')
            ->pluck('media_path')
            ->filter()
            ->unique()
            ->values();

        $disk = Storage::disk(config('filesystems.media_disk', config('filesystems.default', 'local')));

        DB::transaction(function () use ($user, $mediaPaths, $disk): void {
            foreach ($mediaPaths as $path) {
                if ($disk->exists($path) && ! $disk->delete($path)) {
                    throw new RuntimeException('Media akun tidak dapat dihapus.');
                }
            }

            DB::table('password_reset_tokens')->where('email', $user->email)->delete();
            AdminLog::query()
                ->where('target_table', $user->getTable())
                ->where('target_id', $user->getKey())
                ->delete();

            $user->delete();
        });
    }
}
