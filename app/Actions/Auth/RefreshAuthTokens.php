<?php

namespace App\Actions\Auth;

use App\Models\RefreshToken;
use App\Models\User;
use App\Support\ApiException;
use App\Support\ErrorCodes;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\PersonalAccessToken;

class RefreshAuthTokens
{
    /**
     * @return array{token: string, refresh_token: string}
     */
    public function __invoke(string $refreshToken, IssueAuthTokens $issue): array
    {
        return DB::transaction(function () use ($refreshToken, $issue): array {
            $row = RefreshToken::query()
                ->where('token_hash', hash('sha256', $refreshToken))
                ->lockForUpdate()
                ->first();

            if ($row === null || $row->expires_at->isPast()) {
                $row?->delete();
                throw new ApiException(ErrorCodes::AUTH_FAILED, 401);
            }

            $user = User::query()->find($row->user_id);
            if ($user === null || $user->membership === null) {
                $row->delete();
                throw new ApiException(ErrorCodes::AUTH_FAILED, 401);
            }

            if ($row->access_token_id !== null) {
                PersonalAccessToken::query()->whereKey($row->access_token_id)->delete();
            }
            $row->delete();

            return $issue($user);
        });
    }
}
