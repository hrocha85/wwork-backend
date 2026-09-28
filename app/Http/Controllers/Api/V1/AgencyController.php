<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Agency\StoreAgencyLogo;
use App\Actions\Agency\UpdateInvoiceDetails;
use App\Actions\Auth\CompleteFirstAccess;
use App\Enums\MembershipRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\CompleteFirstAccessRequest;
use App\Http\Requests\Api\V1\UpdateInvoiceDetailsRequest;
use App\Http\Resources\Api\V1\AuthSessionResource;
use App\Models\User;
use App\Support\AgencyContext;
use App\Support\ApiException;
use App\Support\ErrorCodes;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AgencyController extends Controller
{
    public function update(UpdateInvoiceDetailsRequest $request, UpdateInvoiceDetails $update): JsonResponse
    {
        $update($request->validated());

        /** @var User $user */
        $user = $request->user();

        return response()->json(AuthSessionResource::me($user));
    }

    public function onboarding(CompleteFirstAccessRequest $request, CompleteFirstAccess $complete): JsonResponse
    {
        $user = $complete($request->validated());

        return response()->json(AuthSessionResource::me($user));
    }

    public function logo(Request $request, StoreAgencyLogo $store): JsonResponse
    {
        $store($request->file('logo'));

        /** @var User $user */
        $user = $request->user();

        return response()->json(AuthSessionResource::me($user));
    }

    public function showLogo(): StreamedResponse
    {
        $membership = AgencyContext::membership();

        if ($membership->role !== MembershipRole::Owner) {
            throw new ApiException(ErrorCodes::AGENCY_NOT_OWNER, 403);
        }

        $path = $membership->agency->logo_path;

        if (! filled($path) || ! Storage::disk('local')->exists($path)) {
            throw new ApiException(ErrorCodes::NOT_FOUND, 404);
        }

        return Storage::disk('local')->response($path);
    }
}
