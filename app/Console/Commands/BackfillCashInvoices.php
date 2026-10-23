<?php

namespace App\Console\Commands;

use App\Enums\InvoiceStatus;
use App\Enums\VisitStatus;
use App\Models\Invoice;
use App\Models\Visit;
use App\Services\InvoicePdf;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillCashInvoices extends Command
{
    protected $signature = 'billing:backfill-cash-invoices {--dry-run : Só conta, sem gravar}';

    protected $description = 'Cria fatura já paga para cada visita concluída paga em dinheiro e ainda sem linha de fatura.';

    public function handle(InvoicePdf $pdf): int
    {
        $visits = Visit::query()
            ->where('status', VisitStatus::Done)
            ->where('payment_method', 'cash')
            ->where('payment_status', 'paid')
            ->whereDoesntHave('invoiceLines')
            ->with(['agency', 'client'])
            ->orderBy('service_date')
            ->orderBy('id')
            ->get();

        $this->info("Cash visits without invoice: {$visits->count()}");

        if ($this->option('dry-run')) {
            return self::SUCCESS;
        }

        $created = 0;
        foreach ($visits as $visit) {
            $invoice = DB::transaction(function () use ($visit): Invoice {
                $number = ((int) Invoice::withTrashed()->where('agency_id', $visit->agency_id)->max('number')) + 1;
                $invoice = Invoice::query()->create([
                    'agency_id' => $visit->agency_id,
                    'client_id' => $visit->client_id,
                    'created_by' => $visit->assignee_id,
                    'number' => $number,
                    'status' => InvoiceStatus::Paid,
                    'total_pence' => (int) $visit->price_pence,
                    'locale' => $visit->agency->locale ?? 'en',
                    'invoice_region' => $visit->agency->invoice_region,
                    'paid_at' => $visit->service_date->copy()->startOfDay(),
                    'paid_method' => 'cash',
                ]);
                $invoice->lines()->create([
                    'visit_id' => $visit->id,
                    'service_date' => $visit->service_date->toDateString(),
                    'description' => (string) $visit->description,
                    'price_pence' => (int) $visit->price_pence,
                ]);

                return $invoice;
            });

            $invoice->pdf_path = $pdf->store($invoice->fresh(['agency', 'client', 'lines']));
            $invoice->save();
            $created++;
        }

        $this->info("Paid invoices created: {$created}");

        return self::SUCCESS;
    }
}
