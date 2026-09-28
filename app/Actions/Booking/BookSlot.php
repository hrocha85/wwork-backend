<?php

namespace App\Actions\Booking;

use App\Enums\SubscriptionStatus;
use App\Enums\VisitStatus;
use App\Models\Agency;
use App\Models\Client;
use App\Models\Visit;
use App\Support\ApiException;
use App\Support\ErrorCodes;
use App\Support\RecordActivity;
use Illuminate\Support\Facades\DB;

class BookSlot
{
    /**
     * @param  array<string, mixed>  $input
     * @return array{ok: true, visit_id: int}
     */
    public function __invoke(string $token, array $input): array
    {
        if (! is_numeric($input['lat'] ?? null) || ! is_numeric($input['lng'] ?? null)) {
            throw new ApiException(ErrorCodes::BOOKING_MISSING_POINT, 422);
        }

        return DB::transaction(function () use ($token, $input): array {
            $agency = Agency::query()->where('booking_token', $token)->lockForUpdate()->first();
            if ($agency === null) {
                throw new ApiException(ErrorCodes::BOOKING_NOT_FOUND, 404);
            }

            $agency->loadMissing('subscription');
            if ($agency->subscription?->status === SubscriptionStatus::Cancelled) {
                throw new ApiException(ErrorCodes::SUBSCRIPTION_INACTIVE, 402);
            }

            $service = $agency->bookingServices()->whereKey($input['service_id'])->first();
            if ($service === null) {
                throw new ApiException(ErrorCodes::BOOKING_INVALID_SERVICE, 422);
            }

            if (! app(ListOpenSlots::class)->open($agency, $service->id, (string) $input['date'], (string) $input['time'])) {
                throw new ApiException(ErrorCodes::BOOKING_TAKEN, 409);
            }

            $owner = $agency->ownerMembership()->first();
            if ($owner === null) {
                throw new ApiException(ErrorCodes::BOOKING_NOT_FOUND, 404);
            }

            $client = Client::query()->create([
                'agency_id' => $agency->id,
                'created_by' => $owner->user_id,
                'name' => $input['name'],
                'whatsapp' => $input['whatsapp'],
                'address' => $input['address'],
                'lat' => $input['lat'],
                'lng' => $input['lng'],
            ]);

            $visit = Visit::query()->create([
                'agency_id' => $agency->id,
                'client_id' => $client->id,
                'assignee_id' => $owner->user_id,
                'service_date' => $input['date'],
                'service_time' => $input['time'],
                'description' => $service->name,
                'price_pence' => $service->price_pence,
                'lat' => $input['lat'],
                'lng' => $input['lng'],
                'status' => VisitStatus::Todo,
            ]);

            RecordActivity::add($agency->id, $owner->user_id, 'booking.created');

            return ['ok' => true, 'visit_id' => $visit->id];
        });
    }
}
