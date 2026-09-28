<?php

namespace App\Actions\Auth;

use App\Models\RefreshToken;
use App\Models\User;
use Illuminate\Support\Str;

class IssueAuthTokens
{
    /**
     * @return array{token: string, refresh_token: string}
     */
    public function __invoke(User $user): array
    {
        $access = $user->createToken('access', ['*'], now()->addDay());
        $refresh = Str::random(80);

        RefreshToken::query()->create([
            'user_id' => $user->id,
            'access_token_id' => $access->accessToken->id,
            'token_hash' => hash('sha256', $refresh),
            'expires_at' => now()->addDays(60),
        ]);

        return [
            'token' => $access->plainTextToken,
            'refresh_token' => $refresh,
        ];
    }
}
