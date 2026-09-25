<?php

namespace App\Actions\Auth;

use App\Models\User;
use App\Support\ApiException;
use App\Support\ErrorCodes;
use App\Support\RecordActivity;
use Illuminate\Support\Facades\Auth;

class LoginUser
{
    public function __invoke(string $email, string $password): User
    {
        $guard = Auth::guard('web');

        if (! $guard->attempt(['email' => $email, 'password' => $password])) {
            throw new ApiException(ErrorCodes::AUTH_FAILED, 401);
        }

        /** @var User $user */
        $user = $guard->user();
        $user->load('membership.agency');

        if ($user->membership === null) {
            $guard->logout();
            request()->session()->invalidate();
            request()->session()->regenerateToken();

            throw new ApiException(ErrorCodes::AUTH_FAILED, 401);
        }

        request()->session()->regenerate();

        $user->forceFill(['last_seen_at' => now()])->save();

        RecordActivity::add($user->membership->agency_id, $user->id, 'auth.login');

        return $user->fresh(['membership.agency.subscription']);
    }
}
