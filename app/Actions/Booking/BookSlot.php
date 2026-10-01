<?php

namespace App\Actions\Booking;

use App\Enums\BookingRequestStatus;
use App\Enums\SubscriptionStatus;
use App\Mail\BookingRequestedMail;
use App\Models\Agency;
use App\Models\BookingRequest;
use App\Support\ApiException;
use App\Support\ErrorCodes;
use App\Support\MailNotifier;
use App\Support\RecordActivity;
use Illuminate\Support\Carbon;
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

            foreach ($this->recurringDates($input) as $date) {
                if (! app(ListOpenSlots::class)->free($agency, $service->id, $date, (string) $input['time'])) {
                    throw new ApiException(ErrorCodes::BOOKING_TAKEN, 409);
                }
            }

            $owner = $agency->ownerMembership()->first();
            if ($owner === null) {
                throw new ApiException(ErrorCodes::BOOKING_NOT_FOUND, 404);
            }

            $request = BookingRequest::query()->create([
                'agency_id' => $agency->id,
                'booking_service_id' => $service->id,
                'client_name' => $input['name'],
                'client_phone' => $input['whatsapp'],
                'requested_date' => $input['date'],
                'requested_time' => $input['time'],
                'estimated_end_time' => $input['estimated_end_time'] ?? null,
                'is_recurring' => (bool) ($input['is_recurring'] ?? false),
                'recurring_days' => ($input['is_recurring'] ?? false)
                    ? array_values($input['recurring_days'] ?? [])
                    : null,
                'status' => BookingRequestStatus::Pending,
            ]);

            RecordActivity::add($agency->id, $owner->user_id, 'booking.requested');

            app(MailNotifier::class)->toOwner('booking.requested', $agency, new BookingRequestedMail($request));

            return ['ok' => true, 'request_id' => $request->id];
        });
    }

    /**
     * Datas futuras de uma recorrência semanal, já validadas contra a disponibilidade.
     *
     * @param  array<string, mixed>  $input
     * @return list<string>
     */
    private function recurringDates(array $input): array
    {
        if (! ($input['is_recurring'] ?? false)) {
            return [];
        }

        $days = array_values(array_filter(
            is_array($input['recurring_days'] ?? null) ? $input['recurring_days'] : [],
            fn ($day) => is_string($day) && in_array($day, ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'], true),
        ));

        if ($days === []) {
            return [];
        }

        $cursor = Carbon::parse((string) $input['date'])->addWeek();
        $limit = Carbon::parse((string) $input['date'])->addWeeks(max(1, (int) config('wwork.recurrence_weeks', 12)));
        $names = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];
        $dates = [];

        while ($cursor->lte($limit)) {
            if (in_array($names[$cursor->dayOfWeekIso - 1], $days, true)) {
                $dates[] = $cursor->toDateString();
            }
            $cursor->addDay();
        }

        return $dates;
    }
}
