<?php

namespace App\Actions\Invoices;

use App\Enums\InvoiceStatus;
use App\Enums\PaidMethod;
use App\Models\Invoice;
use App\Policies\InvoicePolicy;
use App\Support\AgencyContext;
use App\Support\ApiException;
use App\Support\ErrorCodes;
use App\Support\RecordActivity;

class MarkInvoicePaid
{
    public function __invoke(Invoice $invoice, ?string $paidOn = null, ?string $note = null, bool $fromCheckout = false, ?string $paidMethod = null): Invoice
    {
        $actor = AgencyContext::user();

        if (! $fromCheckout && ! app(InvoicePolicy::class)->view($actor, $invoice)) {
            throw new ApiException(ErrorCodes::INVOICE_FORBIDDEN, 403);
        }

        $invoice->loadMissing('agency');

        $method = null;
        if (filled($paidMethod)) {
            $method = PaidMethod::tryFrom($paidMethod);
            if ($method === null) {
                throw new ApiException(ErrorCodes::INVOICE_PAID_METHOD_INVALID, 422);
            }
            // Pix é meio de pagamento brasileiro: só faz sentido para agência do Brasil.
            if ($method === PaidMethod::Pix && ! self::isBrazil($invoice)) {
                throw new ApiException(ErrorCodes::INVOICE_PIX_ONLY_BRAZIL, 422);
            }
        }

        $invoice->status = InvoiceStatus::Paid;
        if ($method !== null) {
            $invoice->paid_method = $method;
        }
        $invoice->paid_at = filled($paidOn)
            ? \Illuminate\Support\Carbon::parse($paidOn, $invoice->agency->timezone)->startOfDay()
            : now();
        $invoice->paid_note = filled($note) ? $note : null;
        $invoice->save();

        $path = app(\App\Services\InvoicePdf::class)->store($invoice->fresh(['agency', 'client', 'lines']));
        $invoice->pdf_path = $path;
        $invoice->save();

        RecordActivity::add($invoice->agency_id, $actor->id, 'invoice.paid');

        return $invoice;
    }

    public static function isBrazil(Invoice $invoice): bool
    {
        $agency = $invoice->agency;

        return strtoupper((string) ($agency?->currency ?? '')) === 'BRL'
            || strtoupper((string) ($invoice->invoice_region ?? '')) === 'BR'
            || strtoupper((string) ($agency?->invoice_region ?? '')) === 'BR'
            || strtoupper((string) ($agency?->country ?? '')) === 'BR';
    }
}
