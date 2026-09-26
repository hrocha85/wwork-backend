<?php

namespace App\Filament\Widgets;

use App\Enums\PlanCode;
use App\Enums\StaffPermissionCode;
use App\Enums\SubscriptionStatus;
use App\Enums\Trade;
use App\Filament\Support\PanelWindow;
use App\Models\Activity;
use App\Models\Agency;
use App\Models\Subscription;
use App\Models\User;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;

class EmptyDashboardStats extends StatsOverviewWidget
{
    use InteractsWithPageFilters;

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
        $window = PanelWindow::range(($this->pageFilters ?? [])['period'] ?? null);
        $created = Agency::query()->whereBetween('created_at', [$window['start'], $window['end']]);
        $total = (clone $created)->count();
        $top = $this->topTrade($created, $total, $window['previousStart'], $window['previousEnd']);
        $campaign = $this->topCampaign(clone $created);

        $stats = [
            Stat::make('Cadastros', (string) $total),
            Stat::make('Onde se inscrevem', $top),
            Stat::make('Por campanha', $campaign),
            Stat::make('Usuários novos', (string) User::query()
                ->whereHas('membership')
                ->whereBetween('created_at', [$window['start'], $window['end']])
                ->count()),
            Stat::make('Online', User::query()->where('last_seen_at', '>=', now()->subDay())->count().' / '.User::query()->where('last_seen_at', '>=', now()->subDays(7))->count()),
            Stat::make('Atividade', (string) Activity::query()->whereBetween('created_at', [$window['start'], $window['end']])->count()),
            Stat::make('Por plano', $this->byPlan()),
            Stat::make('Falhas', (string) Subscription::query()->where('status', SubscriptionStatus::PastDue)->count()),
        ];

        $user = auth('staff')->user();
        if ($user instanceof User && $user->hasStaffPermission(StaffPermissionCode::ViewRevenue)) {
            array_splice($stats, 6, 0, [
                Stat::make('MRR', $this->mrr()),
                Stat::make('Receita no período', $this->revenue($window['start'], $window['end'])),
            ]);
        }

        return $stats;
    }

    private function topTrade(Builder $created, int $total, mixed $previousStart, mixed $previousEnd): string
    {
        $row = (clone $created)
            ->selectRaw('trade, count(*) as total')
            ->groupBy('trade')
            ->orderByDesc('total')
            ->first();

        if ($row === null || $total === 0) {
            return '—';
        }

        $previous = Agency::query()
            ->where('trade', $row->trade)
            ->whereBetween('created_at', [$previousStart, $previousEnd])
            ->count();
        $share = (int) round(((int) $row->total / $total) * 100);
        $trade = $row->trade instanceof Trade ? $row->trade->value : (string) $row->trade;

        return $trade.' '.$row->total.' ('.$share.'%) · '.$previous.' → '.$row->total;
    }

    private function topCampaign(Builder $created): string
    {
        $row = $created
            ->selectRaw("coalesce(nullif(utm_campaign, ''), nullif(utm_source, ''), 'Direct') as campaign, count(*) as total")
            ->groupBy('campaign')
            ->orderByDesc('total')
            ->first();

        if ($row === null) {
            return '—';
        }

        return $row->campaign.' '.$row->total;
    }

    private function byPlan(): string
    {
        $parts = [];
        foreach (PlanCode::cases() as $plan) {
            $parts[] = $plan->name.' '.Subscription::query()->where('plan', $plan)->count();
        }
        $parts[] = 'Cortesia '.Subscription::query()->where('status', SubscriptionStatus::Complimentary)->count();
        $parts[] = 'Canceladas '.Subscription::query()->where('status', SubscriptionStatus::Cancelled)->count();

        return implode(' · ', $parts);
    }

    private function mrr(): string
    {
        $rows = Subscription::query()
            ->where('subscriptions.status', SubscriptionStatus::Active)
            ->join('agencies', 'agencies.id', '=', 'subscriptions.agency_id')
            ->selectRaw('agencies.country as country, subscriptions.currency as currency, sum(subscriptions.amount_minor) as total')
            ->groupBy('agencies.country', 'subscriptions.currency')
            ->get();

        if ($rows->isEmpty()) {
            return '—';
        }

        return $rows->map(fn ($row): string => $row->country.' '.$this->money((int) $row->total, (string) $row->currency))->implode(' · ');
    }

    private function revenue(mixed $start, mixed $end): string
    {
        $rows = Subscription::query()
            ->where('status', SubscriptionStatus::Active)
            ->whereIn('agency_id', Activity::query()
                ->where('action', 'subscription.payment_succeeded')
                ->whereBetween('created_at', [$start, $end])
                ->select('agency_id'))
            ->selectRaw('currency, sum(amount_minor) as total')
            ->groupBy('currency')
            ->get();

        if ($rows->isEmpty()) {
            return '—';
        }

        return $rows->map(fn ($row): string => $this->money((int) $row->total, (string) $row->currency))->implode(' · ');
    }

    private function money(int $minor, string $currency): string
    {
        return number_format($minor / 100, 2, '.', '').' '.$currency;
    }
}
