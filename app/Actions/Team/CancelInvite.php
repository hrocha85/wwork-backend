<?php

namespace App\Actions\Team;

use App\Models\Invite;
use App\Policies\TeamPolicy;
use App\Support\AgencyContext;
use App\Support\ApiException;
use App\Support\ErrorCodes;
use App\Support\RecordActivity;

class CancelInvite
{
    public function __invoke(Invite $invite): void
    {
        $actor = AgencyContext::user();
        $membership = AgencyContext::membership();

        if (! app(TeamPolicy::class)->cancel($actor) || $invite->agency_id !== $membership->agency_id) {
            throw new ApiException(ErrorCodes::TEAM_NOT_OWNER, 403);
        }

        if ($invite->accepted_at !== null) {
            throw new ApiException(ErrorCodes::INVITE_ALREADY_ACCEPTED, 409);
        }

        if ($invite->cancelled_at !== null) {
            throw new ApiException(ErrorCodes::INVITE_CANCELLED, 409);
        }

        $invite->forceFill(['cancelled_at' => now()])->save();

        RecordActivity::add($invite->agency_id, $actor->id, 'team.invite_cancelled');
    }
}
