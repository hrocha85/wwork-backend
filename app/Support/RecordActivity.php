<?php

namespace App\Support;

use App\Models\Activity;

class RecordActivity
{
    public static function add(?int $agencyId, ?int $userId, string $action): void
    {
        Activity::query()->create([
            'agency_id' => $agencyId,
            'user_id' => $userId,
            'action' => $action,
        ]);
    }
}
