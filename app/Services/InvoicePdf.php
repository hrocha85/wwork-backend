<?php

namespace App\Services;

use App\Enums\PaymentMethod;
use App\Enums\PayBy;
use App\Models\Invoice;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Carbon;
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
        $invoice->loadMissing(['agency.ownerMembership.user', 'client', 'lines']);
        $agency = $invoice->agency;
        $locale = $invoice->locale->value;
        $labels = $this->labels($locale);
        $region = (string) ($invoice->invoice_region ?? $agency->invoice_region ?? 'GB');
        $labels['vat'] = $this->taxLabel($region);
        $total = $this->money((int) $invoice->total_pence, (string) $agency->currency, $region);
        $html = view('invoices.pdf', [
            'labels' => $labels,
            'title' => mb_strtoupper($labels['invoice']),
            'agencyName' => $agency->name,
            'region' => $this->regionLabel($region),
            'legalAddress' => $agency->legal_address,
            'phone' => $agency->phone,
            'email' => $agency->ownerMembership?->user?->email,
            'vat' => $agency->vat_registered ? (string) $agency->tax_id : null,
            'paymentLabel' => $this->paymentLabel($agency->payment_method, $labels),
            'paymentDetails' => $agency->payment_details,
            'invoicePayBy' => $invoice->pay_by,
            'invoicePayLink' => $invoice->pay_link,
            'invoicePaymentLabel' => $this->invoicePaymentLabel($invoice->pay_by, $invoice->pay_link, $labels),
            'logo' => $this->logoData($agency->logo_path),
            'initial' => mb_strtoupper(mb_substr($agency->name, 0, 1)),
            'number' => sprintf('INV-%04d', $invoice->number),
            'issued' => Carbon::parse($invoice->created_at)->timezone($agency->timezone)->format('d/m/Y'),
            'clientName' => $invoice->client->name,
            'clientAddress' => $invoice->client->address,
            'lines' => $invoice->lines,
            'total' => $total,
            'money' => fn (int $pence): string => $this->money($pence, (string) $agency->currency, $region),
        ])->render();

        $options = new Options;
        $options->set('isRemoteEnabled', false);
        $pdf = new Dompdf($options);
        $pdf->loadHtml($html);
        $pdf->setPaper('A4');
        $pdf->render();

        return $pdf->output();
    }

    public function regionLabel(string $region): string
    {
        return match ($region) {
            'BR' => 'Brasil',
            'GB' => 'United Kingdom',
            'EU' => 'Europa',
            'US' => 'United States',
            'PL' => 'Polska',
            'RO' => 'România',
            default => $region,
        };
    }

    public function taxLabel(string $region): string
    {
        return match ($region) {
            'BR' => 'CNPJ',
            'GB' => 'VAT',
            'EU' => 'IVA',
            'US' => 'Tax ID',
            'PL' => 'NIP',
            'RO' => 'CIF',
            default => 'VAT',
        };
    }

    private function money(int $pence, string $currency, string $region): string
    {
        $symbol = match (strtoupper($currency)) {
            'BRL' => 'R$',
            'GBP' => '£',
            'EUR' => '€',
            'USD' => '$',
            'PLN' => 'zł',
            'RON' => 'lei',
            default => $currency,
        };
        $european = in_array($region, ['BR', 'EU', 'PL', 'RO'], true);

        return $symbol.' '.number_format($pence / 100, 2, $european ? ',' : '.', $european ? '.' : ',');
    }

    /**
     * @return array<string, string>
     */
    private function labels(string $locale): array
    {
        $shared = [
            'bill_to' => 'BILL TO',
            'from' => 'FROM',
            'number' => 'INVOICE NUMBER',
            'date' => 'DATE',
            'due' => 'DUE',
            'amount_due' => 'AMOUNT DUE',
            'on_receipt' => 'On receipt',
            'item' => 'ITEM',
            'notes' => 'HOW TO PAY',
            'subtotal' => 'SUBTOTAL',
            'bank_transfer' => 'Bank transfer',
            'cash' => 'Cash',
            'other' => 'Other',
            'card_link' => 'Card via link',
            'in_person' => 'In person',
        ];
        $catalogs = [
            'en' => ['invoice' => 'Invoice', 'description' => 'Description', 'amount' => 'Amount', 'total' => 'Total', 'vat' => 'VAT'] + $shared,
            'pt' => ['invoice' => 'Fatura', 'description' => 'Descrição', 'amount' => 'Valor', 'total' => 'Total', 'vat' => 'IVA'] + $shared,
            'pl' => ['invoice' => 'Faktura', 'description' => 'Opis', 'amount' => 'Kwota', 'total' => 'Suma', 'vat' => 'VAT'] + $shared,
            'ro' => ['invoice' => 'Factură', 'description' => 'Descriere', 'amount' => 'Sumă', 'total' => 'Total', 'vat' => 'TVA'] + $shared,
            'es' => ['invoice' => 'Factura', 'description' => 'Descripción', 'amount' => 'Importe', 'total' => 'Total', 'vat' => 'IVA'] + $shared,
        ];

        return $catalogs[$locale] ?? $catalogs['en'];
    }

    /**
     * @param  array<string, string>  $labels
     */
    private function paymentLabel(?string $method, array $labels): ?string
    {
        $payment = PaymentMethod::tryFrom((string) $method);

        if ($payment === null) {
            return null;
        }

        return $labels[$payment->value] ?? null;
    }

    /**
     * @param  array<string, string>  $labels
     */
    private function invoicePaymentLabel(?string $payBy, ?string $payLink, array $labels): ?string
    {
        $method = PayBy::tryFrom((string) $payBy);

        if ($method === null) {
            return null;
        }

        $label = $labels[$method->value] ?? null;

        if ($method === PayBy::Link && filled($payLink)) {
            return $label.': '.$payLink;
        }

        return $label;
    }

    private function logoData(?string $path): ?string
    {
        if (! filled($path) || ! Storage::disk('local')->exists($path)) {
            return null;
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $mime = $extension === 'png' ? 'image/png' : 'image/jpeg';

        return 'data:'.$mime.';base64,'.base64_encode(Storage::disk('local')->get($path));
    }
}
