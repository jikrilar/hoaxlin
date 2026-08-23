<?php

namespace App\Filament\Resources\SubmissionProcessingEvents\Pages;

use App\Filament\Resources\SubmissionProcessingEvents\SubmissionProcessingEventResource;
use Filament\Resources\Pages\ListRecords;

class ListSubmissionProcessingEvents extends ListRecords
{
    protected static string $resource = SubmissionProcessingEventResource::class;
}
