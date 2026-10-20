<?php

namespace App\Filament\Resources\BillingInvoices\Pages;

use App\Filament\Resources\BillingInvoices\BillingInvoiceResource;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;

class ListBillingInvoices extends ListRecords
{
    protected static string $resource = BillingInvoiceResource::class;

    public function getSubheading(): string|Htmlable|null
    {
        return __('panel.billing_invoices.list_help');
    }
}
