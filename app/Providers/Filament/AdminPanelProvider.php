<?php

namespace App\Providers\Filament;

use App\Filament\Auth\Login;
use App\Filament\Auth\LoginResponse;
use App\Http\Middleware\RequireStaffPasswordChange;
use Filament\Auth\Http\Responses\Contracts\LoginResponse as LoginResponseContract;
use Filament\Enums\ThemeMode;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        $this->app->bind(LoginResponseContract::class, LoginResponse::class);

        return $panel
            ->default()
            ->id('admin')
            ->path('')
            ->login(Login::class)
            ->loginRouteSlug('/')
            ->profile(null)
            ->authGuard('staff')
            ->brandName('WWork')
            ->defaultThemeMode(ThemeMode::Light)
            ->colors([
                'primary' => Color::hex('#79B4B0'),
                'success' => Color::hex('#9FC089'),
                'warning' => Color::hex('#FFCC3F'),
            ])
            ->bootUsing(function (): void {
                FilamentView::registerRenderHook(
                    PanelsRenderHook::STYLES_AFTER,
                    fn (): string => view('filament.wwork-theme')->render(),
                );
            })
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
                RequireStaffPasswordChange::class,
            ]);
    }
}
