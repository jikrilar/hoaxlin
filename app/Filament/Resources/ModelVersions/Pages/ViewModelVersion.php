<?php

namespace App\Filament\Resources\ModelVersions\Pages;

use App\Filament\Resources\ModelVersions\ModelVersionResource;
use Filament\Resources\Pages\ViewRecord;

class ViewModelVersion extends ViewRecord
{
    protected static string $resource = ModelVersionResource::class;

    protected ?string $subheading = ModelVersionResource::TRAINING_NOTICE;

    protected function getHeaderActions(): array
    {
        return [
            ModelVersionResource::makeActivateAction(),
        ];
    }
}
