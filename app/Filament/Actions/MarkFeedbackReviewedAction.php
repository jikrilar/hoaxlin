<?php

namespace App\Filament\Actions;

use App\Models\Feedback;
use App\Services\Admin\FeedbackReview;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;

/** "Tandai sudah ditinjau" on the feedback list and detail (T3.7). */
final class MarkFeedbackReviewedAction
{
    public static function make(): Action
    {
        return Action::make('markReviewed')
            ->label('Tandai sudah ditinjau')
            ->icon(Heroicon::OutlinedCheckCircle)
            ->color('gray')
            ->authorize(static fn (): bool => auth()->user()?->is_admin === true)
            ->visible(fn (Feedback $record): bool => ! $record->isReviewed())
            ->requiresConfirmation()
            ->modalHeading('Tandai umpan balik ini sudah ditinjau?')
            ->modalDescription('Peninjauan hanya mencatat bahwa admin sudah membaca umpan balik ini. Label prediksi, komentar pengguna, dan model tidak berubah.')
            ->modalSubmitActionLabel('Tandai sudah ditinjau')
            ->action(function (Feedback $record): void {
                if (! app(FeedbackReview::class)->markReviewed($record)) {
                    Notification::make()
                        ->title('Umpan balik sudah ditinjau')
                        ->body('Admin lain sudah menandai umpan balik ini. Muat ulang daftar untuk melihat kondisi terbaru.')
                        ->warning()
                        ->send();

                    return;
                }

                $record->refresh();

                Notification::make()
                    ->title('Umpan balik ditandai sudah ditinjau')
                    ->success()
                    ->send();
            });
    }

    public static function bulk(): BulkAction
    {
        return BulkAction::make('markReviewedSelected')
            ->label('Tandai sudah ditinjau')
            ->icon(Heroicon::OutlinedCheckCircle)
            ->color('gray')
            // No per-record authorization filter: the service claims each row with a
            // conditional UPDATE, so reviewed rows are counted as skipped instead of
            // being silently dropped from the selection.
            ->authorize(static fn (): bool => auth()->user()?->is_admin === true)
            ->deselectRecordsAfterCompletion()
            ->requiresConfirmation()
            ->modalHeading('Tandai umpan balik terpilih sudah ditinjau?')
            ->modalDescription('Hanya umpan balik yang belum ditinjau yang akan ditandai. Umpan balik yang sudah ditinjau dilewati.')
            ->modalSubmitActionLabel('Tandai sudah ditinjau')
            ->action(function (Collection $records): void {
                $result = app(FeedbackReview::class)->markManyReviewed($records);

                Notification::make()
                    ->title($result['reviewed'] === []
                        ? 'Tidak ada umpan balik yang ditandai'
                        : count($result['reviewed']).' umpan balik ditandai sudah ditinjau')
                    ->body($result['skipped'] === 0
                        ? 'Daftar akan diperbarui otomatis.'
                        : "{$result['skipped']} umpan balik dilewati karena sudah ditinjau.")
                    ->{$result['reviewed'] === [] ? 'warning' : 'success'}()
                    ->send();
            });
    }
}
