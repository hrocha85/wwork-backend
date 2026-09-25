<?php

namespace App\Actions\Team;

use App\Enums\MembershipRole;
use App\Models\Membership;
use App\Models\User;
use App\Policies\TeamPolicy;
use App\Support\AgencyContext;
use App\Support\ApiException;
use App\Support\ErrorCodes;
use App\Support\RecordActivity;

class UpdateRate
{
    public function __invoke(User $member, int $rate): Membership
    {
        $actor = AgencyContext::user();
        $membership = $this->membershipOf($member);

        if ($membership->role === MembershipRole::Owner) {
            if ($actor->membership?->role !== MembershipRole::Owner) {
                throw new ApiException(ErrorCodes::TEAM_NOT_OWNER, 403);
            }

            throw new ApiException(ErrorCodes::TEAM_CANNOT_RATE_OWNER, 403);
        }

        if (! app(TeamPolicy::class)->updateRate($actor, $membership)) {
            throw new ApiException(ErrorCodes::TEAM_NOT_OWNER, 403);
        }

        $membership->forceFill(['rate' => $rate])->save();

        RecordActivity::add($membership->agency_id, $actor->id, 'team.rate_updated');

        return $membership->fresh();
    }

    private function membershipOf(User $member): Membership
    {
        $agencyId = AgencyContext::membership()->agency_id;
        $membership = Membership::query()
            ->where('agency_id', $agencyId)
            ->where('user_id', $member->id)
            ->first();

        if ($membership === null) {
            throw new ApiException(ErrorCodes::TEAM_MEMBER_NOT_FOUND, 404);
        }

        return $membership;
    }
}
