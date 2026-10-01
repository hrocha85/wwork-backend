<?php

namespace App\Actions\Booking;

use App\Actions\Visits\ExpandRecurrence;
use App\Enums\ContactChannel;
use App\Enums\VisitStatus;
use App\Models\BookingRequest;
use App\Models\Client;
use App\Models\Visit;
use App\Support\ApiException;
use App\Support\ErrorCodes;
use App\Support\RecordActivity;

class ScheduleAcceptedRequest
{
    public function __invoke(BookingRequest $request): Visit
    {
        $agency = $request->agency()->first();
        $owner = $agency?->ownerMembership()->first();
        if ($agency === null || $owner === null) {
            throw new ApiException(ErrorCodes::BOOKING_NOT_FOUND, 404);
        }

        $client = $this->client($agency->id, $owner->user_id, $request);
        $quote = $request->kind === 'quote';
        $date = $quote ? $request->proposed_date : $request->requested_date;
        $time = $quote ? $request->proposed_time : $request->requested_time;
        if ($date === null || $time === null) {
            throw new ApiException(ErrorCodes::BOOKING_NOT_READY, 422);
        }

        $note = trim((string) $request->quote_note);
        $description = $quote
            ? trim((string) $request->description.($note === '' ? '' : "\n".$note))
            : $request->service?->name;

        $visit = Visit::query()->create([
            'agency_id' => $agency->id,
            'client_id' => $client->id,
            'assignee_id' => $owner->user_id,
            'service_date' => $date->toDateString(),
            'service_time' => substr((string) $time, 0, 5),
            'estimated_end_time' => $request->estimated_end_time,
            'is_recurring' => (bool) $request->is_recurring,
            'recurring_days' => $request->is_recurring ? ($request->recurring_days ?? []) : null,
            'description' => $description === '' ? null : $description,
            'price_pence' => $quote ? (int) $request->quote_pence : (int) $request->service?->price_pence,
            'lat' => $client->lat,
            'lng' => $client->lng,
            'status' => VisitStatus::Todo,
        ]);

        if ($visit->is_recurring && ! empty($visit->recurring_days)) {
            app(ExpandRecurrence::class)($visit->fresh(['goals', 'agency']), [
                'is_recurring' => true,
                'recurring_days' => $visit->recurring_days,
            ]);
        }

        RecordActivity::add($agency->id, $owner->user_id, 'visit.created');

        return $visit;
    }

    private function client(int $agencyId, int $ownerId, BookingRequest $request): Client
    {
        $digits = preg_replace('/\D/', '', $request->client_phone) ?? '';
        $existing = Client::query()
            ->where('agency_id', $agencyId)
            ->get()
            ->first(function (Client $client) use ($digits): bool {
                return (preg_replace('/\D/', '', $client->phone) ?? '') === $digits && $digits !== '';
            });

        if ($existing !== null) {
            return $existing;
        }

        $client = Client::query()->create([
            'agency_id' => $agencyId,
            'created_by' => $ownerId,
            'name' => $request->client_name,
            'phone' => $request->client_phone,
            'contact_channel' => $request->client_contact_channel ?? ContactChannel::Whatsapp,
            'address' => filled($request->address) ? $request->address : 'A confirmar',
            'lat' => null,
            'lng' => null,
        ]);

        RecordActivity::add($agencyId, $ownerId, 'client.created');

        return $client;
    }
}
