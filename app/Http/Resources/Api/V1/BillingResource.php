<?php

namespace App\Http\Resources\Api\V1;

use App\Enums\InvoiceStatus;
use App\Enums\MembershipRole;
use App\Models\Invoice;
use App\Models\Membership;
use App\Models\Payout;
use App\Support\AgencyContext;

class BillingResource
{
    /**
     * @return array<string, mixed>
     */
    public static function show(string $period): array
    {
        $membership = AgencyContext::membership();
        [$start, $end] = InvoiceResource::window($period === '' ? 'week' : $period);
        $from = $start->toDateString();
        $to = $end->toDateString();

        if ($membership->role === MembershipRole::Invited) {
            return self::invited($membership, $period === '' ? 'week' : $period, $from, $to);
        }

        return self::owner($membership, $period === '' ? 'week' : $period, $from, $to);
    }

    /**
     * @return array<string, mixed>
     */
    private static function owner(Membership $membership, string $period, string $from, string $to): array
    {
        $invoices = Invoice::query()
            ->where('agency_id', $membership->agency_id)
            ->whereHas('lines', function ($query) use ($from, $to): void {
                $query->whereDate('service_date', '>=', $from)->whereDate('service_date', '<=', $to);
            })
            ->with(['client', 'lines', 'agency'])
            ->orderBy('id')
            ->get();

        $payouts = Payout::query()
            ->where('agency_id', $membership->agency_id)
            ->whereHas('visit', function ($query) use ($from, $to): void {
                $query->whereDate('service_date', '>=', $from)->whereDate('service_date', '<=', $to);
            })
            ->with(['visit.client', 'user'])
            ->orderBy('id')
            ->get();

        $invoiced = (int) $invoices->sum('total_pence');
        $received = (int) $invoices->where('status', InvoiceStatus::Paid)->sum('total_pence');
        $paidOut = (int) $payouts->where('paid', true)->sum('amount_pence');
        $toPay = (int) $payouts->where('paid', false)->sum('amount_pence');

        $rates = Membership::query()
            ->where('agency_id', $membership->agency_id)
            ->where('role', MembershipRole::Invited)
            ->with('user')
            ->orderBy('id')
            ->get();

        return [
            'period' => $period,
            'totals' => [
                'invoiced_pence' => $invoiced,
                'received_pence' => $received,
                'outstanding_pence' => $invoiced - $received,
                'to_pay_pence' => $toPay,
                'paid_out_pence' => $paidOut,
                'net_pence' => $received - $paidOut,
            ],
            'invoices' => $invoices->map(fn (Invoice $invoice): array => [
                'id' => $invoice->id,
                'number' => sprintf('INV-%04d', $invoice->number),
                'client_name' => $invoice->client->name,
                'service_date' => $invoice->lines->min(fn ($line) => $line->service_date->toDateString()),
                'total_pence' => $invoice->total_pence,
                'status' => $invoice->status->value,
                'paid_at' => $invoice->paid_at?->timezone($invoice->agency->timezone)->toIso8601String(),
            ])->values()->all(),
            'payouts' => $payouts->map(fn (Payout $payout): array => [
                'id' => $payout->id,
                'visit_id' => $payout->visit_id,
                'assignee_name' => $payout->user->name,
                'service_date' => $payout->visit->service_date->toDateString(),
                'client_name' => $payout->visit->client->name,
                'rate' => $payout->visit->rate,
                'price_pence' => $payout->visit->price_pence,
                'payout_pence' => $payout->amount_pence,
                'paid' => $payout->paid,
            ])->values()->all(),
            'team_rates' => $rates->map(fn (Membership $row): array => [
                'user_id' => $row->user_id,
                'name' => $row->user->name,
                'rate' => $row->rate,
            ])->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function invited(Membership $membership, string $period, string $from, string $to): array
    {
        $payouts = Payout::query()
            ->where('agency_id', $membership->agency_id)
            ->where('user_id', $membership->user_id)
            ->whereHas('visit', function ($query) use ($from, $to): void {
                $query->whereDate('service_date', '>=', $from)->whereDate('service_date', '<=', $to);
            })
            ->with('visit.client')
            ->orderBy('id')
            ->get();

        $earned = (int) $payouts->sum('amount_pence');
        $received = (int) $payouts->where('paid', true)->sum('amount_pence');

        return [
            'period' => $period,
            'totals' => [
                'earned_pence' => $earned,
                'received_pence' => $received,
                'pending_pence' => $earned - $received,
            ],
            'payouts' => $payouts->map(fn (Payout $payout): array => [
                'id' => $payout->id,
                'visit_id' => $payout->visit_id,
                'service_date' => $payout->visit->service_date->toDateString(),
                'client_name' => $payout->visit->client->name,
                'payout_pence' => $payout->amount_pence,
                'paid' => $payout->paid,
            ])->values()->all(),
        ];
    }
}
