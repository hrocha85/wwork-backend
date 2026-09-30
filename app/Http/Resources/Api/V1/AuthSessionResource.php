<?php

namespace App\Http\Resources\Api\V1;

use App\Enums\MembershipRole;
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

        if ($user->membership === null) {
            return [
                'user' => self::clientUser($user),
                'agency' => null,
            ];
        }

        $agency = $user->membership->agency;

        return [
            'user' => self::user($user, $agency->timezone, true),
            'agency' => self::agency($agency, $user->membership->role === MembershipRole::Owner),
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
            'agency' => self::agency($agency, true),
            'subscription' => [
                'plan' => $subscription?->plan?->value,
                'status' => $subscription?->status?->value,
                'seats' => $subscription?->seats,
                'amount' => $subscription?->amount_minor,
                'billing' => $subscription?->billing?->value,
                'discount_type' => $subscription?->discount_type?->value,
                'offer_ends_at' => self::iso($subscription?->offer_ends_at, $agency->timezone),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function me(User $user): array
    {
        $user->loadMissing('membership.agency.subscription');

        if ($user->membership === null) {
            return [
                'user' => self::clientUser($user),
                'agency' => null,
                'team_count' => 0,
                'max_seats' => null,
            ];
        }

        $agency = $user->membership->agency;
        $subscription = $agency->subscription;
        $agencyPayload = self::agency($agency, $user->membership->role === MembershipRole::Owner);
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
            'first_access_at' => self::iso($user->first_access_at, $timezone),
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
    private static function clientUser(User $user): array
    {
        $avatar = $user->clients()->whereNotNull('avatar_path')->exists();

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'role' => 'client',
            'locale' => $user->locale->value,
            'must_change_password' => $user->must_change_password,
            'first_access_at' => self::iso($user->first_access_at, 'UTC'),
            'last_seen_at' => self::iso($user->last_seen_at, 'UTC'),
            'has_avatar' => $avatar,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function agency(Agency $agency, bool $withInvoice = false): array
    {
        $payload = [
            'id' => $agency->id,
            'name' => $agency->name,
            'timezone' => $agency->timezone,
            'currency' => $agency->currency,
            'country' => $agency->country,
            'invoice_region' => $agency->invoice_region,
            'trade' => $agency->trade->value,
            'trade_detail' => $agency->trade_detail,
        ];

        if ($withInvoice) {
            $payload['legal_address'] = $agency->legal_address;
            $payload['phone'] = $agency->phone;
            $payload['payment_method'] = $agency->payment_method;
            $payload['payment_details'] = $agency->payment_details;
            $payload['vat_registered'] = $agency->vat_registered;
            $payload['tax_id'] = $agency->tax_id;
            $payload['has_logo'] = filled($agency->logo_path);
            $payload['bio'] = $agency->bio;
            $payload['website'] = $agency->website;
            $payload['public_slug'] = $agency->public_slug;
        }

        return $payload;
    }

    private static function iso(mixed $moment, string $timezone): ?string
    {
        if ($moment === null) {
            return null;
        }

        return Carbon::parse($moment)->timezone($timezone)->toIso8601String();
    }
}
