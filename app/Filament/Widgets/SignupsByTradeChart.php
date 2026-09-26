<?php

namespace App\Filament\Widgets;

use App\Enums\StaffPermissionCode;
use App\Enums\Trade;
use App\Filament\Support\PanelWindow;
use App\Models\Agency;
use App\Models\User;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

class SignupsByTradeChart extends ChartWidget
{
    use InteractsWithPageFilters;

    protected static ?int $sort = 2;

    protected ?string $heading = 'Cadastros por ofício';

    protected static bool $isLazy = false;

    public static function canView(): bool
    {
        $user = auth('staff')->user();

        return $user instanceof User
            && $user->hasStaffPermission(StaffPermissionCode::ViewMetrics);
    }

    protected function getType(): string
    {
        return 'bar';
    }

    /**
     * @return array<string, mixed>
     */
    protected function getOptions(): array
    {
        return [
            'scales' => [
                'x' => ['stacked' => true],
                'y' => ['stacked' => true],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getData(): array
    {
        $window = PanelWindow::range(($this->pageFilters ?? [])['period'] ?? null);
        $labels = [];
        $counts = [];
        foreach (Trade::cases() as $trade) {
            $counts[$trade->value] = [];
        }

        for ($day = $window['start']->startOfDay(); $day->lte($window['end']); $day = $day->addDay()) {
            $labels[] = $day->format('d/m');
            $rows = Agency::query()
                ->whereDate('created_at', $day->toDateString())
                ->selectRaw('trade, count(*) as total')
                ->groupBy('trade')
                ->pluck('total', 'trade');
            foreach (Trade::cases() as $trade) {
                $counts[$trade->value][] = (int) ($rows[$trade->value] ?? 0);
            }
        }

        $colors = [
            'cleaning' => '#79B4B0',
            'lawn' => '#9FC089',
            'pool' => '#FFCC3F',
            'garden' => '#2C3848',
            'other' => '#1F2937',
        ];

        $datasets = [];
        foreach (Trade::cases() as $trade) {
            $datasets[] = [
                'label' => $trade->value,
                'data' => $counts[$trade->value],
                'backgroundColor' => $colors[$trade->value],
                'stack' => 'trades',
            ];
        }

        return [
            'datasets' => $datasets,
            'labels' => $labels,
        ];
    }
}
