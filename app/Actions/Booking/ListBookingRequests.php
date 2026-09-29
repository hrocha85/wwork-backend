<?php

namespace App\Actions\Booking;

use App\Enums\MembershipRole;
use App\Models\Agency;
use App\Models\BookingRequest;
use App\Support\AgencyContext;
use App\Support\ApiException;
use App\Support\ErrorCodes;

class ListBookingRequests
{
    /**
     * @return list<array<string, mixed>>
     */
    public function __invoke(): array
    {
        $membership = AgencyContext::membership();
        if ($membership->role !== MembershipRole::Owner) {
            throw new ApiException(ErrorCodes::BOOKING_FORBIDDEN, 403);
        }

        return self::forAgency($membership->agency);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function forAgency(Agency $agency): array
    {
        return $agency->bookingRequests()
            ->with('service')
            ->orderByDesc('requested_date')
            ->orderByDesc('id')
            ->limit(50)
            ->get()
            ->map(fn (BookingRequest $request): array => self::row($request))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public static function row(BookingRequest $request): array
    {
        return [
            'id' => $request->id,
            'client_name' => $request->client_name,
            'client_phone' => $request->client_phone,
            'date' => $request->requested_date?->toDateString(),
            'time' => $request->requested_time === null ? null : substr((string) $request->requested_time, 0, 5),
            'service_name' => $request->service?->name,
            'status' => $request->status->value,
            'kind' => $request->kind ?? 'slot',
            'address' => $request->address,
            'description' => $request->description,
            'quote_pence' => $request->quote_pence,
            'quote_note' => $request->quote_note,
            'proposed_date' => $request->proposed_date?->toDateString(),
            'proposed_time' => $request->proposed_time === null ? null : substr((string) $request->proposed_time, 0, 5),
            'public_token' => $request->public_token,
            'has_photo' => filled($request->photo_path),
        ];
    }
}
