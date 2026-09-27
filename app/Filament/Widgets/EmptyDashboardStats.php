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
            Stat::make(__('panel.stats.signups'), (string) $total)
                ->description(__('panel.stats.signups_help'))
                ->descriptionIcon(Heroicon::OutlinedBuildingOffice2)
                ->icon(Heroicon::OutlinedBuildingOffice2)
                ->color('primary'),
            Stat::make(__('panel.stats.where'), $top)
                ->description(__('panel.stats.where_help'))
                ->descriptionIcon(Heroicon::OutlinedMapPin)
                ->icon(Heroicon::OutlinedMapPin)
                ->color('primary'),
            Stat::make(__('panel.stats.campaign'), $campaign)
                ->description(__('panel.stats.campaign_help'))
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
            Stat::make(__('panel.stats.plans'), $this->byPlan())
                ->description(__('panel.stats.plans_help'))
                ->descriptionIcon(Heroicon::OutlinedRectangleStack)
                ->icon(Heroicon::OutlinedRectangleStack)
                ->color('primary'),
            Stat::make(__('panel.stats.failures'), (string) Subscription::query()->where('status', SubscriptionStatus::PastDue)->count())
                ->description(__('panel.stats.failures_help'))
                ->descriptionIcon(Heroicon::OutlinedExclamationTriangle)
                ->icon(Heroicon::OutlinedExclamationTriangle)
                ->color('warning'),
        ];

        $user = auth('staff')->user();
        if ($user instanceof User && $user->hasStaffPermission(StaffPermissionCode::ViewRevenue)) {
            array_splice($stats, 6, 0, [
                Stat::make(__('panel.stats.mrr'), $this->mrr())
                    ->description(__('panel.stats.mrr_help'))
                    ->descriptionIcon(Heroicon::OutlinedBanknotes)
                    ->icon(Heroicon::OutlinedBanknotes)
                    ->color('success'),
                Stat::make(__('panel.stats.revenue'), $this->revenue($window['start'], $window['end']))
                    ->description(__('panel.stats.revenue_help'))
                    ->descriptionIcon(Heroicon::OutlinedBanknotes)
                    ->icon(Heroicon::OutlinedBanknotes)
                    ->color('success'),
                Stat::make(__('panel.stats.at_risk'), $this->atRisk())
                    ->description(__('panel.stats.at_risk_help'))
                    ->descriptionIcon(Heroicon::OutlinedExclamationTriangle)
                    ->icon(Heroicon::OutlinedExclamationTriangle)
                    ->color('warning'),
                Stat::make(__('panel.stats.ending'), $this->endingSoon())
                    ->description(__('panel.stats.ending_help'))
                    ->descriptionIcon(Heroicon::OutlinedCalendar)
                    ->icon(Heroicon::OutlinedCalendar)
                    ->color('warning'),
                Stat::make(__('panel.stats.full'), $this->atCeiling())
                    ->description(__('panel.stats.full_help'))
                    ->descriptionIcon(Heroicon::OutlinedUserGroup)
                    ->icon(Heroicon::OutlinedUserGroup)
                    ->color('primary'),
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
        $trade = PanelLabels::trade($row->trade);

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

        $name = $row->campaign === 'Direct' ? __('panel.campaign.direct') : $row->campaign;

        return $name.' '.$row->total;
    }

    private function byPlan(): string
    {
        $parts = [];
        foreach (PlanCode::cases() as $plan) {
            $parts[] = PanelLabels::plan($plan).' '.Subscription::query()->where('plan', $plan)->count();
        }
        $parts[] = __('panel.status.complimentary').' '.Subscription::query()->where('status', SubscriptionStatus::Complimentary)->count();
        $parts[] = __('panel.status.cancelled').' '.Subscription::query()->where('status', SubscriptionStatus::Cancelled)->count();

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

    private function atRisk(): string
    {
        $rows = Subscription::query()
            ->where('status', SubscriptionStatus::PastDue)
            ->selectRaw('currency, sum(amount_minor) as total')
            ->groupBy('currency')
            ->get();

        if ($rows->isEmpty()) {
            return '—';
        }

        return $rows->map(fn ($row): string => PanelLabels::money((int) $row->total, (string) $row->currency))->implode(' · ');
    }

    private function endingSoon(): string
    {
        $soon = now()->addDays(30);

        return (string) Subscription::query()
            ->where(function (Builder $query) use ($soon): void {
                $query->where(fn (Builder $query): Builder => $query->whereNotNull('cancel_at')->where('cancel_at', '<=', $soon))
                    ->orWhere(fn (Builder $query): Builder => $query->whereNotNull('complimentary_until')->where('complimentary_until', '<=', $soon))
                    ->orWhere(fn (Builder $query): Builder => $query->whereNotNull('paid_offline_until')->where('paid_offline_until', '<=', $soon));
            })
            ->count();
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
