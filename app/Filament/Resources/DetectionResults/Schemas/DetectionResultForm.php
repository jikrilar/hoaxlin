<?php

namespace App\Filament\Resources\DetectionResults\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class DetectionResultForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Hasil Klasifikasi')
                    ->schema([
                        Select::make('submission_id')->label('Submission')->relationship('submission', 'id')->searchable()->required()->unique(ignoreRecord: true),
                        Select::make('label')->label('Label')->options(['valid' => 'Valid', 'hoax' => 'Hoax', 'meragukan' => 'Meragukan'])->required(),
                        TextInput::make('confidence_score')->label('Confidence')->numeric()->minValue(0)->maxValue(1)->step(0.0001)->required(),
                        TextInput::make('model_version')->label('Versi model')->required()->maxLength(255),
                        Textarea::make('explanation')->label('Penjelasan')->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }
}
