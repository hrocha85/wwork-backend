<?php

namespace App\Http\Middleware;

use App\Filament\Pages\ChangePassword;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireStaffPasswordChange
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user('staff');

        if ($user === null || ! $user->must_change_password) {
            return $next($request);
        }

        if ($request->routeIs('filament.admin.pages.change-password')) {
            return $next($request);
        }

        return redirect()->to(ChangePassword::getUrl());
    }
}
