<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Enums\PaidMethod;
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

    public function render(Invoice $invoice, ?string $overrideLocale = null): string
    {
        $invoice->loadMissing(['agency.ownerMembership.user', 'client', 'lines']);
        $agency = $invoice->agency;
        $candidateLocale = $overrideLocale
            ?? request()?->query('locale')
            ?? ($invoice->locale?->value ?? (is_string($invoice->locale) ? $invoice->locale : null))
            ?? (auth()->user()?->locale?->value ?? (is_string(auth()->user()?->locale) ? auth()->user()?->locale : null))
            ?? ($agency->ownerMembership?->user?->locale?->value ?? (is_string($agency->ownerMembership?->user?->locale) ? $agency->ownerMembership?->user?->locale : null))
            ?? (request()?->hasHeader('Accept-Language') ? strtolower(substr(request()->header('Accept-Language'), 0, 2)) : null)
            ?? 'pt';
        $locale = in_array($candidateLocale, ['en', 'pt', 'es', 'pl', 'ro'], true) ? $candidateLocale : 'pt';
        $labels = $this->labels($locale);
        $region = (string) ($invoice->invoice_region ?? $agency->invoice_region ?? 'GB');
        $labels['vat'] = $this->taxLabel($region);
        $total = $this->money((int) $invoice->total_pence, (string) $agency->currency, $region);

        $isPaid = $invoice->status === InvoiceStatus::Paid || $invoice->paid_at !== null;
        $paidAt = $invoice->paid_at ? Carbon::parse($invoice->paid_at)->timezone($agency->timezone)->format('d/m/Y') : null;
        $paidMethodKey = $invoice->paid_method?->value ?? null;
        $paidMethodLabel = $paidMethodKey ? ($labels[$paidMethodKey] ?? ucfirst($paidMethodKey)) : null;

        $html = view('invoices.pdf', [
            'locale' => $locale,
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
            'isPaid' => $isPaid,
            'paidAt' => $paidAt,
            'paidMethodLabel' => $paidMethodLabel,
            'paidNote' => $invoice->paid_note,
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
        $catalogs = [
            'en' => [
                'invoice' => 'Invoice',
                'description' => 'Description',
                'amount' => 'Amount',
                'total' => 'Total',
                'subtotal' => 'SUBTOTAL',
                'vat' => 'VAT',
                'bill_to' => 'BILL TO',
                'from' => 'FROM',
                'number' => 'INVOICE NUMBER',
                'date' => 'DATE',
                'due' => 'DUE',
                'amount_due' => 'AMOUNT DUE',
                'on_receipt' => 'On receipt',
                'item' => 'ITEM',
                'notes' => 'HOW TO PAY',
                'bank_transfer' => 'Bank transfer',
                'cash' => 'Cash',
                'card' => 'Card',
                'pix' => 'Pix',
                'other' => 'Other',
                'card_link' => 'Card via link',
                'in_person' => 'In person',
                'paid' => 'PAID',
                'paid_at' => 'PAID ON',
                'status' => 'STATUS',
                'amount_paid' => 'AMOUNT PAID',
                'paid_receipt' => 'PAYMENT RECEIPT',
                'paid_method' => 'Payment method',
                'paid_note' => 'Note',
            ],
            'pt' => [
                'invoice' => 'Fatura',
                'description' => 'Descrição',
                'amount' => 'Valor',
                'total' => 'Total',
                'subtotal' => 'SUBTOTAL',
                'vat' => 'IVA',
                'bill_to' => 'FATURAR A',
                'from' => 'DE',
                'number' => 'NÚMERO DA FATURA',
                'date' => 'DATA',
                'due' => 'VENCIMENTO',
                'amount_due' => 'VALOR A PAGAR',
                'on_receipt' => 'À vista',
                'item' => 'ITEM',
                'notes' => 'COMO PAGAR',
                'bank_transfer' => 'Transferência bancária',
                'cash' => 'Dinheiro',
                'card' => 'Cartão',
                'pix' => 'Pix',
                'other' => 'Outro',
                'card_link' => 'Cartão via link',
                'in_person' => 'Presencial',
                'paid' => 'PAGO',
                'paid_at' => 'PAGO EM',
                'status' => 'STATUS',
                'amount_paid' => 'VALOR PAGO',
                'paid_receipt' => 'COMPROVANTE DE PAGAMENTO',
                'paid_method' => 'Forma de pagamento',
                'paid_note' => 'Observação',
            ],
            'es' => [
                'invoice' => 'Factura',
                'description' => 'Descripción',
                'amount' => 'Importe',
                'total' => 'Total',
                'subtotal' => 'SUBTOTAL',
                'vat' => 'IVA',
                'bill_to' => 'FACTURAR A',
                'from' => 'DE',
                'number' => 'NÚMERO DE FACTURA',
                'date' => 'FECHA',
                'due' => 'VENCIMIENTO',
                'amount_due' => 'IMPORTE A PAGAR',
                'on_receipt' => 'Al contado',
                'item' => 'CONCEPTO',
                'notes' => 'CÓMO PAGAR',
                'bank_transfer' => 'Transferencia bancaria',
                'cash' => 'Efectivo',
                'card' => 'Tarjeta',
                'pix' => 'Pix',
                'other' => 'Otro',
                'card_link' => 'Tarjeta por enlace',
                'in_person' => 'En persona',
                'paid' => 'PAGADO',
                'paid_at' => 'PAGADO EL',
                'status' => 'ESTADO',
                'amount_paid' => 'IMPORTE PAGADO',
                'paid_receipt' => 'JUSTIFICANTE DE PAGO',
                'paid_method' => 'Método de pago',
                'paid_note' => 'Nota',
            ],
            'pl' => [
                'invoice' => 'Faktura',
                'description' => 'Opis',
                'amount' => 'Kwota',
                'total' => 'Total',
                'subtotal' => 'SUMA CZĘŚCIOWA',
                'vat' => 'VAT',
                'bill_to' => 'NABYWCA',
                'from' => 'SPRZEDAWCA',
                'number' => 'NUMER FAKTURY',
                'date' => 'DATA',
                'due' => 'TERMIN PŁATNOŚCI',
                'amount_due' => 'DO ZAPŁATY',
                'on_receipt' => 'W dniu otrzymania',
                'item' => 'POZYCJA',
                'notes' => 'SPOSÓB PŁATNOŚCI',
                'bank_transfer' => 'Przelew bankowy',
                'cash' => 'Gotówka',
                'card' => 'Karta',
                'pix' => 'Pix',
                'other' => 'Inne',
                'card_link' => 'Karta przez link',
                'in_person' => 'Osobiście',
                'paid' => 'OPŁACONA',
                'paid_at' => 'DATA PŁATNOŚCI',
                'status' => 'STATUS',
                'amount_paid' => 'KWOTA ZAPŁACONA',
                'paid_receipt' => 'POTWIERDZENIE PŁATNOŚCI',
                'paid_method' => 'Metoda płatności',
                'paid_note' => 'Uwaga',
            ],
            'ro' => [
                'invoice' => 'Factură',
                'description' => 'Descriere',
                'amount' => 'Sumă',
                'total' => 'Total',
                'subtotal' => 'SUBTOTAL',
                'vat' => 'TVA',
                'bill_to' => 'FACTURAT CĂTRE',
                'from' => 'DE LA',
                'number' => 'NUMĂR FACTURĂ',
                'date' => 'DATA',
                'due' => 'SCADENȚĂ',
                'amount_due' => 'TOTAL DE PLATĂ',
                'on_receipt' => 'La primire',
                'item' => 'ARTICOL',
                'notes' => 'MODALITATE DE PLATĂ',
                'bank_transfer' => 'Transfer bancar',
                'cash' => 'Numerar',
                'card' => 'Card',
                'pix' => 'Pix',
                'other' => 'Altele',
                'card_link' => 'Card prin link',
                'in_person' => 'În persoană',
                'paid' => 'PLĂTIT',
                'paid_at' => 'PLĂTIT LA',
                'status' => 'STATUS',
                'amount_paid' => 'SUMĂ PLĂTITĂ',
                'paid_receipt' => 'DOVADĂ DE PLATĂ',
                'paid_method' => 'Metodă de plată',
                'paid_note' => 'Notă',
            ],
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