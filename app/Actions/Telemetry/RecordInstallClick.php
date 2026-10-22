<?php

namespace App\Actions\Telemetry;

use App\Models\InstallClick;
use App\Models\User;
use Illuminate\Support\Carbon;

class RecordInstallClick
{
    public function __invoke(
        string $platform,
        ?string $timestamp = null,
        ?User $user = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): InstallClick {
        $clickedAt = $timestamp ? Carbon::parse($timestamp) : now();

        $agencyId = $user?->membership?->agency_id;

        return InstallClick::create([
            'platform' => $platform,
            'user_id' => $user?->id,
            'agency_id' => $agencyId,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'clicked_at' => $clickedAt,
        ]);
    }
}
