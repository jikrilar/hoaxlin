<?php

namespace App\Filament\Actions;

use App\Models\AdminLog;
use App\Models\Submission;
use App\Services\Admin\DashboardMetrics;
use App\Services\Pipeline\SubmissionRecovery;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;

/**
 * "Coba lagi" for the admin panel (T3.4).
 *
 * The action never writes pipeline state itself: it delegates to
 * `SubmissionRecovery`, the same service the artisan command uses. Eligibility
 * is re-checked inside the service, so a stale row or a double click cannot
 * dispatch twice.
 */
final class RetrySubmissionAction
{
    public static function make(): Action
    {
        return Action::make('retry')
            ->label('Coba lagi')
            ->icon(Heroicon::OutlinedArrowPath)
            ->color('gray')
            ->authorize('retry')
            ->visible(fn (Submission $record): bool => app(SubmissionRecovery::class)->isRetryable($record))
            ->requiresConfirmation()
            ->modalHeading('Coba lagi pemeriksaan ini?')
            ->modalDescription(fn (Submission $record): string => app(SubmissionRecovery::class)->isStalled($record)
                ? 'Pemeriksaan ini tertahan di tengah proses. Pemrosesan dilanjutkan dari tahap terakhir yang tercatat, bukan diulang dari awal. Hasil yang sudah tersimpan tidak dihapus.'
                : 'Pemeriksaan ini berstatus gagal. Pemrosesan diulang dari awal antrean dengan input yang sama; hasil sebelumnya tidak dihapus, dan pemeriksaan yang sudah selesai tidak akan diulang.')
            ->modalSubmitActionLabel('Coba lagi')
            ->action(function (Submission $record): void {
                $recovered = app(SubmissionRecovery::class)->retry($record);

                if (! $recovered) {
                    Notification::make()
                        ->title('Pemeriksaan tidak dapat dicoba lagi')
                        ->body('Status pemeriksaan sudah berubah. Muat ulang daftar untuk melihat kondisi terbaru.')
                        ->warning()
                        ->send();

                    return;
                }

                // The detail page renders the same model instance, so refresh it
                // to show the new status without a manual reload.
                $record->refresh();
                self::recordAudit([$record->getKey()]);
                DashboardMetrics::forget(['needs-action', 'kpis', 'breakdown']);

                Notification::make()
                    ->title('Pemeriksaan dimasukkan kembali ke antrean')
                    ->body('Pantau perubahan status pada daftar pemeriksaan.')
                    ->success()
                    ->send();
            });
    }

    public static function bulk(): BulkAction
    {
        return BulkAction::make('retrySelected')
            ->label('Coba lagi yang dipilih')
            ->icon(Heroicon::OutlinedArrowPath)
            ->color('gray')
            // No single record exists when the bulk action mounts, so the policy
            // is applied per record instead of against the whole selection.
            ->authorize(static fn (): bool => auth()->user()?->is_admin === true)
            ->authorizeIndividualRecords('retry')
            ->deselectRecordsAfterCompletion()
            ->requiresConfirmation()
            ->modalHeading('Coba lagi pemeriksaan terpilih?')
            ->modalDescription('Hanya pemeriksaan berstatus gagal atau tertahan yang akan dimasukkan kembali ke antrean. Yang gagal diulang dari awal antrean; yang tertahan dilanjutkan dari tahap terakhir yang tercatat. Pemeriksaan yang sudah selesai atau masih berjalan normal dilewati.')
            ->modalSubmitActionLabel('Coba lagi yang dipilih')
            ->action(function (Collection $records): void {
                $recovery = app(SubmissionRecovery::class);
                $recovered = [];
                $skipped = 0;

                foreach ($records as $record) {
                    if ($record instanceof Submission && $recovery->retry($record)) {
                        $recovered[] = $record->getKey();
                    } else {
                        $skipped++;
                    }
                }

                if ($recovered !== []) {
                    self::recordAudit($recovered);
                    DashboardMetrics::forget(['needs-action', 'kpis', 'breakdown']);
                }

                Notification::make()
                    ->title($recovered === []
                        ? 'Tidak ada pemeriksaan yang dicoba lagi'
                        : count($recovered).' pemeriksaan dimasukkan kembali ke antrean')
                    ->body($skipped === 0
                        ? 'Pantau perubahan status pada daftar pemeriksaan.'
                        : "{$skipped} pemeriksaan dilewati karena sudah selesai atau masih berjalan normal.")
                    ->{$recovered === [] ? 'warning' : 'success'}()
                    ->send();
            });
    }

    /**
     * Allowlist only: action, count, and submission IDs. No input, media path,
     * failure text, or user identity is copied into the log.
     *
     * @param  list<int>  $submissionIds
     */
    private static function recordAudit(array $submissionIds): void
    {
        $admin = auth()->user();

        if ($admin === null || ! $admin->is_admin) {
            return;
        }

        AdminLog::create([
            'admin_id' => $admin->getKey(),
            'action' => SubmissionRecovery::ACTION,
            'target_table' => 'submissions',
            'target_id' => $submissionIds[0],
            'metadata' => [
                'count' => count($submissionIds),
                'submission_ids' => array_values($submissionIds),
            ],
        ]);
    }
}
