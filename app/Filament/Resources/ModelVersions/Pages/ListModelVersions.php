<?php

namespace App\Filament\Resources\ModelVersions\Pages;

use App\Exceptions\AiServiceException;
use App\Filament\Resources\ModelVersions\ModelVersionResource;
use App\Services\Bert\BertTrainingService;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Throwable;

class ListModelVersions extends ListRecords
{
    protected static string $resource = ModelVersionResource::class;

    protected ?string $subheading = ModelVersionResource::TRAINING_NOTICE;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Latih Versi Baru'),
            Action::make('activateBaseline')
                ->label('Aktifkan Baseline v1.0.0')
                ->icon(Heroicon::OutlinedArrowUturnLeft)
                ->color('gray')
                ->authorize(static fn (): bool => auth()->user()?->is_admin === true)
                ->requiresConfirmation()
                ->modalHeading('Kembalikan model ke baseline v1.0.0?')
                ->modalDescription('Layanan BERT akan memuat ulang artefak baseline v1.0.0 yang terverifikasi. Versi model hasil pelatihan bertahap tidak dihapus dan dapat diaktifkan kembali kapan saja.')
                ->modalSubmitActionLabel('Aktifkan Baseline v1.0.0')
                ->action(static function (): void {
                    try {
                        app(BertTrainingService::class)->activateBaseline();
                    } catch (AiServiceException $exception) {
                        Notification::make()
                            ->title('Gagal mengaktifkan baseline v1.0.0')
                            ->body($exception->getMessage())
                            ->danger()
                            ->send();

                        return;
                    } catch (Throwable) {
                        Notification::make()
                            ->title('Gagal mengaktifkan baseline v1.0.0')
                            ->body('Terjadi galat tak terduga saat memuat ulang baseline v1.0.0 pada layanan BERT.')
                            ->danger()
                            ->send();

                        return;
                    }

                    Notification::make()
                        ->title('Baseline v1.0.0 diaktifkan')
                        ->body('Runtime BERT dan database kini kembali menggunakan baseline terverifikasi v1.0.0.')
                        ->success()
                        ->send();
                }),
        ];
    }
}
