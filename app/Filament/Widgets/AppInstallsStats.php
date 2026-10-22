<?php

namespace App\Filament\Widgets;

use App\Enums\StaffPermissionCode;
use App\Filament\Support\PanelWindow;
use App\Models\InstallClick;
use App\Models\User;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class AppInstallsStats extends StatsOverviewWidget
{
    use InteractsWithPageFilters;

    protected static ?int $sort = 30;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    protected int|array|null $columns = 4;

    public static function canView(): bool
    {
        $user = auth('staff')->user();

        return $user instanceof User
            && $user->hasStaffPermission(StaffPermissionCode::ViewMetrics);
    }

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        $window = PanelWindow::range(($this->pageFilters ?? [])['period'] ?? null);

        $query = InstallClick::query()->whereBetween('clicked_at', [$window['start'], $window['end']]);

        $total = (clone $query)->count();
        $pwa = (clone $query)->where('platform', 'pwa')->count();
        $apple = (clone $query)->where('platform', 'apple')->count();
        $android = (clone $query)->where('platform', 'android')->count();

        return [
            Stat::make(__('panel.installs.total'), (string) $total)
                ->description(__('panel.installs.total_help'))
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('primary'),

            Stat::make(__('panel.installs.pwa'), (string) $pwa)
                ->description(__('panel.installs.pwa_help'))
                ->icon(Heroicon::OutlinedGlobeAlt)
                ->color('success'),

            Stat::make(__('panel.installs.apple'), (string) $apple)
                ->description(__('panel.installs.apple_help'))
                ->icon(Heroicon::OutlinedDevicePhoneMobile)
                ->color('warning'),

            Stat::make(__('panel.installs.android'), (string) $android)
                ->description(__('panel.installs.android_help'))
                ->icon(Heroicon::OutlinedDevicePhoneMobile)
                ->color('success'),
        ];
    }
}
