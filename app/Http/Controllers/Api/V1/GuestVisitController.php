<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Marketplace\ClientAccess;
use App\Actions\Marketplace\GuestVisitAccess;
use App\Enums\InvoiceStatus;
use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Visit;
use App\Models\VisitPhoto;
use App\Support\AgencyContext;
use App\Support\ApiException;
use App\Support\ErrorCodes;
use App\Support\InvoiceShareToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GuestVisitController extends Controller
{
    public function show(string $token, GuestVisitAccess $guest): JsonResponse
    {
        return response()->json($guest->show($token));
    }

    public function photo(string $token, int $photo, GuestVisitAccess $guest): StreamedResponse
    {
        $visit = $guest->visit($token);
        $row = VisitPhoto::query()->where('visit_id', $visit->id)->find($photo);

        return $this->file($row?->path);
    }

    public function invoicePdf(string $token, GuestVisitAccess $guest): Response
    {
        $visit = $guest->visit($token);
        $visit->loadMissing('invoiceLine.invoice');
        $invoice = $visit->invoiceLine?->invoice;

        if ($invoice === null || $invoice->status === InvoiceStatus::ToSend || $invoice->pdf_path === null || ! Storage::disk('local')->exists($invoice->pdf_path)) {
            throw new ApiException(ErrorCodes::INVOICE_PDF_MISSING, 404);
        }

        return response(Storage::disk('local')->get($invoice->pdf_path), 200, ['Content-Type' => 'application/pdf']);
    }

    public function review(string $token, Request $request, GuestVisitAccess $guest): JsonResponse
    {
        $data = $request->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
            'comment' => ['nullable', 'string', 'max:500'],
            'email' => ['nullable', 'email', 'max:255'],
        ]);
        $review = $guest->review($token, $data);

        return response()->json(['id' => $review->id, 'rating' => $review->rating], 201);
    }

    public function decline(string $token, GuestVisitAccess $guest): JsonResponse
    {
        $guest->declineInvite($token);

        return response()->json(['ok' => true]);
    }

    public function requestCode(Request $request, GuestVisitAccess $guest): JsonResponse
    {
        $data = $request->validate(['email' => ['required', 'email', 'max:255']]);
        $guest->requestCode($data['email']);

        return response()->json(['ok' => true]);
    }

    public function verify(Request $request, GuestVisitAccess $guest): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'code' => ['required', 'string', 'size:6'],
        ]);

        return response()->json($guest->history($data['email'], $data['code']));
    }

    public function providerLink(int $visit, GuestVisitAccess $guest): JsonResponse
    {
        $membership = AgencyContext::membership();
        $model = Visit::query()->where('agency_id', $membership->agency_id)->find($visit);

        if ($model === null) {
            throw new ApiException(ErrorCodes::NOT_FOUND, 404);
        }

        return response()->json([
            'url' => $guest->url($model),
            'phone' => (string) ($model->client?->phone ?? ''),
        ]);
    }

    public function clientShow(Request $request, int $visit, GuestVisitAccess $guest): JsonResponse
    {
        $model = $this->ownVisit($request, $visit);
        // O payload do convidado já traz fotos, fatura e avaliação da visita.
        $model->guest_token = $model->guest_token ?: 'client';
        $payload = $guest->present($model);
        $payload['photos'] = $model->photos->map(fn (VisitPhoto $photo): array => [
            'id' => $photo->id,
            'url' => rtrim((string) config('app.url'), '/').'/api/v1/client/visits/'.$model->id.'/photos/'.$photo->id,
        ])->values()->all();
        if ($payload['invoice'] !== null) {
            $payload['invoice']['pdf_url'] = rtrim((string) config('app.url'), '/').'/api/v1/invoices/share/'.InvoiceShareToken::issue($payload['invoice']['id']);
        }

        return response()->json($payload);
    }

    public function clientPhoto(Request $request, int $visit, int $photo): StreamedResponse
    {
        $model = $this->ownVisit($request, $visit);
        $row = VisitPhoto::query()->where('visit_id', $model->id)->find($photo);

        return $this->file($row?->path);
    }

    private function ownVisit(Request $request, int $visit): Visit
    {
        $user = $request->user();
        $clientIds = Client::query()->where('user_id', $user->id)->pluck('id');
        $model = Visit::query()->whereIn('client_id', $clientIds)->with(['client', 'agency', 'assignee', 'photos', 'events', 'invoiceLine.invoice'])->find($visit);

        if ($model === null) {
            throw new ApiException(ErrorCodes::NOT_FOUND, 404);
        }

        return $model;
    }

    private function file(mixed $path): StreamedResponse
    {
        if (! is_string($path) || $path === '' || ! Storage::disk('local')->exists($path)) {
            throw new ApiException(ErrorCodes::NOT_FOUND, 404);
        }

        return Storage::disk('local')->response($path);
    }
}
