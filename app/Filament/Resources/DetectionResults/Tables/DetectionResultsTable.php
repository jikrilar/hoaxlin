<?php

namespace App\Filament\Resources\DetectionResults\Tables;

use App\Filament\Support\ReadOnlyPresentation;
use App\Models\DetectionResult;
use App\View\Presenters\DetectionPresentation;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class DetectionResultsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('submission_id')->label('Submission')->sortable(),
                TextColumn::make('submission.user.name')->label('Pengguna')->placeholder('Tamu')->searchable()->wrap(),
                TextColumn::make('submission.status')->label('Status submission')->formatStateUsing(ReadOnlyPresentation::status(...))->placeholder('Submission tidak tersedia'),
                TextColumn::make('record_context')->label('Konteks record')->state(ReadOnlyPresentation::resultContext(...))->wrap(),
                TextColumn::make('label')->label('Prediksi tersimpan')->formatStateUsing(fn (DetectionResult $record): string => ReadOnlyPresentation::resultLabel($record))->sortable()->wrap(),
                TextColumn::make('confidence_percentage')->label('Skor keyakinan model')->state(fn (DetectionResult $record): ?string => ReadOnlyPresentation::confidence($record->confidence_score))->placeholder('Skor tidak tersedia')->sortable(query: fn ($query, string $direction) => $query->orderBy('confidence_score', $direction)),
                TextColumn::make('model_version')->label('Versi model classifier')->searchable()->placeholder('Versi model tidak tersedia')->wrap(),
                TextColumn::make('created_at')->label('Dibuat')->dateTime('d/m/Y H:i')->sortable(),
            ])
            ->filters([
                SelectFilter::make('label')->label('Prediksi tersimpan')->options(array_combine(['valid', 'hoax', 'meragukan'], array_map(fn (string $label): string => DetectionPresentation::label($label)['text'], ['valid', 'hoax', 'meragukan']))),
            ])
            ->recordActions([
                ViewAction::make()->label('Lihat detail'),
            ])
            ->toolbarActions([])
            ->emptyStateHeading('Tidak ada record classifier yang ditampilkan')
            ->emptyStateDescription('Ubah pencarian atau filter. Record classifier belum tentu merupakan hasil final submission.');
    }
}
