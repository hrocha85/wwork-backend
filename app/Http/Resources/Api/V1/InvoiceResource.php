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
            ->whereDoesntHave('invoiceLine')
            ->orderBy('service_date')
            ->orderBy('id')
            ->get();

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
                'price_pence' => $visit->price_pence,
            ])->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function index(string $period): array
    {
        self::assertOwner();
        [$start, $end] = self::window($period);

        $invoices = Invoice::query()
            ->where('agency_id', AgencyContext::membership()->agency_id)
            ->whereHas('lines', function ($query) use ($start, $end): void {
                $query->whereDate('service_date', '>=', $start->toDateString())
                    ->whereDate('service_date', '<=', $end->toDateString());
            })
            ->with(['client', 'lines'])
            ->orderBy('id')
            ->get();

        return [
            'invoices' => $invoices->map(fn (Invoice $invoice): array => self::listItem($invoice))->values()->all(),
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
    public static function window(string $period): array
    {
        $now = Carbon::now(AgencyContext::membership()->agency->timezone);

        if ($period === 'month') {
            return [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()];
        }

        return [$now->copy()->startOfWeek(Carbon::MONDAY), $now->copy()->endOfWeek(Carbon::SUNDAY)];
    }
}
