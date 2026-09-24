<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\LoginRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Sessão em cookie, não token.
 *
 * O PWA pede GET /sanctum/csrf-cookie, faz o POST /login e o navegador passa a
 * mandar o cookie sozinho. Nada vai para o localStorage.
 */
class AuthController extends Controller
{
    public function login(LoginRequest $request): UserResource
    {
        if (! Auth::attempt($request->only('email', 'password'))) {
            // Código estável: a frase na tela sai do catálogo do PWA.
            throw ValidationException::withMessages([
                'email' => 'auth.invalid_credentials',
            ]);
        }

        $request->session()->regenerate();

        $user = $request->user();
        $user->touchLastSeen();

        return new UserResource($user->refresh());
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['status' => 'logged_out']);
    }

    public function me(Request $request): UserResource
    {
        return new UserResource($request->user());
    }
}
