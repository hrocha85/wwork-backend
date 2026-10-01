<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Marketplace\ClientAccess;
use App\Actions\Marketplace\SearchAgencies;
use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\User;
use App\Support\ApiException;
use App\Support\ErrorCodes;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class ClientPortalController extends Controller
{
    public function search(Request $request, SearchAgencies $search): JsonResponse
    {
        return response()->json($search(
            $request->string('q')->toString(),
            $request->input('lat'),
            $request->input('lng'),
        ));
    }

    public function preview(string $token, ClientAccess $access): JsonResponse
    {
        return response()->json($access->preview($token));
    }

    public function accept(string $token, Request $request, ClientAccess $access): JsonResponse
    {
        $user = $access->accept($token, $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:80'],
            'password' => ['required', 'string', 'min:8'],
            'email' => ['nullable', 'email', 'max:255'],
        ]));

        return response()->json(['phone' => $user->phone], 201);
    }

    public function link(int $client, ClientAccess $access): JsonResponse
    {
        $row = Client::query()->find($client);

        if ($row === null) {
            abort(404);
        }

        return response()->json($access->issue($row));
    }

    public function home(Request $request, ClientAccess $access): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json($access->home($user));
    }

    public function review(Request $request, ClientAccess $access): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $review = $access->review($user, $request->validate([
            'visit_id' => ['required', 'integer'],
            'rating' => ['required', 'integer', 'between:1,5'],
            'comment' => ['nullable', 'string', 'max:500'],
        ]));

        return response()->json(['id' => $review->id, 'rating' => $review->rating], 201);
    }

    public function avatar(Request $request, ClientAccess $access): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $request->validate(['photo' => ['required', 'file']]);
        $access->avatar($user, $request->file('photo'));

        return response()->json(['ok' => true]);
    }

    public function invoicePdf(Request $request, int $invoice): Response
    {
        /** @var User $user */
        $user = $request->user();

        $clientIds = Client::query()->where('user_id', $user->id)->pluck('id');

        $invoiceModel = Invoice::query()
            ->where('id', $invoice)
            ->whereIn('client_id', $clientIds)
            ->first();

        if ($invoiceModel === null || $invoiceModel->pdf_path === null || ! Storage::disk('local')->exists($invoiceModel->pdf_path)) {
            throw new ApiException(ErrorCodes::INVOICE_PDF_MISSING, 404);
        }

        return response(Storage::disk('local')->get($invoiceModel->pdf_path), 200, [
            'Content-Type' => 'application/pdf',
        ]);
    }
}
