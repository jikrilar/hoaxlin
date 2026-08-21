<?php

namespace App\Filament\Resources\Datasets\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class DatasetForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Data Training')
                    ->schema([
                        Textarea::make('text')
                            ->label('Teks')
                            ->required()
                            ->minLength(10)
                            ->columnSpanFull(),
                        Select::make('label')
                            ->label('Label')
                            ->options(['valid' => 'Valid', 'hoax' => 'Hoax', 'meragukan' => 'Meragukan'])
                            ->required(),
                        TextInput::make('source')
                            ->label('Sumber')
                            ->required()
                            ->maxLength(255),
                        Select::make('verified_by')
                            ->label('Diverifikasi oleh')
                            ->relationship('verifier', 'name')
                            ->searchable()
                            ->preload()
                            ->nullable(),
                    ])
                    ->columns(2),
            ]);
    }
}
