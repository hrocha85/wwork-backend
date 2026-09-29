<?php

namespace App\Actions\Auth;

use App\Models\User;
use App\Support\ApiException;
use App\Support\ErrorCodes;
use App\Support\PhoneNumber;
use App\Support\RecordActivity;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class LoginUser
{
    public function __invoke(string $login, string $password): User
    {
        $user = $this->find($login);
        $guard = Auth::guard('web');

        if ($user === null || ! Hash::check($password, $user->password)) {
            throw new ApiException(ErrorCodes::AUTH_FAILED, 401);
        }

        $guard->login($user);
        $user->load('membership.agency', 'clients');

        if ($user->membership === null && $user->clients->isEmpty()) {
            $guard->logout();
            request()->session()->invalidate();
            request()->session()->regenerateToken();

            throw new ApiException(ErrorCodes::AUTH_FAILED, 401);
        }

        request()->session()->regenerate();
        $user->forceFill(['last_seen_at' => now()])->save();

        if ($user->membership !== null) {
            RecordActivity::add($user->membership->agency_id, $user->id, 'auth.login');

            return $user->fresh(['membership.agency.subscription']);
        }

        return $user->fresh('clients');
    }

    private function find(string $login): ?User
    {
        if (PhoneNumber::looksLikeEmail($login)) {
            return User::query()->where('email', $login)->first();
        }

        $phone = PhoneNumber::digits($login);

        if ($phone === '') {
            return null;
        }

        return User::query()->where('phone', $phone)->first();
    }
}
