<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Invite;
use App\Models\Membership;
use App\Models\User;
use App\Policies\TeamPolicy;
use App\Support\AgencyContext;
use App\Support\ApiException;
use App\Support\ErrorCodes;
use Illuminate\Support\Carbon;

class TeamResource
{
    /**
     * @return array<string, mixed>
     */
    public static function invite(Invite $invite, string $timezone): array
    {
        return [
            'invite' => [
                'id' => $invite->id,
                'email' => $invite->email,
                'token' => $invite->token,
                'rate' => $invite->rate,
                'accepted' => $invite->accepted_at !== null,
                'expires_at' => Carbon::parse($invite->expires_at)->timezone($timezone)->toIso8601String(),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function index(): array
    {
        $actor = AgencyContext::user();

        if (! app(TeamPolicy::class)->view($actor)) {
            throw new ApiException(ErrorCodes::TEAM_NOT_OWNER, 403);
        }

        $membership = AgencyContext::membership();
        $agency = $membership->agency;
        $timezone = $agency->timezone;

        $members = $agency->memberships()
            ->with('user')
            ->get()
            ->map(fn (Membership $row): array => [
                'id' => $row->user_id,
                'name' => $row->user->name,
                'email' => $row->user->email,
                'role' => $row->role->value,
                'last_seen_at' => $row->user->last_seen_at === null
                    ? null
                    : Carbon::parse($row->user->last_seen_at)->timezone($timezone)->toIso8601String(),
                'rate' => $row->role->value === 'owner' ? null : $row->rate,
            ])
            ->values()
            ->all();

        $pending = Invite::query()
            ->where('agency_id', $agency->id)
            ->whereNull('accepted_at')
            ->where('expires_at', '>', now())
            ->orderBy('sent_at')
            ->get()
            ->map(fn (Invite $invite): array => [
                'id' => $invite->id,
                'email' => $invite->email,
                'rate' => $invite->rate,
                'sent_at' => Carbon::parse($invite->sent_at)->timezone($timezone)->toIso8601String(),
            ])
            ->values()
            ->all();

        return [
            'members' => $members,
            'pending_invites' => $pending,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function rate(Membership $membership): array
    {
        return [
            'id' => $membership->user_id,
            'rate' => $membership->rate,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function accepted(User $user): array
    {
        $user->loadMissing('membership.agency');
        $agency = $user->membership->agency;

        return [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->membership->role->value,
                'locale' => $user->locale->value,
            ],
            'agency' => [
                'id' => $agency->id,
                'name' => $agency->name,
            ],
        ];
    }
}
