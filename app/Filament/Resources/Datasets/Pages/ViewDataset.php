<?php

namespace App\Filament\Resources\Datasets\Pages;

use App\Filament\Resources\Datasets\DatasetResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewDataset extends ViewRecord
{
    protected static string $resource = DatasetResource::class;

    protected ?string $subheading = DatasetResource::CURATION_NOTICE;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
