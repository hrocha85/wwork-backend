<?php

namespace App\Actions\Auth;

use App\Models\RefreshToken;
use App\Models\User;
use App\Support\RecordActivity;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;

class LogoutUser
{
    public function __invoke(): void
    {
        /** @var User|null $user */
        $user = request()->user() ?? Auth::guard('web')->user();

        if ($user !== null) {
            $user->loadMissing('membership');
            RecordActivity::add($user->membership?->agency_id, $user->id, 'auth.logout');
        }

        $this->revoke($user, request()->string('refresh_token')->toString());

        Auth::guard('web')->logout();
        Auth::guard('sanctum')->forgetUser();

        if (request()->hasSession()) {
            request()->session()->invalidate();
            request()->session()->regenerateToken();
        }
    }

    private function revoke(?User $user, string $refreshToken): void
    {
        if ($refreshToken !== '') {
            $row = RefreshToken::query()->where('token_hash', hash('sha256', $refreshToken))->first();
            if ($row !== null) {
                if ($row->access_token_id !== null) {
                    PersonalAccessToken::query()->whereKey($row->access_token_id)->delete();
                }
                $row->delete();
            }
        }

        $current = $user?->currentAccessToken();
        if ($current instanceof PersonalAccessToken) {
            RefreshToken::query()->where('access_token_id', $current->id)->delete();
            $current->delete();
        }
    }
}
