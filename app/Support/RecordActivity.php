<?php

namespace App\Support;

use App\Models\Activity;

class RecordActivity
{
    /**
     * @param  array<string, mixed>|null  $properties
     */
    public static function add(?int $agencyId, ?int $userId, string $action, ?array $properties = null): void
    {
        Activity::query()->create([
            'agency_id' => $agencyId,
            'user_id' => $userId,
            'action' => $action,
            'properties' => $properties,
        ]);
    }
}
