<?php

namespace App\Actions\Invoices;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Policies\InvoicePolicy;
use App\Support\AgencyContext;
use App\Support\ApiException;
use App\Support\ErrorCodes;
use App\Support\InvoiceShareToken;
use App\Support\RecordActivity;

class ShareInvoice
{
    /**
     * @return array{pdf_url: string, text: string}
     */
    public function __invoke(Invoice $invoice): array
    {
        $actor = AgencyContext::user();

        if (! app(InvoicePolicy::class)->view($actor, $invoice)) {
            throw new ApiException(ErrorCodes::INVOICE_FORBIDDEN, 403);
        }

        if ($invoice->status === InvoiceStatus::ToSend) {
            $invoice->status = InvoiceStatus::Sent;
            $invoice->sent_at = now();
            $invoice->save();
        }

        $invoice->loadMissing('agency');
        $url = rtrim((string) config('app.url'), '/').'/api/v1/invoices/share/'.InvoiceShareToken::issue($invoice->id);

        RecordActivity::add($invoice->agency_id, $actor->id, 'invoice.shared');

        return [
            'pdf_url' => $url,
            'text' => 'Your invoice from '.$invoice->agency->name.' is ready. View it here: '.$url,
        ];
    }
}
