<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Auth\UpdateLocale;
use App\Actions\Team\StoreUserAvatar;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UpdateLocaleRequest;
use App\Http\Resources\Api\V1\AuthSessionResource;
use App\Models\User;
use App\Support\ApiException;
use App\Support\ErrorCodes;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MeController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json(AuthSessionResource::me($user));
    }

    public function update(UpdateLocaleRequest $request, UpdateLocale $updateLocale): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $user = $updateLocale($user, $request->string('locale')->toString());

        return response()->json(AuthSessionResource::profile($user));
    }

    public function avatar(Request $request, StoreUserAvatar $store): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $store($user, $request->file('photo'));

        return response()->json(AuthSessionResource::me($user));
    }

    public function showAvatar(Request $request): StreamedResponse
    {
        /** @var User $user */
        $user = $request->user();
        $path = $user->avatar_path;

        if (! filled($path) || ! Storage::disk('local')->exists($path)) {
            throw new ApiException(ErrorCodes::NOT_FOUND, 404);
        }

        return Storage::disk('local')->response($path);
    }
}
