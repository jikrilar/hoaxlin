<?php

namespace App\Filament\Resources\Feedback\Tables;

use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
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
                TextColumn::make('user.name')->label('Pengguna')->searchable(),
                IconColumn::make('is_correct')->label('Benar')->boolean(),
                TextColumn::make('comment')->label('Komentar')->limit(60)->searchable()->placeholder('-'),
                TextColumn::make('created_at')->label('Dikirim')->dateTime()->sortable(),
            ])
            ->filters([
                TernaryFilter::make('is_correct')->label('Prediksi benar'),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([]);
    }
}
