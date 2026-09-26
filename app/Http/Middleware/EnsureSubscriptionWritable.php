<?php

namespace App\Http\Middleware;

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
        if ($request->isMethod('GET') || $request->isMethod('HEAD')) {
            return $next($request);
        }

        $subscription = AgencyContext::membership()->agency->subscription;
        $path = $request->path();

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

    private function blocksWhenCancelled(string $path): bool
    {
        foreach (['/clients', '/visits', '/invoices', '/partners', '/payouts', '/invoice-candidates'] as $piece) {
            if (str_contains($path, $piece)) {
                return true;
            }
        }

        return false;
    }
}
