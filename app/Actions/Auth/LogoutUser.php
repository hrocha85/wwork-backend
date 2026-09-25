<?php

namespace App\Actions\Auth;

use App\Models\User;
use App\Support\RecordActivity;
use Illuminate\Support\Facades\Auth;

class LogoutUser
{
    public function __invoke(): void
    {
        /** @var User|null $user */
        $user = Auth::guard('web')->user();

        if ($user !== null) {
            $user->loadMissing('membership');
            RecordActivity::add($user->membership?->agency_id, $user->id, 'auth.logout');
        }

        Auth::guard('web')->logout();
        Auth::guard('sanctum')->forgetUser();

        request()->session()->invalidate();
        request()->session()->regenerateToken();
    }
}
