<?php

namespace App\Filament\Widgets;

use App\Enums\StaffPermissionCode;
use App\Models\Activity;
use App\Models\Agency;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class EmptyDashboardStats extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected static bool $isLazy = false;

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
        return [
            Stat::make('Cadastros', (string) Agency::query()->count()),
            Stat::make('Pessoas', (string) User::query()->whereHas('membership')->count()),
            Stat::make('Online', (string) User::query()->where('last_seen_at', '>=', now()->subDay())->count()),
            Stat::make('Atividade', (string) Activity::query()->count()),
        ];
    }
}
