<?php

namespace App\Filament\Resources\Submissions\Tables;

use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class SubmissionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->label('ID')->sortable(),
                TextColumn::make('user.name')->label('Pengguna')->placeholder('Tamu')->searchable(),
                TextColumn::make('input_type')->label('Jenis')->badge()->sortable(),
                TextColumn::make('content')->label('Konten')->limit(60)->searchable(['raw_input', 'extracted_text', 'source_url']),
                TextColumn::make('status')->label('Status')->badge()->sortable(),
                TextColumn::make('detectionResult.label')->label('Hasil')->badge()->placeholder('Belum ada'),
                TextColumn::make('created_at')->label('Dikirim')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('input_type')->label('Jenis')->options(['text' => 'Teks', 'image' => 'Gambar', 'video' => 'Video', 'url' => 'URL']),
                SelectFilter::make('status')->options(['pending' => 'Menunggu', 'processing' => 'Diproses', 'completed' => 'Selesai', 'failed' => 'Gagal']),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([]);
    }
}
