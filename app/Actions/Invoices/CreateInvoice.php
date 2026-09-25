<?php

namespace App\Actions\Invoices;

use App\Enums\InvoiceStatus;
use App\Enums\VisitStatus;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Visit;
use App\Policies\InvoicePolicy;
use App\Services\InvoicePdf;
use App\Support\AgencyContext;
use App\Support\ApiException;
use App\Support\ErrorCodes;
use App\Support\RecordActivity;
use Illuminate\Support\Facades\DB;

class CreateInvoice
{
    public function __construct(private InvoicePdf $pdf) {}

    /**
     * @param  array{client_id: int, visit_ids: array<int, int>}  $input
     */
    public function __invoke(array $input): Invoice
    {
        $actor = AgencyContext::user();
        $membership = AgencyContext::membership();

        if (! app(InvoicePolicy::class)->create($actor)) {
            throw new ApiException(ErrorCodes::INVOICE_FORBIDDEN, 403);
        }

        $ids = array_values(array_unique(array_map('intval', $input['visit_ids'] ?? [])));

        if ($ids === []) {
            throw new ApiException(ErrorCodes::INVOICE_EMPTY, 422);
        }

        $client = Client::query()->find($input['client_id']);

        if ($client === null || $client->agency_id !== $membership->agency_id) {
            throw new ApiException(ErrorCodes::CLIENT_NOT_FOUND, 404);
        }

        $invoice = DB::transaction(function () use ($actor, $membership, $client, $ids): Invoice {
            $visits = Visit::query()->whereIn('id', $ids)->lockForUpdate()->get();

            if ($visits->count() !== count($ids)) {
                throw new ApiException(ErrorCodes::INVOICE_CLIENT_MISMATCH, 422);
            }

            foreach ($visits as $visit) {
                if ($visit->agency_id !== $membership->agency_id || $visit->client_id !== $client->id) {
                    throw new ApiException(ErrorCodes::INVOICE_CLIENT_MISMATCH, 422);
                }
                if ($visit->status !== VisitStatus::Done) {
                    throw new ApiException(ErrorCodes::INVOICE_VISIT_NOT_DONE, 422);
                }
                if ($visit->invoiceLine()->exists()) {
                    throw new ApiException(ErrorCodes::INVOICE_VISIT_ALREADY_INVOICED, 422);
                }
            }

            $number = ((int) Invoice::query()->where('agency_id', $membership->agency_id)->max('number')) + 1;
            $total = (int) $visits->sum('price_pence');

            $invoice = Invoice::query()->create([
                'agency_id' => $membership->agency_id,
                'client_id' => $client->id,
                'created_by' => $actor->id,
                'number' => $number,
                'status' => InvoiceStatus::ToSend,
                'total_pence' => $total,
                'locale' => $actor->locale,
                'invoice_region' => $membership->agency->invoice_region,
            ]);

            foreach ($visits as $visit) {
                $invoice->lines()->create([
                    'visit_id' => $visit->id,
                    'service_date' => $visit->service_date->toDateString(),
                    'description' => (string) $visit->description,
                    'price_pence' => $visit->price_pence,
                ]);
            }

            return $invoice;
        });

        $path = $this->pdf->store($invoice->fresh(['agency', 'client', 'lines']));
        $invoice->pdf_path = $path;
        $invoice->save();

        RecordActivity::add($membership->agency_id, $actor->id, 'invoice.created');

        return $invoice->fresh(['client', 'lines']);
    }
}
