<?php

namespace App\Filament\Resources\Datasets\Pages;

use App\Filament\Resources\Datasets\DatasetResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDatasets extends ListRecords
{
    protected static string $resource = DatasetResource::class;

    protected ?string $subheading = DatasetResource::CURATION_NOTICE;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
