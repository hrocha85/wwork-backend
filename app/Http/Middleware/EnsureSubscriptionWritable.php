<?php

namespace App\Http\Middleware;

use App\Enums\MembershipRole;
use App\Enums\SubscriptionStatus;
use App\Support\AgencyContext;
use App\Support\ApiException;
use App\Support\ErrorCodes;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSubscriptionWritable
{
    public function handle(Request $request, Closure $next): Response
    {
        $membership = AgencyContext::membership();
        $agency = $membership->agency;
        $subscription = $agency->subscription;
        $path = $request->path();

        // 1. Se colaborador e a agência está no modo gratuito (sem plano pago ou inadimplente), bloquear acesso
        if ($membership->role === MembershipRole::Invited && $agency->isFreeMode()) {
            throw new ApiException(ErrorCodes::TEAM_ACCESS_SUSPENDED, 402);
        }

        if ($request->isMethod('GET') || $request->isMethod('HEAD')) {
            return $next($request);
        }

        // 2. Modo gratuito: bloquear agendamentos, faturas, equipe e bloqueios de agenda
        if ($agency->isFreeMode() && $this->blocksWhenFreeMode($request, $path)) {
            throw new ApiException(ErrorCodes::PLAN_UPGRADE_REQUIRED, 402);
        }

        if ($subscription?->status === SubscriptionStatus::PastDue) {
            if (str_contains($path, '/partners') || str_contains($path, '/subscription/annual')) {
                throw new ApiException(ErrorCodes::SUBSCRIPTION_PAST_DUE, 402);
            }
        }

        if ($subscription?->status === SubscriptionStatus::Cancelled && $this->blocksWhenCancelled($path)) {
            throw new ApiException(ErrorCodes::SUBSCRIPTION_INACTIVE, 402);
        }

        return $next($request);
    }

    private function blocksWhenFreeMode(Request $request, string $path): bool
    {
        if ($request->isMethod('POST')) {
            // Nota: clientes (POST api/v1/clients) permanecem 100% liberados!
            if (preg_match('#^api/v1/visits$#', $path) === 1) {
                return true;
            }
            if (preg_match('#^api/v1/invoices$#', $path) === 1) {
                return true;
            }
            if (preg_match('#^api/v1/partners$#', $path) === 1) {
                return true;
            }
            if (preg_match('#^api/v1/agenda/blocks$#', $path) === 1) {
                return true;
            }
        }

        return false;
    }

    private function blocksWhenCancelled(string $path): bool
    {
        foreach (['/visits', '/invoices', '/partners', '/payouts', '/invoice-candidates', '/agenda/blocks', '/booking'] as $piece) {
            if (str_contains($path, $piece)) {
                return true;
            }
        }

        return false;
    }
}
