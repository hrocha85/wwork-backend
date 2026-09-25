<?php

namespace App\Actions\Team;

use App\Enums\Locale;
use App\Enums\MembershipRole;
use App\Models\Invite;
use App\Models\Membership;
use App\Models\User;
use App\Support\ApiException;
use App\Support\ErrorCodes;
use App\Support\RecordActivity;
use Illuminate\Support\Facades\Auth;

class AcceptInvite
{
    /**
     * @param  array{name: string, password: string, locale: string, terms_accepted: mixed}  $input
     */
    public function __invoke(string $token, array $input): User
    {
        if ($input['terms_accepted'] !== true) {
            throw new ApiException(ErrorCodes::REGISTER_TERMS_REQUIRED, 422);
        }

        $locale = Locale::tryFrom($input['locale']);

        if ($locale === null) {
            throw new ApiException(ErrorCodes::ME_INVALID_LOCALE, 422);
        }

        $invite = Invite::query()->where('token', $token)->first();

        if ($invite === null || $invite->expires_at->isPast()) {
            throw new ApiException(ErrorCodes::INVITE_EXPIRED, 404);
        }

        if ($invite->accepted_at !== null) {
            throw new ApiException(ErrorCodes::INVITE_ALREADY_ACCEPTED, 409);
        }

        if (User::query()->where('email', $invite->email)->exists()) {
            throw new ApiException(ErrorCodes::TEAM_EMAIL_ALREADY_MEMBER, 422);
        }

        $user = User::query()->create([
            'name' => $input['name'],
            'email' => $invite->email,
            'password' => $input['password'],
            'locale' => $locale,
            'must_change_password' => false,
            'terms_accepted_at' => now(),
        ]);

        Membership::query()->create([
            'agency_id' => $invite->agency_id,
            'user_id' => $user->id,
            'role' => MembershipRole::Invited,
            'rate' => $invite->rate,
        ]);

        $invite->forceFill(['accepted_at' => now()])->save();

        RecordActivity::add($invite->agency_id, $user->id, 'team.invite_accepted');

        Auth::guard('web')->login($user);
        request()->session()->regenerate();
        $user->forceFill(['last_seen_at' => now()])->save();

        return $user->fresh(['membership.agency']);
    }
}
