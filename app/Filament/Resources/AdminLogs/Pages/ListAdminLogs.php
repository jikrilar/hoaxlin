<?php

namespace App\Filament\Resources\AdminLogs\Pages;

use App\Filament\Resources\AdminLogs\AdminLogResource;
use App\Filament\Support\MonitoringPresentation;
use Filament\Resources\Pages\ListRecords;

class ListAdminLogs extends ListRecords
{
    protected static string $resource = AdminLogResource::class;

    protected ?string $subheading = MonitoringPresentation::LOGS_NOTICE;
}
