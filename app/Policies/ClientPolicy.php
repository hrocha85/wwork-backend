<?php

namespace App\Policies;

use App\Enums\MembershipRole;
use App\Models\Client;
use App\Models\User;

class ClientPolicy
{
    public function create(User $user): bool
    {
        $user->loadMissing('membership');

        return $user->membership !== null;
    }

    public function viewAny(User $user): bool
    {
        return $this->isOwner($user);
    }

    public function view(User $user, Client $client): bool
    {
        return $this->owns($user, $client);
    }

    public function delete(User $user, Client $client): bool
    {
        return $this->owns($user, $client);
    }

    private function owns(User $user, Client $client): bool
    {
        $user->loadMissing('membership');

        return $this->isOwner($user)
            && $user->membership?->agency_id === $client->agency_id;
    }

    private function isOwner(User $user): bool
    {
        $user->loadMissing('membership');

        return $user->membership?->role === MembershipRole::Owner;
    }
}
