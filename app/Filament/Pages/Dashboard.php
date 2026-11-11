<?php

namespace App\Filament\Pages;

use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/** Dashboard shown as the first item of the Operasional navigation group (T1.6). */
class Dashboard extends BaseDashboard
{
    protected static ?string $navigationLabel = 'Periksa';

    protected static ?string $title = 'Dashboard';

    protected static UnitEnum|string|null $navigationGroup = 'Operasional';

    protected static ?int $navigationSort = 10;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedHome;
}
