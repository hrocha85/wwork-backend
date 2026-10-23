<?php

namespace App\Http\Resources\Api\V1;

use App\Enums\MembershipRole;
use App\Enums\VisitStatus;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Policies\InvoicePolicy;
use App\Support\AgencyContext;
use App\Support\ApiException;
use App\Support\ErrorCodes;
use App\Support\InvoiceShareToken;
use Illuminate\Support\Carbon;

class InvoiceResource
{
    /**
     * @return array<string, mixed>
     */
    public static function candidates(int $clientId): array
    {
        $actor = AgencyContext::user();

        if (! app(InvoicePolicy::class)->create($actor)) {
            throw new ApiException(ErrorCodes::INVOICE_FORBIDDEN, 403);
        }

        $membership = AgencyContext::membership();
        $client = Client::query()->find($clientId);

        if ($client === null || $client->agency_id !== $membership->agency_id) {
            throw new ApiException(ErrorCodes::CLIENT_NOT_FOUND, 404);
        }

        $visits = $client->visits()
            ->where('status', VisitStatus::Done)
            ->withSum('invoiceLines as invoiced_pence', 'price_pence')
            ->orderBy('service_date')
            ->orderBy('id')
            ->get()
            ->filter(fn ($visit): bool => (int) $visit->price_pence - (int) $visit->invoiced_pence > 0);

        return [
            'client' => [
                'id' => $client->id,
                'name' => $client->name,
                'address' => $client->address,
            ],
            'visits' => $visits->map(fn ($visit): array => [
                'id' => $visit->id,
                'date' => $visit->service_date->toDateString(),
                'description' => $visit->description,
                'price_pence' => (int) $visit->price_pence - (int) $visit->invoiced_pence,
                'full_pence' => (int) $visit->price_pence,
            ])->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function index(string $period, ?int $clientId = null, ?string $month = null): array
    {
        self::assertOwner();
        [$start, $end] = self::window($period, $month);

        $query = Invoice::query()
            ->where('agency_id', AgencyContext::membership()->agency_id)
            ->whereHas('lines', function ($query) use ($start, $end): void {
                $query->whereDate('service_date', '>=', $start->toDateString())
                    ->whereDate('service_date', '<=', $end->toDateString());
            });

        if ($clientId !== null) {
            $query->where('client_id', $clientId);
        }

        $invoices = $query
            ->with(['client', 'lines'])
            ->orderBy('id')
            ->get();

        $from = $start->toDateString();
        $to = $end->toDateString();

        return [
            'invoices' => $invoices->map(function (Invoice $invoice) use ($from, $to): array {
                $item = self::listItem($invoice);
                $lines = $invoice->lines->filter(
                    fn (InvoiceLine $line) => $line->service_date->toDateString() >= $from && $line->service_date->toDateString() <= $to,
                );
                $item['total_pence'] = (int) $lines->sum('price_pence');
                $item['service_date'] = $lines->min(fn (InvoiceLine $line) => $line->service_date->toDateString());

                return $item;
            })->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function show(Invoice $invoice): array
    {
        if (! app(InvoicePolicy::class)->view(AgencyContext::user(), $invoice)) {
            throw new ApiException(ErrorCodes::INVOICE_FORBIDDEN, 403);
        }

        $invoice->loadMissing(['client', 'lines', 'agency']);

        return self::detail($invoice);
    }

    /**
     * @return array<string, mixed>
     */
    public static function created(Invoice $invoice): array
    {
        $invoice->loadMissing(['client', 'lines']);
        $body = self::detail($invoice);
        $body['pdf_url'] = null;

        return $body;
    }

    /**
     * @return array<string, mixed>
     */
    private static function detail(Invoice $invoice): array
    {
        $timezone = $invoice->agency?->timezone ?? AgencyContext::membership()->agency->timezone;

        return [
            'id' => $invoice->id,
            'number' => sprintf('INV-%04d', $invoice->number),
            'client' => [
                'id' => $invoice->client->id,
                'name' => $invoice->client->name,
                'address' => $invoice->client->address,
            ],
            'status' => $invoice->status->value,
            'total_pence' => $invoice->total_pence,
            'locale' => $invoice->locale->value,
            'sent_at' => $invoice->sent_at?->timezone($timezone)->toIso8601String(),
            'paid_at' => $invoice->paid_at?->timezone($timezone)->toIso8601String(),
            'paid_note' => $invoice->paid_note,
            'paid_method' => $invoice->paid_method?->value,
            'pay_by' => $invoice->pay_by?->value,
            'pay_link' => $invoice->pay_link,
            'pdf_url' => $invoice->pdf_path ? rtrim((string) config('app.url'), '/').'/api/v1/invoices/share/'.InvoiceShareToken::issue($invoice->id) : null,
            'lines' => $invoice->lines->map(fn (InvoiceLine $line): array => [
                'id' => $line->id,
                'visit_id' => $line->visit_id,
                'date' => $line->service_date->toDateString(),
                'description' => $line->description,
                'price_pence' => $line->price_pence,
            ])->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function listItem(Invoice $invoice): array
    {
        $timezone = $invoice->agency?->timezone ?? AgencyContext::membership()->agency->timezone;
        $date = $invoice->lines->min(fn (InvoiceLine $line) => $line->service_date->toDateString());

        return [
            'id' => $invoice->id,
            'number' => sprintf('INV-%04d', $invoice->number),
            'client_name' => $invoice->client->name,
            'service_date' => $date,
            'total_pence' => $invoice->total_pence,
            'status' => $invoice->status->value,
            'sent_at' => $invoice->sent_at?->timezone($timezone)->toIso8601String(),
            'paid_at' => $invoice->paid_at?->timezone($timezone)->toIso8601String(),
            'paid_note' => $invoice->paid_note,
            'paid_method' => $invoice->paid_method?->value,
        ];
    }

    private static function assertOwner(): void
    {
        if (AgencyContext::membership()->role !== MembershipRole::Owner) {
            throw new ApiException(ErrorCodes::INVOICE_FORBIDDEN, 403);
        }
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    public static function window(string $period, ?string $month = null): array
    {
        $timezone = AgencyContext::membership()->agency->timezone;
        $now = Carbon::now($timezone);

        if ($period === 'month') {
            if (filled($month)) {
                $start = Carbon::createFromFormat('!Y-m', $month, $timezone)->startOfMonth();
                if ($start->gt($now->copy()->startOfMonth())) {
                    throw new ApiException('billing.month_future', 422);
                }

                return [$start, $start->copy()->endOfMonth()];
            }

            return [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()];
        }

        return [$now->copy()->startOfWeek(Carbon::MONDAY), $now->copy()->endOfWeek(Carbon::SUNDAY)];
    }
}
