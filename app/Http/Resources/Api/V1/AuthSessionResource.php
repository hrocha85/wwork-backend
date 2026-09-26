<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Agency;
use App\Models\User;
use Illuminate\Support\Carbon;

class AuthSessionResource
{
    /**
     * @return array<string, mixed>
     */
    public static function login(User $user): array
    {
        $user->loadMissing('membership.agency');
        $agency = $user->membership->agency;

        return [
            'user' => self::user($user, $agency->timezone, true),
            'agency' => self::agency($agency),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function registered(User $user): array
    {
        $user->loadMissing('membership.agency.subscription');
        $agency = $user->membership->agency;
        $subscription = $agency->subscription;

        return [
            'user' => self::profile($user)['user'],
            'agency' => self::agency($agency),
            'subscription' => [
                'plan' => $subscription?->plan?->value,
                'status' => $subscription?->status?->value,
                'seats' => $subscription?->seats,
                'amount' => $subscription?->amount_minor,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function me(User $user): array
    {
        $user->loadMissing('membership.agency.subscription');
        $agency = $user->membership->agency;
        $subscription = $agency->subscription;
        $agencyPayload = self::agency($agency);
        $agencyPayload['subscription'] = [
            'plan' => $subscription?->plan?->value,
            'status' => $subscription?->status?->value,
            'seats' => $subscription?->seats,
            'amount' => $subscription?->amount_minor,
            'cancel_at' => self::iso($subscription?->cancel_at, $agency->timezone),
        ];

        return [
            'user' => self::user($user, $agency->timezone, true),
            'agency' => $agencyPayload,
            'team_count' => $agency->memberships()->count(),
            'max_seats' => $subscription?->plan?->maxSeats(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function profile(User $user): array
    {
        $user->loadMissing('membership');

        return [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->membership?->role?->value,
                'locale' => $user->locale->value,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function user(User $user, string $timezone, bool $withMustChange): array
    {
        $user->loadMissing('membership');

        $payload = [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->membership?->role?->value,
            'locale' => $user->locale->value,
            'must_change_password' => $user->must_change_password,
            'last_seen_at' => self::iso($user->last_seen_at, $timezone),
        ];

        if (! $withMustChange) {
            unset($payload['must_change_password']);
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    private static function agency(Agency $agency): array
    {
        return [
            'id' => $agency->id,
            'name' => $agency->name,
            'timezone' => $agency->timezone,
            'currency' => $agency->currency,
            'country' => $agency->country,
            'invoice_region' => $agency->invoice_region,
            'trade' => $agency->trade->value,
        ];
    }

    private static function iso(mixed $moment, string $timezone): ?string
    {
        if ($moment === null) {
            return null;
        }

        return Carbon::parse($moment)->timezone($timezone)->toIso8601String();
    }
}
