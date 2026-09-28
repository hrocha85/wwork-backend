<?php

namespace App\Providers\Filament;

use App\Enums\Locale;
use App\Filament\Auth\Login;
use App\Filament\Auth\LoginResponse;
use App\Filament\Pages\ChangePassword;
use App\Filament\Pages\Dashboard;
use App\Http\Middleware\SetPanelLocale;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Auth\Http\Responses\Contracts\LoginResponse as LoginResponseContract;
use Filament\Enums\ThemeMode;
use Filament\Forms\Components\Select;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Facades\FilamentView;
use Filament\Support\Icons\Heroicon;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Cookie;
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
            ->font('Inter')
            ->defaultThemeMode(ThemeMode::Light)
            ->colors([
                'primary' => Color::hex('#79B4B0'),
                'success' => Color::hex('#9FC089'),
                'warning' => Color::hex('#FFCC3F'),
            ])
            ->userMenuItems([
                'language' => Action::make('language')
                    ->label(fn (): string => __('panel.account.language'))
                    ->icon(Heroicon::OutlinedCog6Tooth)
                    ->schema([
                        Select::make('locale')
                            ->label(__('panel.account.language'))
                            ->helperText(__('panel.account.language_help'))
                            ->options(collect(Locale::cases())->mapWithKeys(
                                fn (Locale $locale): array => [$locale->value => __('panel.locale.'.$locale->value)],
                            )->all())
                            ->native(false)
                            ->required(),
                    ])
                    ->fillForm(function (): array {
                        $user = auth('staff')->user();

                        return [
                            'locale' => $user instanceof User ? $user->locale->value : app()->getLocale(),
                        ];
                    })
                    ->action(function (array $data): void {
                        $locale = Locale::from($data['locale']);
                        $user = auth('staff')->user();
                        if ($user instanceof User) {
                            $user->locale = $locale;
                            $user->save();
                        }
                        session(['panel_locale' => $locale->value]);
                        Cookie::queue(cookie(SetPanelLocale::COOKIE, $locale->value, 60 * 24 * 400));
                        app()->setLocale($locale->value);
                    })
                    ->successRedirectUrl(function (): string {
                        $referer = request()->headers->get('referer');

                        return is_string($referer) && $referer !== '' ? $referer : Dashboard::getUrl();
                    }),
                'password' => Action::make('changePassword')
                    ->label(fn (): string => __('panel.account.password'))
                    ->icon(Heroicon::OutlinedKey)
                    ->url(fn (): string => ChangePassword::getUrl()),
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
                SetPanelLocale::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
