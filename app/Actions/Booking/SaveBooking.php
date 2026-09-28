<?php

namespace App\Actions\Booking;

use App\Enums\MembershipRole;
use App\Models\Agency;
use App\Models\BookingHour;
use App\Models\BookingService;
use App\Support\AgencyContext;
use App\Support\ApiException;
use App\Support\ErrorCodes;
use App\Support\RecordActivity;
use Illuminate\Support\Str;

class SaveBooking
{
    /**
     * @param  array{services: list<array<string, mixed>>, hours: list<array<string, mixed>>}  $input
     * @return array<string, mixed>
     */
    public function __invoke(array $input): array
    {
        $membership = AgencyContext::membership();
        if ($membership->role !== MembershipRole::Owner) {
            throw new ApiException(ErrorCodes::BOOKING_FORBIDDEN, 403);
        }

        foreach ($input['hours'] as $hour) {
            if ((string) $hour['ends'] <= (string) $hour['starts']) {
                throw new ApiException(ErrorCodes::BOOKING_INVALID_HOUR, 422);
            }
        }

        $agency = $membership->agency;
        if (! filled($agency->booking_token)) {
            $agency->booking_token = Str::lower(Str::random(40));
            $agency->save();
        }

        $agency->bookingServices()->delete();
        $agency->bookingHours()->delete();

        foreach ($input['services'] as $service) {
            BookingService::query()->create([
                'agency_id' => $agency->id,
                'name' => $service['name'],
                'duration_minutes' => $service['duration_minutes'],
                'price_pence' => $service['price_pence'],
            ]);
        }

        foreach ($input['hours'] as $hour) {
            BookingHour::query()->create([
                'agency_id' => $agency->id,
                'weekday' => $hour['weekday'],
                'starts_at' => $hour['starts'],
                'ends_at' => $hour['ends'],
            ]);
        }

        RecordActivity::add($agency->id, AgencyContext::user()->id, 'booking.saved');

        return ShowBooking::payload($agency->fresh());
    }
}
