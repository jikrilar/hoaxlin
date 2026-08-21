<?php

namespace App\Filament\Resources\DetectionResults\Schemas;

use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;

class DetectionResultInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Text::make('submission_id')->label('Submission'),
                Text::make('label')->label('Label'),
                Text::make('confidence_percentage')->label('Confidence')->suffix('%'),
                Text::make('model_version')->label('Versi model'),
                Text::make('explanation')->label('Penjelasan')->placeholder('-')->columnSpanFull(),
                Text::make('created_at')->label('Dibuat')->dateTime(),
            ]);
    }
}
