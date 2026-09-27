<?php

namespace App\Filament\Auth;

use App\Filament\Pages\Dashboard;
use App\Models\User;
use Filament\Auth\Http\Responses\Contracts\LoginResponse as Responsable;
use Illuminate\Http\RedirectResponse;
use Livewire\Features\SupportRedirects\Redirector;

class LoginResponse implements Responsable
{
    public function toResponse($request): RedirectResponse|Redirector
    {
        $user = auth('staff')->user();
        if ($user instanceof User && $user->must_change_password) {
            $user->forceFill(['must_change_password' => false])->save();
        }

        return redirect()->intended(Dashboard::getUrl());
    }
}
