<?php

namespace App\Filament\Widgets;

use App\Enums\PlanCode;
use App\Enums\StaffPermissionCode;
use App\Enums\SubscriptionStatus;
use App\Filament\Support\PanelLabels;
use App\Filament\Support\PanelWindow;
use Filament\Support\Icons\Heroicon;
use App\Models\Activity;
use App\Models\Agency;
use App\Models\Subscription;
use App\Models\User;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;

class EmptyDashboardStats extends StatsOverviewWidget
{
    use InteractsWithPageFilters;

    protected static ?int $sort = 1;

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
        $created = Agency::query()->whereBetween('created_at', [$window['start'], $window['end']]);
        $total = (clone $created)->count();
        $top = $this->topTrade($created, $total, $window['previousStart'], $window['previousEnd']);
        $campaign = $this->topCampaign(clone $created);

        $stats = [
            Stat::make(__('panel.stats.signups'), (string) $total)
                ->description(__('panel.stats.signups_help'))
                ->descriptionIcon(Heroicon::OutlinedBuildingOffice2)
                ->icon(Heroicon::OutlinedBuildingOffice2)
                ->color('primary'),
            Stat::make(__('panel.stats.where'), $top['value'])
                ->description($top['description'])
                ->descriptionIcon(Heroicon::OutlinedMapPin)
                ->icon(Heroicon::OutlinedMapPin)
                ->color('primary'),
            Stat::make(__('panel.stats.campaign'), $campaign['value'])
                ->description($campaign['description'])
                ->descriptionIcon(Heroicon::OutlinedMegaphone)
                ->icon(Heroicon::OutlinedMegaphone)
                ->color('primary'),
            Stat::make(__('panel.stats.users'), (string) User::query()
                ->whereHas('membership')
                ->whereBetween('created_at', [$window['start'], $window['end']])
                ->count())
                ->description(__('panel.stats.users_help'))
                ->descriptionIcon(Heroicon::OutlinedUserGroup)
                ->icon(Heroicon::OutlinedUserGroup)
                ->color('success'),
            Stat::make(__('panel.stats.online'), User::query()->where('last_seen_at', '>=', now()->subDay())->count().' / '.User::query()->where('last_seen_at', '>=', now()->subDays(7))->count())
                ->description(__('panel.stats.online_help'))
                ->descriptionIcon(Heroicon::OutlinedSignal)
                ->icon(Heroicon::OutlinedSignal)
                ->color('success'),
            Stat::make(__('panel.stats.activity'), (string) Activity::query()->whereBetween('created_at', [$window['start'], $window['end']])->count())
                ->description(__('panel.stats.activity_help'))
                ->descriptionIcon(Heroicon::OutlinedClipboardDocumentList)
                ->icon(Heroicon::OutlinedClipboardDocumentList)
                ->color('primary'),
        ];

        $user = auth('staff')->user();
        if ($user instanceof User && $user->hasStaffPermission(StaffPermissionCode::ViewRevenue)) {
            $atRiskData = $this->atRisk();
            $planData = $this->byPlan();

            $stats[] = Stat::make(__('panel.stats.mrr'), $this->mrr())
                ->description(__('panel.stats.mrr_help'))
                ->descriptionIcon(Heroicon::OutlinedBanknotes)
                ->icon(Heroicon::OutlinedBanknotes)
                ->color('success');
            $stats[] = Stat::make(__('panel.stats.revenue'), $this->revenue($window['start'], $window['end']))
                ->description(__('panel.stats.revenue_help'))
                ->descriptionIcon(Heroicon::OutlinedBanknotes)
                ->icon(Heroicon::OutlinedBanknotes)
                ->color('success');
            $stats[] = Stat::make(__('panel.stats.at_risk'), $atRiskData['value'])
                ->description($atRiskData['description'])
                ->descriptionIcon(Heroicon::OutlinedExclamationTriangle)
                ->icon(Heroicon::OutlinedExclamationTriangle)
                ->color('warning');
            $stats[] = Stat::make(__('panel.stats.ending'), $this->endingSoon())
                ->description(__('panel.stats.ending_help'))
                ->descriptionIcon(Heroicon::OutlinedCalendar)
                ->icon(Heroicon::OutlinedCalendar)
                ->color('warning');
            $stats[] = Stat::make(__('panel.stats.full'), $this->atCeiling())
                ->description(__('panel.stats.full_help'))
                ->descriptionIcon(Heroicon::OutlinedUserGroup)
                ->icon(Heroicon::OutlinedUserGroup)
                ->color('primary');
            $stats[] = Stat::make(__('panel.stats.plans'), $planData['value'])
                ->description($planData['description'])
                ->descriptionIcon(Heroicon::OutlinedRectangleStack)
                ->icon(Heroicon::OutlinedRectangleStack)
                ->color('primary');
        } else {
            $planData = $this->byPlan();

            $stats[] = Stat::make(__('panel.stats.failures'), (string) Subscription::query()->where('status', SubscriptionStatus::PastDue)->count())
                ->description(__('panel.stats.failures_help'))
                ->descriptionIcon(Heroicon::OutlinedExclamationTriangle)
                ->icon(Heroicon::OutlinedExclamationTriangle)
                ->color('warning');
            $stats[] = Stat::make(__('panel.stats.plans'), $planData['value'])
                ->description($planData['description'])
                ->descriptionIcon(Heroicon::OutlinedRectangleStack)
                ->icon(Heroicon::OutlinedRectangleStack)
                ->color('primary');
        }

        return $stats;
    }

    /**
     * @return array{value: string, description: string}
     */
    private function topTrade(Builder $created, int $total, mixed $previousStart, mixed $previousEnd): array
    {
        $row = (clone $created)
            ->selectRaw('trade, count(*) as total')
            ->groupBy('trade')
            ->orderByDesc('total')
            ->first();

        if ($row === null || $total === 0) {
            return [
                'value' => '—',
                'description' => __('panel.stats.where_help'),
            ];
        }

        $previous = Agency::query()
            ->where('trade', $row->trade)
            ->whereBetween('created_at', [$previousStart, $previousEnd])
            ->count();
        $share = (int) round(((int) $row->total / $total) * 100);
        $trade = PanelLabels::trade($row->trade);

        $arrow = $previous <= (int) $row->total ? '↑' : '↓';

        return [
            'value' => "{$trade} ({$share}%)",
            'description' => "{$row->total} agências · anterior: {$previous} {$arrow} {$row->total}",
        ];
    }

    /**
     * @return array{value: string, description: string}
     */
    private function topCampaign(Builder $created): array
    {
        $row = $created
            ->selectRaw("coalesce(nullif(utm_campaign, ''), nullif(utm_source, ''), 'Direct') as campaign, count(*) as total")
            ->groupBy('campaign')
            ->orderByDesc('total')
            ->first();

        if ($row === null) {
            return [
                'value' => '—',
                'description' => __('panel.stats.campaign_help'),
            ];
        }

        $name = $row->campaign === 'Direct' ? __('panel.campaign.direct') : $row->campaign;

        return [
            'value' => $name,
            'description' => "{$row->total} " . ($row->total === 1 ? 'agência' : 'agências') . ($row->campaign === 'Direct' ? ' · Sem tag UTM' : ''),
        ];
    }

    /**
     * @return array{value: string, description: string}
     */
    private function byPlan(): array
    {
        $activeCount = Subscription::query()->where('status', SubscriptionStatus::Active)->count();
        $parts = [];
        foreach (PlanCode::cases() as $plan) {
            $count = Subscription::query()->where('plan', $plan)->count();
            if ($count > 0) {
                $parts[] = PanelLabels::plan($plan)." {$count}";
            }
        }
        $complimentary = Subscription::query()->where('status', SubscriptionStatus::Complimentary)->count();
        if ($complimentary > 0) {
            $parts[] = __('panel.status.complimentary')." {$complimentary}";
        }
        $cancelled = Subscription::query()->where('status', SubscriptionStatus::Cancelled)->count();
        if ($cancelled > 0) {
            $parts[] = __('panel.status.cancelled')." {$cancelled}";
        }

        $summary = empty($parts) ? __('panel.stats.plans_help') : implode(' · ', $parts);

        return [
            'value' => $activeCount === 1 ? '1 ativa' : "{$activeCount} ativas",
            'description' => $summary,
        ];
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

    /**
     * @return array{value: string, description: string}
     */
    private function atRisk(): array
    {
        $pastDueQuery = Subscription::query()->where('status', SubscriptionStatus::PastDue);
        $count = (clone $pastDueQuery)->count();
        $rows = $pastDueQuery
            ->selectRaw('currency, sum(amount_minor) as total')
            ->groupBy('currency')
            ->get();

        $val = $rows->isEmpty()
            ? '—'
            : $rows->map(fn ($row): string => PanelLabels::money((int) $row->total, (string) $row->currency))->implode(' · ');

        return [
            'value' => $val,
            'description' => $count > 0
                ? "{$count} em atraso · convite suspenso"
                : __('panel.stats.at_risk_help'),
        ];
    }

    private function endingSoon(): string
    {
        try {
            $soon = now()->addDays(30);

            return (string) Subscription::query()
                ->where(function (Builder $query) use ($soon): void {
                    $query->where(fn (Builder $query): Builder => $query->whereNotNull('cancel_at')->where('cancel_at', '<=', $soon))
                        ->orWhere(fn (Builder $query): Builder => $query->whereNotNull('complimentary_until')->where('complimentary_until', '<=', $soon))
                        ->orWhere(fn (Builder $query): Builder => $query->whereNotNull('paid_offline_until')->where('paid_offline_until', '<=', $soon));
                })
                ->count();
        } catch (QueryException $e) {
            Log::critical("Filament Dashboard [endingSoon] falha de consulta / schema: {$e->getMessage()}", [
                'sql' => $e->getSql(),
                'bindings' => $e->getBindings(),
            ]);

            return '⚠️ Erro de schema';
        }
    }

    private function atCeiling(): string
    {
        return (string) Subscription::query()->atCeiling()->count();
    }

    private function money(int $minor, string $currency): string
    {
        return number_format($minor / 100, 2, '.', '').' '.$currency;
    }
}
