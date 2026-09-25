<?php

namespace App\Policies;

use App\Enums\MembershipRole;
use App\Models\Invoice;
use App\Models\User;

class InvoicePolicy
{
    public function create(User $user): bool
    {
        return $this->isOwner($user);
    }

    public function view(User $user, Invoice $invoice): bool
    {
        $user->loadMissing('membership');

        return $this->isOwner($user)
            && $user->membership?->agency_id === $invoice->agency_id;
    }

    private function isOwner(User $user): bool
    {
        $user->loadMissing('membership');

        return $user->membership?->role === MembershipRole::Owner;
    }
}
