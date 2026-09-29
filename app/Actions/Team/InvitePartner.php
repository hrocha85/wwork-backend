<?php

namespace App\Actions\Team;

use App\Enums\PlanCode;
use App\Models\Invite;
use App\Models\User;
use App\Policies\TeamPolicy;
use App\Services\SeatPlan;
use App\Support\AgencyContext;
use App\Support\ApiException;
use App\Support\ErrorCodes;
use App\Support\RecordActivity;

class InvitePartner
{
    public function __invoke(string $email, int $rate): Invite
    {
        $actor = AgencyContext::user();
        $membership = AgencyContext::membership();

        if (! app(TeamPolicy::class)->invite($actor)) {
            throw new ApiException(ErrorCodes::TEAM_INVITE_FORBIDDEN, 403);
        }

        app(SeatPlan::class)->prepareInvite($membership->agency);

        $email = mb_strtolower(trim($email));
        $agency = $membership->agency;
        $agency->loadMissing('subscription');

        if (User::query()->where('email', $email)->exists()
            || $agency->memberships()->whereHas('user', fn ($query) => $query->where('email', $email))->exists()) {
            throw new ApiException(ErrorCodes::TEAM_EMAIL_ALREADY_MEMBER, 422);
        }

        $pending = Invite::query()
            ->where('agency_id', $agency->id)
            ->where('email', $email)
            ->whereNull('accepted_at')
            ->whereNull('cancelled_at')
            ->where('expires_at', '>', now())
            ->exists();

        if ($pending) {
            throw new ApiException(ErrorCodes::TEAM_EMAIL_ALREADY_INVITED, 422);
        }

        $subscription = $agency->subscription()->first();
        $limit = $subscription?->plan?->maxSeats() ?? 0;

        if ($agency->memberships()->count() >= $limit && $subscription?->plan !== PlanCode::Business) {
            throw new ApiException(ErrorCodes::TEAM_SEAT_LIMIT, 403);
        }

        $invite = new Invite([
            'agency_id' => $agency->id,
            'invited_by' => $actor->id,
            'email' => $email,
            'rate' => $rate,
            'expires_at' => now()->addDays(Invite::TTL_DAYS),
            'sent_at' => now(),
        ]);
        $plain = $invite->rotateToken();
        $invite->save();

        app(SendInviteMail::class)($invite, $plain, $actor);

        RecordActivity::add($agency->id, $actor->id, 'team.invited');

        return $invite->fresh('agency');
    }
}
