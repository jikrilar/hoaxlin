<?php

namespace App\Filament\Widgets\Concerns;

use Filament\Facades\Filament;
use Filament\Models\Contracts\FilamentUser;

trait RequiresAdminPanel
{
    public static function canView(): bool
    {
        $panel = Filament::getPanel('admin');
        $user = auth($panel->getAuthGuard())->user();

        return $user instanceof FilamentUser && $user->canAccessPanel($panel);
    }

    public function mountRequiresAdminPanel(): void
    {
        abort_unless(static::canView(), 403);
    }
}
