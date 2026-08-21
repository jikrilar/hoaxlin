<?php

namespace App\Filament\Resources\DetectionResults\Pages;

use App\Filament\Resources\DetectionResults\DetectionResultResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditDetectionResult extends EditRecord
{
    protected static string $resource = DetectionResultResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
