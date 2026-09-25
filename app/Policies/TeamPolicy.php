<?php

namespace App\Policies;

use App\Enums\MembershipRole;
use App\Models\Membership;
use App\Models\User;

class TeamPolicy
{
    public function invite(User $user): bool
    {
        return $this->isOwner($user);
    }

    public function view(User $user): bool
    {
        return $this->isOwner($user);
    }

    public function resend(User $user): bool
    {
        return $this->isOwner($user);
    }

    public function updateRate(User $user, Membership $membership): bool
    {
        return $this->isOwner($user)
            && $membership->role !== MembershipRole::Owner
            && $user->membership?->agency_id === $membership->agency_id;
    }

    public function remove(User $user, Membership $membership): bool
    {
        return $this->updateRate($user, $membership);
    }

    private function isOwner(User $user): bool
    {
        $user->loadMissing('membership');

        return $user->membership?->role === MembershipRole::Owner;
    }
}
