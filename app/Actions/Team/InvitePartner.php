<?php

namespace App\Actions\Team;

use App\Enums\PlanCode;
use App\Mail\PartnerInvited;
use App\Models\Invite;
use App\Models\User;
use App\Policies\TeamPolicy;
use App\Services\SeatPlan;
use App\Support\AgencyContext;
use App\Support\ApiException;
use App\Support\ErrorCodes;
use App\Support\RecordActivity;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

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

        $invite = Invite::query()->create([
            'agency_id' => $agency->id,
            'invited_by' => $actor->id,
            'email' => $email,
            'token' => Str::random(40),
            'rate' => $rate,
            'expires_at' => now()->addDays(7),
            'sent_at' => now(),
        ]);

        $this->send($invite);

        RecordActivity::add($agency->id, $actor->id, 'team.invited');

        return $invite->fresh('agency');
    }

    private function send(Invite $invite): void
    {
        try {
            Mail::to($invite->email)->send(new PartnerInvited($invite));
        } catch (\Throwable) {
            RecordActivity::add($invite->agency_id, $invite->invited_by, 'mail.failed');
        }
    }
}
