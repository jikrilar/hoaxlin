<?php

namespace App\Filament\Resources\Datasets\Schemas;

use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;

class DatasetInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Text::make('text')->label('Teks')->columnSpanFull(),
                Text::make('label')->label('Label'),
                Text::make('source')->label('Sumber'),
                Text::make('verifier.name')->label('Diverifikasi oleh')->placeholder('Belum diverifikasi'),
                Text::make('created_at')->label('Dibuat')->dateTime(),
                Text::make('updated_at')->label('Diperbarui')->dateTime(),
            ]);
    }
}
