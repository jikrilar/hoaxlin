<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\RequiresAdminPanel;
use App\Services\Admin\DashboardMetrics;
use Filament\Widgets\Widget;

/** OpenAI spend against the configured monthly quota (T3.2). */
class OpenAiBudgetWidget extends Widget
{
    use RequiresAdminPanel;

    protected static ?int $sort = 35;

    protected static bool $isLazy = false;

    protected string $view = 'filament.admin.widgets.openai-budget';

    protected function getViewData(): array
    {
        return ['budget' => app(DashboardMetrics::class)->openAiBudget()];
    }
}
