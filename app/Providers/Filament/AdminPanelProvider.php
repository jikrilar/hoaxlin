<?php

namespace App\Providers\Filament;

use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Widgets\ModelVersionWidget;
use App\Filament\Widgets\StatsOverview;
use App\Filament\Widgets\SubmissionTrendChart;
use Filament\FontProviders\LocalFontProvider;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->darkMode(false)
            ->font('Inter', provider: LocalFontProvider::class)
            ->brandName('hoaxlin.id')
            ->login()
            ->renderHook(PanelsRenderHook::AUTH_LOGIN_FORM_BEFORE, fn () => view('filament.admin.auth.runtime-notice'))
            ->renderHook(PanelsRenderHook::PAGE_START, fn () => view('filament.admin.users.runtime-notice'), scopes: [CreateUser::class, EditUser::class])
            ->colors([
                // Filament needs concrete colors to calculate component contrast.
                // Keep these bridges aligned with design-tokens.css; generating a
                // palette from one hex changes the evergreen shades themselves.
                'primary' => [
                    50 => '#ecf3ee',
                    100 => '#dcebe3',
                    200 => '#bed5c7',
                    300 => '#9cbcac',
                    400 => '#6c9482',
                    500 => '#427663',
                    600 => '#235c50',
                    700 => '#19483f',
                    800 => '#123a32',
                    900 => '#102d27',
                    950 => '#102d27',
                ],
                'gray' => [
                    50 => '#f7f6f2',
                    100 => '#f0f2ef',
                    200 => '#dfe3dc',
                    300 => '#7a867f',
                    400 => '#647069',
                    500 => '#647069',
                    600 => '#3d4b46',
                    700 => '#3d4b46',
                    800 => '#1f2b29',
                    900 => '#1f2b29',
                    950 => '#1f2b29',
                ],
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                // Account/logout remain in the native user menu. Keep the
                // dashboard focused on operations, trend, then model context.
                StatsOverview::class,
                SubmissionTrendChart::class,
                ModelVersionWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
