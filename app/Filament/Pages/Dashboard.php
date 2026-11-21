<?php

namespace App\Filament\Pages;

use App\Services\Admin\DashboardMetrics;
use Filament\Forms\Components\Select;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * Dashboard shown as the first item of the Operasional navigation group (T1.6).
 *
 * The 7/30/90-day range lives on the page (URL + session) so every widget reads
 * the same window instead of inventing its own period (T3.2).
 */
class Dashboard extends BaseDashboard
{
    use HasFiltersForm;

    protected static ?string $navigationLabel = 'Periksa';

    protected static ?string $title = 'Dashboard';

    protected static UnitEnum|string|null $navigationGroup = 'Operasional';

    protected static ?int $navigationSort = 10;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedHome;

    public function filtersForm(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('range')
                ->label('Rentang waktu')
                ->options(DashboardMetrics::RANGES)
                ->default(30)
                ->selectablePlaceholder(false)
                ->native(false),
        ]);
    }

    public function getRangeDays(): int
    {
        return app(DashboardMetrics::class)->rangeDays($this->filters['range'] ?? null);
    }
}
