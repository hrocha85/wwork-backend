<?php

namespace App\Actions\Location;

use App\Models\User;
use App\Support\ApiException;
use App\Support\ErrorCodes;

class UpdateMyLocation
{
    public function __invoke(User $user, mixed $lat, mixed $lng): void
    {
        if (! is_numeric($lat) || ! is_numeric($lng)) {
            throw new ApiException(ErrorCodes::LOCATION_MISSING, 422);
        }

        $user->forceFill([
            'last_lat' => $lat,
            'last_lng' => $lng,
            'last_located_at' => now(),
        ])->save();
    }
}
