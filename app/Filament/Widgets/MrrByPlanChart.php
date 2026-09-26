<?php

namespace App\Filament\Widgets;

use App\Enums\PlanCode;
use App\Enums\StaffPermissionCode;
use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use App\Models\User;
use Filament\Widgets\ChartWidget;

class MrrByPlanChart extends ChartWidget
{
    protected static ?int $sort = 3;

    protected ?string $heading = 'MRR por plano';

    protected static bool $isLazy = false;

    public static function canView(): bool
    {
        $user = auth('staff')->user();

        return $user instanceof User
            && $user->hasStaffPermission(StaffPermissionCode::ViewRevenue);
    }

    protected function getType(): string
    {
        return 'bar';
    }

    /**
     * @return array<string, mixed>
     */
    protected function getData(): array
    {
        $data = [];
        $labels = [];
        foreach (PlanCode::cases() as $plan) {
            $labels[] = $plan->name;
            $data[] = (int) Subscription::query()
                ->where('status', SubscriptionStatus::Active)
                ->where('plan', $plan)
                ->where('currency', 'GBP')
                ->sum('amount_minor') / 100;
        }

        return [
            'datasets' => [
                [
                    'label' => 'GBP',
                    'data' => $data,
                    'backgroundColor' => '#79B4B0',
                ],
            ],
            'labels' => $labels,
        ];
    }
}
