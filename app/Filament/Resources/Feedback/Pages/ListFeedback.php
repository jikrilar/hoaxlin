<?php

namespace App\Filament\Resources\Feedback\Pages;

use App\Filament\Resources\Feedback\FeedbackResource;
use App\Filament\Support\ReadOnlyPresentation;
use Filament\Resources\Pages\ListRecords;

class ListFeedback extends ListRecords
{
    protected static string $resource = FeedbackResource::class;

    protected ?string $subheading = ReadOnlyPresentation::FEEDBACK_NOTICE;
}
