<?php

namespace App\Filament\Resources\SubmissionProcessingEvents\Pages;

use App\Filament\Resources\SubmissionProcessingEvents\SubmissionProcessingEventResource;
use App\Filament\Support\MonitoringPresentation;
use Filament\Resources\Pages\ViewRecord;

class ViewSubmissionProcessingEvent extends ViewRecord
{
    protected static string $resource = SubmissionProcessingEventResource::class;

    protected ?string $subheading = MonitoringPresentation::EVENTS_NOTICE;
}
