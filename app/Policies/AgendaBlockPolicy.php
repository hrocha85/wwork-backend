<?php

namespace App\Policies;

use App\Enums\MembershipRole;
use App\Models\AgendaBlock;
use App\Models\User;

class AgendaBlockPolicy
{
    public function delete(User $user, AgendaBlock $block): bool
    {
        $user->loadMissing('membership');
        $membership = $user->membership;

        if ($membership === null || $membership->agency_id !== $block->agency_id) {
            return false;
        }

        if ($membership->role === MembershipRole::Owner) {
            return true;
        }

        return $block->created_by === $user->id;
    }
}
