<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Invoices\CreateInvoice;
use App\Actions\Invoices\MarkInvoicePaid;
use App\Actions\Invoices\ShareInvoice;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\PeriodRequest;
use App\Http\Requests\Api\V1\StoreInvoiceRequest;
use App\Http\Resources\Api\V1\InvoiceResource;
use App\Models\Invoice;
use App\Support\ApiException;
use App\Support\ErrorCodes;
use App\Support\InvoiceShareToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class InvoiceController extends Controller
{
    public function candidates(): JsonResponse
    {
        return response()->json(InvoiceResource::candidates((int) request()->query('client_id')));
    }

    public function store(StoreInvoiceRequest $request, CreateInvoice $create): JsonResponse
    {
        $invoice = $create($request->validated());

        return response()->json(InvoiceResource::created($invoice), 201);
    }

    public function index(PeriodRequest $request): JsonResponse
    {
        $period = $request->validated('period') ?: 'week';

        return response()->json(InvoiceResource::index($period));
    }

    public function show(int $invoice): JsonResponse
    {
        return response()->json(InvoiceResource::show($this->invoice($invoice)));
    }

    public function pdf(int $invoice): Response
    {
        $model = $this->invoice($invoice);
        InvoiceResource::show($model);

        if ($model->pdf_path === null || ! Storage::disk('local')->exists($model->pdf_path)) {
            throw new ApiException(ErrorCodes::INVOICE_PDF_MISSING, 404);
        }

        return response(Storage::disk('local')->get($model->pdf_path), 200, [
            'Content-Type' => 'application/pdf',
        ]);
    }

    public function share(int $invoice, ShareInvoice $share): JsonResponse
    {
        return response()->json($share($this->invoice($invoice)));
    }

    public function paid(int $invoice, MarkInvoicePaid $mark): JsonResponse
    {
        $saved = $mark($this->invoice($invoice));
        $saved->loadMissing('agency');

        return response()->json([
            'id' => $saved->id,
            'status' => $saved->status->value,
            'paid_at' => $saved->paid_at?->timezone($saved->agency->timezone)->toIso8601String(),
        ]);
    }

    public function sharedPdf(string $token): Response
    {
        $invoice = Invoice::query()->find(InvoiceShareToken::invoiceId($token));

        if ($invoice === null || $invoice->pdf_path === null || ! Storage::disk('local')->exists($invoice->pdf_path)) {
            throw new ApiException(ErrorCodes::INVOICE_PDF_MISSING, 404);
        }

        return response(Storage::disk('local')->get($invoice->pdf_path), 200, [
            'Content-Type' => 'application/pdf',
        ]);
    }

    private function invoice(int $id): Invoice
    {
        $invoice = Invoice::query()->find($id);

        if ($invoice === null) {
            throw new ApiException(ErrorCodes::NOT_FOUND, 404);
        }

        return $invoice;
    }
}
