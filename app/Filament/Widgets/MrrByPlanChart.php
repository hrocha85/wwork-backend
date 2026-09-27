<?php

namespace App\Filament\Widgets;

use App\Enums\PlanCode;
use App\Enums\StaffPermissionCode;
use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use App\Models\User;
use App\Filament\Support\PanelLabels;
use Filament\Widgets\ChartWidget;
use Illuminate\Contracts\Support\Htmlable;

class MrrByPlanChart extends ChartWidget
{
    protected static ?int $sort = 3;

    protected static bool $isLazy = false;

    public function getHeading(): string|Htmlable|null
    {
        return __('panel.charts.mrr');
    }

    public function getDescription(): string|Htmlable|null
    {
        return __('panel.charts.mrr_help');
    }

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
            $labels[] = PanelLabels::plan($plan);
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
                    'backgroundColor' => ['#79B4B0', '#9FC089', '#FFCC3F'],
                ],
            ],
            'labels' => $labels,
        ];
    }
}
