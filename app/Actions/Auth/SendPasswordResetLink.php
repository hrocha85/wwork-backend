<?php

namespace App\Actions\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Password;

class SendPasswordResetLink
{
    public function __invoke(string $email): void
    {
        $user = User::query()->where('email', $email)->first();

        if ($user === null || $user->membership === null) {
            return;
        }

        Password::broker()->sendResetLink(['email' => $email]);
    }
}
