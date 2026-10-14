<?php

namespace App\Filament\Resources\Feedback\Tables;

use App\Filament\Support\ReadOnlyPresentation;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class FeedbackTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('submission_id')->label('Submission')->sortable(),
                TextColumn::make('user.name')->label('Pengguna')->searchable()->placeholder('Pengguna tidak tersedia')->wrap(),
                TextColumn::make('is_correct')->label('Penilaian pengguna')->formatStateUsing(ReadOnlyPresentation::assessment(...))->wrap(),
                TextColumn::make('comment')->label('Cuplikan komentar')->limit(60)->searchable()->placeholder('Tidak ada komentar')->wrap(),
                TextColumn::make('created_at')->label('Dikirim')->dateTime('d/m/Y H:i')->sortable(),
            ])
            ->filters([
                TernaryFilter::make('is_correct')->label('Penilaian pengguna')->trueLabel('Dinilai sesuai')->falseLabel('Dinilai tidak sesuai'),
            ])
            ->recordActions([
                ViewAction::make()->label('Lihat detail'),
            ])
            ->toolbarActions([])
            ->emptyStateHeading('Tidak ada feedback yang ditampilkan')
            ->emptyStateDescription('Ubah pencarian atau filter untuk melihat penilaian pengguna lainnya.');
    }
}
