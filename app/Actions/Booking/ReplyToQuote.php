<?php

namespace App\Actions\Booking;

use App\Enums\BookingRequestStatus;
use App\Enums\MembershipRole;
use App\Models\BookingRequest;
use App\Support\AgencyContext;
use App\Support\ApiException;
use App\Support\ErrorCodes;
use Illuminate\Support\Facades\DB;

class ReplyToQuote
{
    /**
     * @param  array{price_pence: int, note: string, date: string, time: string}  $input
     * @return array<string, mixed>
     */
    public function __invoke(int $id, array $input): array
    {
        $membership = AgencyContext::membership();
        if ($membership->role !== MembershipRole::Owner) {
            throw new ApiException(ErrorCodes::BOOKING_FORBIDDEN, 403);
        }

        return DB::transaction(function () use ($membership, $id, $input): array {
            $request = BookingRequest::query()
                ->where('agency_id', $membership->agency_id)
                ->whereKey($id)
                ->lockForUpdate()
                ->first();

            if ($request === null || $request->kind !== 'quote') {
                throw new ApiException(ErrorCodes::BOOKING_NOT_FOUND, 404);
            }
            if ($request->status !== BookingRequestStatus::Pending) {
                throw new ApiException(ErrorCodes::BOOKING_TAKEN, 409);
            }

            $request->quote_pence = $input['price_pence'];
            $request->quote_note = $input['note'];
            $request->proposed_date = $input['date'];
            $request->proposed_time = $input['time'];
            $request->status = BookingRequestStatus::Quoted;
            $request->save();

            return ListBookingRequests::row($request->fresh('service'));
        });
    }
}
