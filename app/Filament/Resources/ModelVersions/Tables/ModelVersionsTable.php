<?php

namespace App\Filament\Resources\ModelVersions\Tables;

use App\Filament\Resources\ModelVersions\ModelVersionResource;
use App\Models\ModelVersion;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class ModelVersionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('version_name')
                    ->label('Versi')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(ModelVersionResource::statusText(...))
                    ->color(ModelVersionResource::statusColor(...))
                    ->sortable(),
                TextColumn::make('total_rows')
                    ->label('Baris dataset')
                    ->formatStateUsing(static fn (?int $state): string => number_format((int) $state, 0, ',', '.'))
                    ->sortable(),
                TextColumn::make('accuracy')
                    ->label('Akurasi')
                    ->formatStateUsing(ModelVersionResource::formatPercentage(...))
                    ->placeholder('Belum tersedia')
                    ->sortable(),
                TextColumn::make('f1_score')
                    ->label('F1-score')
                    ->formatStateUsing(ModelVersionResource::formatPercentage(...))
                    ->placeholder('Belum tersedia')
                    ->sortable(),
                TextColumn::make('training_duration')
                    ->label('Durasi')
                    ->formatStateUsing(ModelVersionResource::formatDuration(...))
                    ->placeholder('Belum tersedia')
                    ->sortable(),
                TextColumn::make('is_active')
                    ->label('Status aktif')
                    ->badge()
                    ->formatStateUsing(static fn (bool $state): string => $state ? 'Aktif' : 'Tidak aktif')
                    ->color(static fn (bool $state): string => $state ? 'success' : 'gray')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status pelatihan')
                    ->options(ModelVersionResource::statusOptions()),
                TernaryFilter::make('is_active')
                    ->label('Status inferensi aktif'),
            ])
            ->recordActions([
                ViewAction::make()->label('Lihat detail'),
                ModelVersionResource::makeActivateAction(),
            ])
            ->toolbarActions([])
            ->defaultSort('created_at', 'desc')
            ->poll(static fn (): ?string => ModelVersion::query()
                ->whereIn('status', [ModelVersion::STATUS_PENDING, ModelVersion::STATUS_TRAINING])
                ->exists() ? '5s' : null)
            ->emptyStateHeading('Belum ada versi model tambahan')
            ->emptyStateDescription('Unggah dataset CSV baru untuk melatih versi IndoBERT secara bertahap. Baseline v1.0.0 tetap menjadi model bawaan.');
    }
}
