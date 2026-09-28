<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Booking\BookSlot;
use App\Actions\Booking\ListOpenSlots;
use App\Actions\Booking\SaveBooking;
use App\Actions\Booking\ShowBooking;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\BookSlotRequest;
use App\Http\Requests\Api\V1\SaveBookingRequest;
use App\Models\Agency;
use App\Support\ApiException;
use App\Support\ErrorCodes;
use Illuminate\Http\JsonResponse;

class BookingController extends Controller
{
    public function show(ShowBooking $show): JsonResponse
    {
        return response()->json($show());
    }

    public function update(SaveBookingRequest $request, SaveBooking $save): JsonResponse
    {
        return response()->json($save($request->validated()));
    }

    public function publicShow(string $token, ListOpenSlots $slots): JsonResponse
    {
        $agency = $this->agency($token);
        $agency->loadMissing('bookingServices');

        return response()->json([
            'agency_name' => $agency->name,
            'currency' => $agency->currency,
            'services' => $agency->bookingServices->map(fn ($service): array => [
                'id' => $service->id,
                'name' => $service->name,
                'duration_minutes' => $service->duration_minutes,
                'price_pence' => $service->price_pence,
            ])->values()->all(),
            'days' => $slots($agency),
        ]);
    }

    public function publicStore(string $token, BookSlotRequest $request, BookSlot $book): JsonResponse
    {
        return response()->json($book($token, $request->validated()), 201);
    }

    private function agency(string $token): Agency
    {
        $agency = Agency::query()->where('booking_token', $token)->first();
        if ($agency === null || $agency->bookingServices()->doesntExist() || $agency->bookingHours()->doesntExist()) {
            throw new ApiException(ErrorCodes::BOOKING_NOT_FOUND, 404);
        }

        return $agency;
    }
}
