<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\RequiresAdminPanel;
use App\Services\Admin\DashboardMetrics;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\Widget;

/** KPI cards for the selected range, with a 7-day sparkline (T3.2). */
class KpiOverview extends Widget
{
    use InteractsWithPageFilters;
    use RequiresAdminPanel;

    protected static ?int $sort = 10;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.admin.widgets.kpi';

    protected function getViewData(): array
    {
        $days = app(DashboardMetrics::class)->rangeDays($this->pageFilters['range'] ?? null);

        return [
            'days' => $days,
            'kpis' => app(DashboardMetrics::class)->kpis($days),
        ];
    }
}
