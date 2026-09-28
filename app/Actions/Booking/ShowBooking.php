<?php

namespace App\Actions\Booking;

use App\Enums\MembershipRole;
use App\Models\Agency;
use App\Support\AgencyContext;
use App\Support\ApiException;
use App\Support\ErrorCodes;

class ShowBooking
{
    /**
     * @return array<string, mixed>
     */
    public function __invoke(): array
    {
        $membership = AgencyContext::membership();
        if ($membership->role !== MembershipRole::Owner) {
            throw new ApiException(ErrorCodes::BOOKING_FORBIDDEN, 403);
        }

        return self::payload($membership->agency);
    }

    /**
     * @return array<string, mixed>
     */
    public static function payload(Agency $agency): array
    {
        $agency->loadMissing(['bookingServices', 'bookingHours']);
        $services = $agency->bookingServices;
        $hours = $agency->bookingHours;
        $ready = filled($agency->booking_token) && $services->isNotEmpty() && $hours->isNotEmpty();

        return [
            'ready' => $ready,
            'url' => $ready ? self::url((string) $agency->booking_token) : null,
            'services' => $services->map(fn ($service): array => [
                'id' => $service->id,
                'name' => $service->name,
                'duration_minutes' => $service->duration_minutes,
                'price_pence' => $service->price_pence,
            ])->values()->all(),
            'hours' => $hours->map(fn ($hour): array => [
                'weekday' => $hour->weekday,
                'starts' => substr((string) $hour->starts_at, 0, 5),
                'ends' => substr((string) $hour->ends_at, 0, 5),
            ])->values()->all(),
        ];
    }

    public static function url(string $token): string
    {
        return rtrim((string) config('wwork.frontend_url'), '/').'/book/'.$token;
    }
}
