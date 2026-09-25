<?php

namespace App\Actions\Invoices;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Policies\InvoicePolicy;
use App\Support\AgencyContext;
use App\Support\ApiException;
use App\Support\ErrorCodes;
use App\Support\RecordActivity;

class MarkInvoicePaid
{
    public function __invoke(Invoice $invoice): Invoice
    {
        $actor = AgencyContext::user();

        if (! app(InvoicePolicy::class)->view($actor, $invoice)) {
            throw new ApiException(ErrorCodes::INVOICE_FORBIDDEN, 403);
        }

        $invoice->status = InvoiceStatus::Paid;
        $invoice->paid_at = now();
        $invoice->save();

        RecordActivity::add($invoice->agency_id, $actor->id, 'invoice.paid');

        return $invoice;
    }
}
