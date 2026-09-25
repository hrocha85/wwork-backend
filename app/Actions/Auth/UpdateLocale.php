<?php

namespace App\Actions\Auth;

use App\Enums\Locale;
use App\Models\User;
use App\Support\ApiException;
use App\Support\ErrorCodes;
use App\Support\RecordActivity;

class UpdateLocale
{
    public function __invoke(User $user, string $locale): User
    {
        $value = Locale::tryFrom($locale);

        if ($value === null) {
            throw new ApiException(ErrorCodes::ME_INVALID_LOCALE, 422);
        }

        $user->locale = $value;
        $user->save();

        $user->loadMissing('membership');
        RecordActivity::add($user->membership?->agency_id, $user->id, 'me.locale');

        return $user;
    }
}
