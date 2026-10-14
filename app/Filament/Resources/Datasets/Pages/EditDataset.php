<?php

namespace App\Filament\Resources\Datasets\Pages;

use App\Filament\Resources\Datasets\DatasetResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditDataset extends EditRecord
{
    protected static string $resource = DatasetResource::class;

    protected ?string $subheading = DatasetResource::CURATION_NOTICE;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make()
                ->modalHeading('Hapus data kurasi ini?')
                ->modalDescription('Data akan dihapus dari katalog. Tindakan ini tidak dapat dibatalkan dan tidak melatih ulang atau mengganti model BERT aktif.')
                ->modalSubmitActionLabel('Hapus data kurasi'),
        ];
    }
}
