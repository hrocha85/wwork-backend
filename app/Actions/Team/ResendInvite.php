<?php

namespace App\Actions\Team;

use App\Mail\PartnerInvited;
use App\Models\Invite;
use App\Policies\TeamPolicy;
use App\Support\AgencyContext;
use App\Support\ApiException;
use App\Support\ErrorCodes;
use App\Support\RecordActivity;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class ResendInvite
{
    public function __invoke(Invite $invite): Invite
    {
        $actor = AgencyContext::user();
        $membership = AgencyContext::membership();

        if (! app(TeamPolicy::class)->resend($actor) || $invite->agency_id !== $membership->agency_id) {
            throw new ApiException(ErrorCodes::TEAM_NOT_OWNER, 403);
        }

        if ($invite->accepted_at !== null) {
            throw new ApiException(ErrorCodes::INVITE_ALREADY_ACCEPTED, 409);
        }

        $invite->forceFill([
            'token' => Str::random(40),
            'expires_at' => now()->addDays(7),
            'sent_at' => now(),
        ])->save();

        try {
            Mail::to($invite->email)->send(new PartnerInvited($invite));
        } catch (\Throwable) {
            RecordActivity::add($invite->agency_id, $actor->id, 'mail.failed');
        }

        RecordActivity::add($invite->agency_id, $actor->id, 'team.invite_resent');

        return $invite->fresh();
    }
}
