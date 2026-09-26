<?php

namespace App\Actions\Agenda;

use App\Models\AgendaBlock;
use App\Support\ApiException;
use App\Support\ErrorCodes;
use Illuminate\Support\Carbon;

class AssertOwnerAvailable
{
    public function __invoke(int $agencyId, string $timezone, string $date, string $time, int $ownerUserId): void
    {
        $moment = Carbon::parse($date.' '.substr($time, 0, 8), $timezone)->utc();

        $blocked = AgendaBlock::query()
            ->where('agency_id', $agencyId)
            ->where('user_id', $ownerUserId)
            ->where('starts_at', '<=', $moment)
            ->where('ends_at', '>', $moment)
            ->exists();

        if ($blocked) {
            throw new ApiException(ErrorCodes::AGENDA_BLOCKED, 409);
        }
    }
}
