<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\RequiresAdminPanel;
use App\Services\Admin\DashboardMetrics;
use App\Services\Admin\ServiceHealth;
use Filament\Widgets\Widget;

/**
 * "Perlu tindakan" (T3.2): failures, stalled work and service health.
 * The retry action itself belongs to T3.4; this widget links to the filtered list.
 */
class NeedsActionWidget extends Widget
{
    use RequiresAdminPanel;

    protected static ?int $sort = 5;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.admin.widgets.needs-action';

    protected function getViewData(): array
    {
        return [
            'action' => app(DashboardMetrics::class)->needsAction(),
            'health' => app(ServiceHealth::class)->all(),
        ];
    }
}
