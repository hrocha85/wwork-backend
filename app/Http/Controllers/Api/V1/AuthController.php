<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Auth\IssueAuthTokens;
use App\Actions\Auth\LoginUser;
use App\Actions\Auth\LogoutUser;
use App\Actions\Auth\RefreshAuthTokens;
use App\Actions\Auth\RegisterOwner;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\LoginRequest;
use App\Http\Requests\Api\V1\RefreshTokenRequest;
use App\Http\Requests\Api\V1\RegisterRequest;
use App\Http\Resources\Api\V1\AuthSessionResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class AuthController extends Controller
{
    public function register(RegisterRequest $request, RegisterOwner $register): JsonResponse
    {
        $user = $register($request->validated());

        return response()->json(AuthSessionResource::registered($user), 201);
    }

    public function login(LoginRequest $request, LoginUser $signIn): JsonResponse
    {
        $identifier = $request->filled('login')
            ? $request->string('login')->toString()
            : $request->string('email')->toString();
        $user = $signIn(
            $identifier,
            $request->string('password')->toString(),
        );

        return response()->json([
            ...AuthSessionResource::login($user),
            ...app(IssueAuthTokens::class)($user),
        ]);
    }

    public function session(IssueAuthTokens $issue): JsonResponse
    {
        /** @var User $user */
        $user = request()->user();

        return response()->json($issue($user));
    }

    public function refresh(RefreshTokenRequest $request, RefreshAuthTokens $refresh): JsonResponse
    {
        return response()->json($refresh($request->string('refresh_token')->toString(), app(IssueAuthTokens::class)));
    }

    public function logout(LogoutUser $logout): Response
    {
        $logout();

        return response()->noContent();
    }
}
