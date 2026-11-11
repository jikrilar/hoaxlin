<?php

namespace App\Filament\Resources\ModelVersions;

use App\Exceptions\AiServiceException;
use App\Filament\Resources\ModelVersions\Pages\CreateModelVersion;
use App\Filament\Resources\ModelVersions\Pages\ListModelVersions;
use App\Filament\Resources\ModelVersions\Pages\ViewModelVersion;
use App\Filament\Resources\ModelVersions\Schemas\ModelVersionForm;
use App\Filament\Resources\ModelVersions\Schemas\ModelVersionInfolist;
use App\Filament\Resources\ModelVersions\Tables\ModelVersionsTable;
use App\Models\ModelVersion;
use App\Services\Bert\BertTrainingService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Throwable;
use UnitEnum;

class ModelVersionResource extends Resource
{
    public const TRAINING_NOTICE = 'Pelatihan bertahap (incremental fine-tuning) berjalan di latar belakang pada layanan BERT tanpa menggantikan baseline v1.0.0 secara otomatis. Aktivasi versi baru dilakukan secara manual setelah pelatihan selesai.';

    protected static ?string $model = ModelVersion::class;

    protected static ?string $navigationLabel = 'Versi Model';

    protected static ?string $modelLabel = 'versi model';

    protected static ?string $pluralModelLabel = 'versi model';

    protected static UnitEnum|string|null $navigationGroup = 'Model AI';

    protected static ?int $navigationSort = 40;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCpuChip;

    /**
     * @return array<string, string>
     */
    public static function statusOptions(): array
    {
        return [
            ModelVersion::STATUS_PENDING => 'Menunggu antrean',
            ModelVersion::STATUS_TRAINING => 'Sedang dilatih',
            ModelVersion::STATUS_COMPLETED => 'Selesai',
            ModelVersion::STATUS_FAILED => 'Gagal',
        ];
    }

    public static function statusText(?string $state): string
    {
        if ($state === null) {
            return 'Tidak diketahui';
        }

        return static::statusOptions()[$state] ?? $state;
    }

    public static function statusColor(?string $state): string
    {
        return match ($state) {
            ModelVersion::STATUS_COMPLETED => 'success',
            ModelVersion::STATUS_TRAINING => 'warning',
            ModelVersion::STATUS_FAILED => 'danger',
            default => 'gray',
        };
    }

    public static function formatPercentage(?float $state): ?string
    {
        if ($state === null) {
            return null;
        }

        return number_format($state * 100, 2, '.', '').'%';
    }

    public static function formatDuration(?int $state): ?string
    {
        if ($state === null) {
            return null;
        }

        return number_format($state, 0, ',', '.').' detik';
    }

    public static function formatDelta(?float $candidate, ?float $baseline): ?string
    {
        if ($candidate === null || $baseline === null) {
            return null;
        }

        $diff = ($candidate - $baseline) * 100;
        $sign = $diff > 0.0001 ? '+' : '';

        return sprintf(
            '%s%s%% (%s vs baseline %s)',
            $sign,
            number_format($diff, 2, '.', ''),
            static::formatPercentage($candidate),
            static::formatPercentage($baseline),
        );
    }

    public static function formatBaselineSummary(?ModelVersion $record): ?string
    {
        if ($record === null) {
            return null;
        }

        if (strtolower($record->version_name) === strtolower(BertTrainingService::BASELINE_VERSION)) {
            return 'Acuan baseline v1.0.0';
        }

        if ($record->accuracy === null || $record->baseline_accuracy === null) {
            return null;
        }

        $accDelta = ($record->accuracy - $record->baseline_accuracy) * 100;
        $accSign = $accDelta > 0.0001 ? '+' : '';
        $summary = sprintf(
            'Akurasi %s%s%% (baseline %s)',
            $accSign,
            number_format($accDelta, 2, '.', ''),
            static::formatPercentage($record->baseline_accuracy),
        );

        if ($record->f1_score !== null && $record->baseline_f1_score !== null) {
            $f1Delta = ($record->f1_score - $record->baseline_f1_score) * 100;
            $f1Sign = $f1Delta > 0.0001 ? '+' : '';
            $summary .= sprintf(
                ' · F1 %s%s%% (baseline %s)',
                $f1Sign,
                number_format($f1Delta, 2, '.', ''),
                static::formatPercentage($record->baseline_f1_score),
            );
        }

        return $summary;
    }

    public static function makeActivateAction(): Action
    {
        return Action::make('activate')
            ->label('Aktifkan Model')
            ->icon(Heroicon::OutlinedCheckCircle)
            ->color('primary')
            ->authorize('activate')
            ->visible(static fn (ModelVersion $record): bool => $record->isCompleted() && ! $record->is_active)
            ->requiresConfirmation()
            ->modalHeading(static fn (ModelVersion $record): string => "Aktifkan model {$record->version_name}?")
            ->modalDescription(static function (ModelVersion $record): string {
                $baseText = "Layanan BERT akan melakukan hot-swap ke artefak {$record->version_name}. Status aktif di database hanya diperbarui jika layanan BERT mengonfirmasi pemuatan model berhasil.";
                $comparison = static::formatBaselineSummary($record);

                return $comparison !== null && $comparison !== 'Acuan baseline v1.0.0'
                    ? "{$baseText} Perbandingan validasi: {$comparison}."
                    : $baseText;
            })
            ->modalSubmitActionLabel('Aktifkan Model')
            ->action(static function (ModelVersion $record): void {
                try {
                    app(BertTrainingService::class)->activateModelVersion($record);
                } catch (AiServiceException $exception) {
                    Notification::make()
                        ->title('Gagal mengaktifkan versi model')
                        ->body($exception->getMessage())
                        ->danger()
                        ->send();

                    return;
                } catch (Throwable) {
                    Notification::make()
                        ->title('Gagal mengaktifkan versi model')
                        ->body('Terjadi galat tak terduga saat mengaktifkan model pada layanan BERT.')
                        ->danger()
                        ->send();

                    return;
                }

                Notification::make()
                    ->title("Model {$record->version_name} diaktifkan")
                    ->body('Runtime BERT dan database kini menggunakan versi model yang dipilih.')
                    ->success()
                    ->send();
            });
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return ModelVersionForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ModelVersionInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ModelVersionsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListModelVersions::route('/'),
            'create' => CreateModelVersion::route('/create'),
            'view' => ViewModelVersion::route('/{record}'),
        ];
    }
}
