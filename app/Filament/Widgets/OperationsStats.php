<?php

namespace App\Filament\Widgets;

use App\Enums\InvoiceStatus;
use App\Enums\StaffPermissionCode;
use App\Enums\VisitStatus;
use App\Filament\Support\PanelLabels;
use App\Filament\Support\PanelWindow;
use App\Models\Agency;
use App\Models\Client;
use App\Models\ClientAccessCode;
use App\Models\Invoice;
use App\Models\Payout;
use App\Models\Review;
use App\Models\User;
use App\Models\Visit;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class OperationsStats extends StatsOverviewWidget
{
    use InteractsWithPageFilters;

    protected static ?int $sort = 2;

    protected static bool $isLazy = false;

    public static function canView(): bool
    {
        $user = auth('staff')->user();

        return $user instanceof User && $user->hasStaffPermission(StaffPermissionCode::ViewMetrics);
    }

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        $window = PanelWindow::range(($this->pageFilters ?? [])['period'] ?? null);
        $start = $window['start'];
        $end = $window['end'];

        $clients = Client::query()->whereBetween('created_at', [$start, $end]);
        $visits = Visit::query()->whereBetween('service_date', [$start->toDateString(), $end->toDateString()]);
        $done = (clone $visits)->where('status', VisitStatus::Done);

        $repeatClients = Client::query()
            ->whereHas('visits', fn ($q) => $q->whereBetween('service_date', [$start->toDateString(), $end->toDateString()]), '>=', 2)
            ->count();

        return [
            Stat::make(__('panel.ops.agencies'), (string) Agency::query()->whereBetween('created_at', [$start, $end])->count())
                ->description(__('panel.ops.agencies_help'))
                ->icon(Heroicon::OutlinedBuildingOffice2)->color('primary'),
            Stat::make(__('panel.ops.clients'), (string) (clone $clients)->count())
                ->description(__('panel.ops.clients_help', [
                    'accounts' => (clone $clients)->whereNotNull('user_id')->count(),
                    'leads' => (clone $clients)->whereNull('user_id')->count(),
                    'email_no_account' => (clone $clients)->whereNotNull('email')->whereNull('user_id')->count(),
                    'phone_no_email' => (clone $clients)->whereNull('email')->whereNotNull('phone')->count(),
                ]))
                ->icon(Heroicon::OutlinedUsers)->color('primary'),
            Stat::make(__('panel.ops.services'), (string) (clone $visits)->count())
                ->description(__('panel.ops.services_help', [
                    'done' => (clone $done)->count(),
                    'cash' => (clone $done)->where('payment_method', 'cash')->count(),
                    'invoice' => (clone $done)->where('payment_method', 'invoice')->count(),
                    'photo' => (clone $done)->has('photos')->count(),
                    'review' => Review::query()->whereBetween('created_at', [$start, $end])->count(),
                ]))
                ->icon(Heroicon::OutlinedCalendarDays)->color('success'),
            Stat::make(__('panel.ops.invoices'), $this->invoiceTotals($start, $end))
                ->description(__('panel.ops.invoices_help', [
                    'to_send' => Invoice::query()->whereBetween('created_at', [$start, $end])->where('status', InvoiceStatus::ToSend)->count(),
                    'sent' => Invoice::query()->whereBetween('created_at', [$start, $end])->where('status', InvoiceStatus::Sent)->count(),
                    'paid' => Invoice::query()->whereBetween('created_at', [$start, $end])->where('status', InvoiceStatus::Paid)->count(),
                ]))
                ->icon(Heroicon::OutlinedDocumentText)->color('success'),
            Stat::make(__('panel.ops.payouts'), $this->payoutTotals())
                ->description(__('panel.ops.payouts_help'))
                ->icon(Heroicon::OutlinedBanknotes)->color('warning'),
            Stat::make(__('panel.ops.funnel'), (string) (clone $clients)->whereNotNull('email')->count())
                ->description(__('panel.ops.funnel_help', [
                    'codes' => ClientAccessCode::query()->whereBetween('created_at', [$start, $end])->count(),
                    'accounts' => Client::query()->whereNotNull('user_id')->whereBetween('updated_at', [$start, $end])->count(),
                    'declined' => Client::query()->whereNotNull('profile_invite_declined_at')->whereBetween('profile_invite_declined_at', [$start, $end])->count(),
                    'repeat' => $repeatClients,
                    'reviews' => Review::query()->whereBetween('created_at', [$start, $end])->count(),
                ]))
                ->icon(Heroicon::OutlinedFunnel)->color('primary'),
        ];
    }

    private function invoiceTotals(mixed $start, mixed $end): string
    {
        $rows = Invoice::query()
            ->whereBetween('invoices.created_at', [$start, $end])
            ->join('agencies', 'agencies.id', '=', 'invoices.agency_id')
            ->selectRaw('agencies.currency as currency, sum(invoices.total_pence) as total')
            ->groupBy('agencies.currency')
            ->get();

        return $rows->isEmpty() ? '—' : $rows->map(fn ($row): string => PanelLabels::money((int) $row->total, (string) $row->currency))->implode(' · ');
    }

    private function payoutTotals(): string
    {
        $rows = Payout::query()
            ->join('agencies', 'agencies.id', '=', 'payouts.agency_id')
            ->selectRaw('agencies.currency as currency, payouts.paid as paid, sum(payouts.amount_pence) as total')
            ->groupBy('agencies.currency', 'payouts.paid')
            ->get();

        return $rows->isEmpty() ? '—' : $rows->map(fn ($row): string => PanelLabels::money((int) $row->total, (string) $row->currency).($row->paid ? ' ✓' : ''))->implode(' · ');
    }
}
