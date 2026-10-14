<?php

namespace App\Filament\Resources\Submissions\Pages;

use App\Filament\Resources\Submissions\SubmissionResource;
use Filament\Resources\Pages\ViewRecord;

class ViewSubmission extends ViewRecord
{
    protected static string $resource = SubmissionResource::class;

    protected ?string $subheading = 'Catatan input dan status pipeline. Record classifier parsial tidak ditampilkan sebagai hasil final.';
}
