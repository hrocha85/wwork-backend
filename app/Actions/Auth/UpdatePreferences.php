<?php

namespace App\Actions\Auth;

use App\Models\User;
use App\Support\RecordActivity;

class UpdatePreferences
{
    /**
     * @param  array<string, mixed>|null  $preferences
     */
    public function __invoke(User $user, ?array $preferences): User
    {
        if ($preferences === null) {
            $user->preferences = null;
        } else {
            $current = is_array($user->preferences) ? $user->preferences : [];
            $user->preferences = array_replace_recursive($current, $preferences);
        }

        $user->save();

        $user->loadMissing('membership');
        RecordActivity::add($user->membership?->agency_id, $user->id, 'me.preferences');

        return $user;
    }
}
