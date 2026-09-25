<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Auth\UpdateLocale;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UpdateLocaleRequest;
use App\Http\Resources\Api\V1\AuthSessionResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
}
