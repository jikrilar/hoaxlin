<?php

namespace App\Filament\Resources\DetectionResults\Tables;

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
                TextColumn::make('submission.user.name')->label('Pengguna')->placeholder('Tamu')->searchable(),
                TextColumn::make('label')->label('Label')->badge()->sortable(),
                TextColumn::make('confidence_percentage')->label('Confidence')->suffix('%')->sortable(query: fn ($query, string $direction) => $query->orderBy('confidence_score', $direction)),
                TextColumn::make('model_version')->label('Versi model')->searchable(),
                TextColumn::make('created_at')->label('Dibuat')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('label')->options(['valid' => 'Valid', 'hoax' => 'Hoax', 'meragukan' => 'Meragukan']),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([]);
    }
}
