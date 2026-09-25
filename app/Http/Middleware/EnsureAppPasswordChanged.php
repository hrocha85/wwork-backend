<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\ApiException;
use App\Support\ErrorCodes;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAppPasswordChanged
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && $user->must_change_password) {
            throw new ApiException(ErrorCodes::AUTH_MUST_CHANGE_PASSWORD, 403);
        }

        return $next($request);
    }
}
