<?php

namespace App\Actions\Auth;

use App\Models\User;
use App\Support\ApiException;
use App\Support\ErrorCodes;
use App\Support\RecordActivity;
use Illuminate\Support\Facades\Hash;

class ChangePassword
{
    /**
     * @param  array{current_password: string, password: string, password_confirmation: string}  $data
     */
    public function __invoke(User $user, array $data): void
    {
        if ($data['password'] !== $data['password_confirmation']) {
            throw new ApiException(ErrorCodes::AUTH_PASSWORD_MISMATCH, 422);
        }

        if (! Hash::check($data['current_password'], $user->password)) {
            throw new ApiException(ErrorCodes::AUTH_CURRENT_PASSWORD, 422);
        }

        $user->password = $data['password'];
        $user->must_change_password = false;
        $user->save();

        $guard = auth('web');
        $hash = $guard->hashPasswordForCookie($user->getAuthPassword());
        session()->put('password_hash_web', $hash);

        $user->loadMissing('membership');
        RecordActivity::add($user->membership?->agency_id, $user->id, 'auth.password_changed');
    }
}
