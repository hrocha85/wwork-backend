<?php

namespace App\Services;

use App\Models\Invoice;
use Dompdf\Dompdf;
use Illuminate\Support\Facades\Storage;

class InvoicePdf
{
    public function store(Invoice $invoice): string
    {
        $invoice->loadMissing(['agency', 'client', 'lines']);
        $bytes = $this->render($invoice);
        $path = 'invoices/'.$invoice->agency_id.'/'.$invoice->id.'.pdf';
        Storage::disk('local')->put($path, $bytes);

        return $path;
    }

    public function render(Invoice $invoice): string
    {
        $invoice->loadMissing(['agency', 'client', 'lines']);
        $agency = $invoice->agency;
        $locale = $invoice->locale->value;
        $html = view('invoices.pdf', [
            'labels' => $this->labels($locale),
            'agencyName' => $agency->name,
            'region' => $this->regionLabel($agency->invoice_region),
            'legalAddress' => $agency->legal_address,
            'vat' => $agency->vat_registered ? (string) $agency->tax_id : null,
            'number' => sprintf('INV-%04d', $invoice->number),
            'clientName' => $invoice->client->name,
            'clientAddress' => $invoice->client->address,
            'currency' => $agency->currency,
            'lines' => $invoice->lines,
            'total' => number_format($invoice->total_pence / 100, 2, '.', ''),
        ])->render();

        $pdf = new Dompdf;
        $pdf->loadHtml($html);
        $pdf->setPaper('A4');
        $pdf->render();

        return $pdf->output();
    }

    public function regionLabel(string $region): string
    {
        return match ($region) {
            'GB' => 'United Kingdom',
            default => $region,
        };
    }

    /**
     * @return array<string, string>
     */
    private function labels(string $locale): array
    {
        $catalogs = [
            'en' => ['invoice' => 'Invoice', 'description' => 'Description', 'amount' => 'Amount', 'total' => 'Total', 'vat' => 'VAT'],
            'pt' => ['invoice' => 'Fatura', 'description' => 'Descrição', 'amount' => 'Valor', 'total' => 'Total', 'vat' => 'IVA'],
            'pl' => ['invoice' => 'Faktura', 'description' => 'Opis', 'amount' => 'Kwota', 'total' => 'Suma', 'vat' => 'VAT'],
            'ro' => ['invoice' => 'Factură', 'description' => 'Descriere', 'amount' => 'Sumă', 'total' => 'Total', 'vat' => 'TVA'],
            'es' => ['invoice' => 'Factura', 'description' => 'Descripción', 'amount' => 'Importe', 'total' => 'Total', 'vat' => 'IVA'],
        ];

        return $catalogs[$locale] ?? $catalogs['en'];
    }
}
