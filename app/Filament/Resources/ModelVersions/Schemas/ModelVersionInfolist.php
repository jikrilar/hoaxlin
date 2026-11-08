<?php

namespace App\Filament\Resources\ModelVersions\Schemas;

use App\Filament\Resources\ModelVersions\ModelVersionResource;
use App\Models\ModelVersion;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ModelVersionInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Ringkasan versi model')
                    ->description('Informasi siklus hidup dan status aktivasi versi model BERT.')
                    ->extraAttributes(static fn (?ModelVersion $record): array => ($record !== null && ($record->isPending() || $record->isTraining()))
                        ? ['wire:poll.5s' => '$refresh']
                        : [])
                    ->schema([
                        TextEntry::make('version_name')
                            ->label('Nama versi'),
                        TextEntry::make('status')
                            ->label('Status pelatihan')
                            ->badge()
                            ->formatStateUsing(ModelVersionResource::statusText(...))
                            ->color(ModelVersionResource::statusColor(...)),
                        TextEntry::make('is_active')
                            ->label('Status inferensi')
                            ->badge()
                            ->formatStateUsing(static fn (bool $state): string => $state ? 'Aktif' : 'Tidak aktif')
                            ->color(static fn (bool $state): string => $state ? 'success' : 'gray'),
                        TextEntry::make('dataset_path')
                            ->label('Berkas CSV pelatihan')
                            ->formatStateUsing(static fn (?string $state): ?string => filled($state) ? basename($state) : null)
                            ->placeholder('Tidak tercatat'),
                        TextEntry::make('total_rows')
                            ->label('Jumlah baris dataset')
                            ->formatStateUsing(static fn (?int $state): string => number_format((int) $state, 0, ',', '.').' baris'),
                        TextEntry::make('created_at')
                            ->label('Dibuat')
                            ->dateTime('d/m/Y H:i:s'),
                        TextEntry::make('updated_at')
                            ->label('Terakhir diperbarui')
                            ->dateTime('d/m/Y H:i:s'),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
                Section::make('Hasil evaluasi pelatihan')
                    ->description('Metrik evaluasi dihitung pada split validasi pelatihan bertahap. Bukan jaminan akurasi pada informasi baru.')
                    ->schema([
                        TextEntry::make('accuracy')
                            ->label('Akurasi validasi')
                            ->formatStateUsing(ModelVersionResource::formatPercentage(...))
                            ->placeholder('Belum tersedia'),
                        TextEntry::make('f1_score')
                            ->label('Macro F1-score')
                            ->formatStateUsing(ModelVersionResource::formatPercentage(...))
                            ->placeholder('Belum tersedia'),
                        TextEntry::make('training_duration')
                            ->label('Durasi pelatihan')
                            ->formatStateUsing(ModelVersionResource::formatDuration(...))
                            ->placeholder('Belum tersedia'),
                    ])
                    ->columns(3)
                    ->columnSpanFull(),
                Section::make('Catatan kegagalan')
                    ->visible(static fn (?ModelVersion $record): bool => $record !== null && filled($record->error_message))
                    ->schema([
                        TextEntry::make('error_message')
                            ->label('Ringkasan kendala')
                            ->extraAttributes(['class' => 'whitespace-pre-line'])
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
