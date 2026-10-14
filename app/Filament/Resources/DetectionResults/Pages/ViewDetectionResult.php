<?php

namespace App\Filament\Resources\DetectionResults\Pages;

use App\Filament\Resources\DetectionResults\DetectionResultResource;
use App\Filament\Support\ReadOnlyPresentation;
use Filament\Resources\Pages\ViewRecord;

class ViewDetectionResult extends ViewRecord
{
    protected static string $resource = DetectionResultResource::class;

    protected ?string $subheading = ReadOnlyPresentation::RESULT_NOTICE;
}
