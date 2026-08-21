<?php

namespace App\Filament\Resources\Feedback\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class FeedbackForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Umpan Balik')
                    ->schema([
                        Select::make('submission_id')->label('Submission')->relationship('submission', 'id')->searchable()->required()->unique(ignoreRecord: true),
                        Select::make('user_id')->label('Pengguna')->relationship('user', 'name')->searchable()->preload()->required(),
                        Toggle::make('is_correct')->label('Prediksi benar')->required(),
                        Textarea::make('comment')->label('Komentar')->maxLength(1000)->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }
}
