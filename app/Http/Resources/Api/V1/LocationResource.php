<?php

namespace App\Http\Resources\Api\V1;

use App\Enums\MembershipRole;
use App\Models\Membership;
use App\Support\AgencyContext;
use App\Support\ApiException;
use App\Support\ErrorCodes;
use Illuminate\Support\Carbon;

class LocationResource
{
    /**
     * @return array<string, mixed>
     */
    public static function index(): array
    {
        $actor = AgencyContext::user();
        $membership = AgencyContext::membership();

        if ($membership->role !== MembershipRole::Owner) {
            throw new ApiException(ErrorCodes::TEAM_INVITE_FORBIDDEN, 403);
        }

        $timezone = $membership->agency->timezone;
        $cutoff = now()->subMinutes(2);

        $rows = Membership::query()
            ->where('agency_id', $membership->agency_id)
            ->whereHas('user', fn ($query) => $query->where('last_located_at', '>', $cutoff))
            ->with('user')
            ->get()
            ->sortBy(fn (Membership $row): string => $row->user_id === $actor->id ? '0' : '1'.$row->user->name)
            ->values();

        return [
            'locations' => $rows->map(fn (Membership $row): array => [
                'user_id' => $row->user_id,
                'name' => $row->user->name,
                'lat' => (float) $row->user->last_lat,
                'lng' => (float) $row->user->last_lng,
                'at' => Carbon::parse($row->user->last_located_at)->timezone($timezone)->toIso8601String(),
                'self' => $row->user_id === $actor->id,
            ])->all(),
        ];
    }
}
