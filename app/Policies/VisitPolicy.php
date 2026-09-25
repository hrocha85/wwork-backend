<?php

namespace App\Policies;

use App\Enums\MembershipRole;
use App\Models\User;
use App\Models\Visit;
use App\Support\ErrorCodes;

class VisitPolicy
{
    public function create(User $user): bool
    {
        return $this->isOwner($user);
    }

    public function update(User $user, Visit $visit): bool
    {
        return $this->owns($user, $visit);
    }

    public function delete(User $user, Visit $visit): bool
    {
        return $this->owns($user, $visit);
    }

    public function denial(User $user, Visit $visit): ?string
    {
        $user->loadMissing('membership');
        $membership = $user->membership;

        if ($membership === null || $membership->agency_id !== $visit->agency_id) {
            return ErrorCodes::VISIT_FORBIDDEN;
        }

        if ($membership->role === MembershipRole::Owner) {
            return null;
        }

        if ($visit->assignee_id === $user->id) {
            return null;
        }

        return ErrorCodes::VISIT_NOT_ASSIGNEE;
    }

    private function owns(User $user, Visit $visit): bool
    {
        $user->loadMissing('membership');

        return $this->isOwner($user)
            && $user->membership?->agency_id === $visit->agency_id;
    }

    private function isOwner(User $user): bool
    {
        $user->loadMissing('membership');

        return $user->membership?->role === MembershipRole::Owner;
    }
}
