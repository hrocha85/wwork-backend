<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Auth\LoginUser;
use App\Actions\Auth\LogoutUser;
use App\Actions\Auth\RegisterOwner;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\LoginRequest;
use App\Http\Requests\Api\V1\RegisterRequest;
use App\Http\Resources\Api\V1\AuthSessionResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class AuthController extends Controller
{
    public function register(RegisterRequest $request, RegisterOwner $register): never
    {
        $register($request->validated());
    }

    public function login(LoginRequest $request, LoginUser $login): JsonResponse
    {
        $user = $login(
            $request->string('email')->toString(),
            $request->string('password')->toString(),
        );

        return response()->json(AuthSessionResource::login($user));
    }

    public function logout(LogoutUser $logout): Response
    {
        $logout();

        return response()->noContent();
    }
}
