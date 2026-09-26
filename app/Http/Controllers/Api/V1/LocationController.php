<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Location\UpdateMyLocation;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UpdateLocationRequest;
use App\Http\Resources\Api\V1\LocationResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class LocationController extends Controller
{
    public function update(UpdateLocationRequest $request, UpdateMyLocation $update): Response
    {
        /** @var User $user */
        $user = $request->user();

        $update($user, $request->input('lat'), $request->input('lng'));

        return response()->noContent();
    }

    public function index(): JsonResponse
    {
        return response()->json(LocationResource::index());
    }
}
