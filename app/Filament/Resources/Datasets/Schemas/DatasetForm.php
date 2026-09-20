<?php

namespace App\Filament\Resources\Datasets\Schemas;

use App\Rules\AdminUser;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class DatasetForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Katalog & Kurasi')
                    ->description('Referensi kurasi production. Perubahan di sini tidak melatih ulang atau mengganti model BERT aktif.')
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
                            ->relationship(
                                'verifier',
                                'name',
                                modifyQueryUsing: fn (Builder $query): Builder => $query->admins(),
                            )
                            ->rule(new AdminUser)
                            ->searchable()
                            ->preload()
                            ->nullable(),
                    ])
                    ->columns(2),
            ]);
    }
}
