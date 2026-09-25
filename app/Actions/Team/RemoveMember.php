<?php

namespace App\Actions\Team;

use App\Enums\MembershipRole;
use App\Models\Membership;
use App\Models\SyncDeletion;
use App\Models\User;
use App\Policies\TeamPolicy;
use App\Support\AgencyContext;
use App\Support\ApiException;
use App\Support\ErrorCodes;
use App\Support\RecordActivity;

class RemoveMember
{
    public function __invoke(User $member): void
    {
        $actor = AgencyContext::user();
        $agencyId = AgencyContext::membership()->agency_id;

        $membership = Membership::query()
            ->where('agency_id', $agencyId)
            ->where('user_id', $member->id)
            ->first();

        if ($membership === null) {
            throw new ApiException(ErrorCodes::TEAM_MEMBER_NOT_FOUND, 404);
        }

        if ($membership->role === MembershipRole::Owner) {
            throw new ApiException(ErrorCodes::TEAM_CANNOT_REMOVE_OWNER, 403);
        }

        if (! app(TeamPolicy::class)->remove($actor, $membership)) {
            throw new ApiException(ErrorCodes::TEAM_NOT_OWNER, 403);
        }

        SyncDeletion::query()->create([
            'agency_id' => $agencyId,
            'table_name' => 'memberships',
            'sync_uuid' => $membership->sync_uuid,
        ]);

        $membership->delete();

        RecordActivity::add($agencyId, $actor->id, 'team.member_removed');
    }
}
