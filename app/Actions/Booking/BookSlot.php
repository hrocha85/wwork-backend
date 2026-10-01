<?php

namespace App\Actions\Booking;

use App\Enums\BookingRequestStatus;
use App\Enums\ContactChannel;
use App\Enums\SubscriptionStatus;
use App\Mail\BookingRequestedMail;
use App\Models\Agency;
use App\Models\BookingRequest;
use App\Support\ApiException;
use App\Support\ErrorCodes;
use App\Support\MailNotifier;
use App\Support\RecordActivity;
use Illuminate\Support\Facades\DB;

class BookSlot
{
    /**
     * @param  array<string, mixed>  $input
     * @return array{ok: true, request_id: int}
     */
    public function __invoke(string $token, array $input): array
    {
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

            if (! app(ListOpenSlots::class)->free($agency, $service->id, (string) $input['date'], (string) $input['time'])) {
                throw new ApiException(ErrorCodes::BOOKING_TAKEN, 409);
            }

            $owner = $agency->ownerMembership()->first();
            if ($owner === null) {
                throw new ApiException(ErrorCodes::BOOKING_NOT_FOUND, 404);
            }

            $request = BookingRequest::query()->create([
                'agency_id' => $agency->id,
                'booking_service_id' => $service->id,
                'client_name' => $input['name'],
                'client_phone' => $input['phone'],
                'client_contact_channel' => ContactChannel::from($input['contact_channel'] ?? ContactChannel::Whatsapp->value),
                'requested_date' => $input['date'],
                'requested_time' => $input['time'],
                'status' => BookingRequestStatus::Pending,
            ]);

            RecordActivity::add($agency->id, $owner->user_id, 'booking.requested');

            app(MailNotifier::class)->toOwner('booking.requested', $agency, new BookingRequestedMail($request));

            return ['ok' => true, 'request_id' => $request->id];
        });
    }
}
