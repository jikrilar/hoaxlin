<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\RequiresAdminPanel;
use App\Services\Admin\DashboardMetrics;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\Widget;

/**
 * Latency p50/p95 with an explicit sample gate (T3.2).
 * Below `pipeline.latency_minimum_sample` the widget says so instead of drawing
 * a trend from a handful of runs.
 */
class LatencyWidget extends Widget
{
    use InteractsWithPageFilters;
    use RequiresAdminPanel;

    protected static ?int $sort = 30;

    protected static bool $isLazy = false;

    protected string $view = 'filament.admin.widgets.latency';

    protected function getViewData(): array
    {
        $metrics = app(DashboardMetrics::class);
        $days = $metrics->rangeDays($this->pageFilters['range'] ?? null);

        return ['days' => $days, 'latency' => $metrics->breakdown($days)['latency']];
    }
}
