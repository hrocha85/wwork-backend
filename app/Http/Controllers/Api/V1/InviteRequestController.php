<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\InviteRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InviteRequestController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email:rfc', 'max:255'],
            'locale' => ['nullable', 'string', 'max:10'],
        ]);

        $email = strtolower(trim($validated['email']));

        // 1. Verifica se já é um usuário cadastrado no sistema
        if (User::query()->where('email', $email)->exists()) {
            return response()->json([
                'ok' => true,
                'already_user' => true,
                'message' => 'Este e-mail já possui uma conta no WWork. Acesse a tela de login para entrar.',
            ], 200);
        }

        // 2. Verifica se já solicitou convite anteriormente
        $existing = InviteRequest::query()->where('email', $email)->first();
        if ($existing) {
            return response()->json([
                'ok' => true,
                'already_registered' => true,
                'message' => 'Seu e-mail já está registrado na lista de espera! Avisaremos assim que seu convite estiver liberado.',
            ], 200);
        }

        // 3. Cadastra nova solicitação de convite
        InviteRequest::create([
            'email' => $email,
            'status' => 'pending',
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 500),
            'locale' => $validated['locale'] ?? app()->getLocale(),
        ]);

        return response()->json([
            'ok' => true,
            'already_registered' => false,
            'already_user' => false,
            'message' => 'Solicitação recebida com sucesso! Seu e-mail foi adicionado à nossa lista de espera.',
        ], 201);
    }
}
