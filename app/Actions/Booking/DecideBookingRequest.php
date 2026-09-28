<?php

namespace App\Actions\Booking;

use App\Enums\BookingRequestStatus;
use App\Enums\MembershipRole;
use App\Models\BookingRequest;
use App\Support\AgencyContext;
use App\Support\ApiException;
use App\Support\ErrorCodes;
use Illuminate\Support\Facades\DB;

class DecideBookingRequest
{
    /**
     * @return array<string, mixed>
     */
    public function __invoke(int $id, string $status): array
    {
        $membership = AgencyContext::membership();
        if ($membership->role !== MembershipRole::Owner) {
            throw new ApiException(ErrorCodes::BOOKING_FORBIDDEN, 403);
        }

        $next = BookingRequestStatus::from($status);

        return DB::transaction(function () use ($membership, $id, $next): array {
            $agency = $membership->agency()->lockForUpdate()->first();
            $request = BookingRequest::query()
                ->where('agency_id', $agency->id)
                ->whereKey($id)
                ->first();
            if ($request === null) {
                throw new ApiException(ErrorCodes::BOOKING_NOT_FOUND, 404);
            }
            if ($request->status !== BookingRequestStatus::Pending) {
                throw new ApiException(ErrorCodes::BOOKING_TAKEN, 409);
            }

            if ($next === BookingRequestStatus::Approved) {
                $open = app(ListOpenSlots::class)->free(
                    $agency,
                    $request->booking_service_id,
                    $request->requested_date->toDateString(),
                    substr((string) $request->requested_time, 0, 5),
                    $request->id,
                );
                if (! $open) {
                    throw new ApiException(ErrorCodes::BOOKING_TAKEN, 409);
                }
            }

            $request->status = $next;
            $request->save();

            return ListBookingRequests::row($request->fresh('service'));
        });
    }
}
