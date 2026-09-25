<?php

namespace App\Support;

use App\Models\Membership;
use App\Models\User;

class AgencyContext
{
    public static function user(): User
    {
        $user = request()->user();

        if (! $user instanceof User) {
            throw new ApiException(ErrorCodes::UNAUTHENTICATED, 401);
        }

        return $user;
    }

    public static function membership(): Membership
    {
        $user = self::user();
        $user->loadMissing('membership.agency.subscription');

        if ($user->membership === null) {
            throw new ApiException(ErrorCodes::AUTH_FAILED, 401);
        }

        return $user->membership;
    }
}
