<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Booking\AnswerQuote;
use App\Actions\Booking\BookSlot;
use App\Actions\Booking\DecideBookingRequest;
use App\Actions\Booking\ListBookingRequests;
use App\Actions\Booking\ListOpenSlots;
use App\Actions\Booking\OpenQuote;
use App\Actions\Booking\ReplyToQuote;
use App\Actions\Booking\SaveBooking;
use App\Actions\Booking\ShowBooking;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\BookSlotRequest;
use App\Http\Requests\Api\V1\DecideBookingRequestRequest;
use App\Http\Requests\Api\V1\OpenQuoteRequest;
use App\Http\Requests\Api\V1\ReplyToQuoteRequest;
use App\Http\Requests\Api\V1\SaveBookingRequest;
use App\Models\Agency;
use App\Models\BookingRequest;
use App\Support\ApiException;
use App\Support\ErrorCodes;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

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

    public function requests(ListBookingRequests $list): JsonResponse
    {
        return response()->json(['requests' => $list()]);
    }

    public function decide(int $id, DecideBookingRequestRequest $request, DecideBookingRequest $decide): JsonResponse
    {
        return response()->json($decide($id, (string) $request->validated('status')));
    }

    public function reply(int $id, ReplyToQuoteRequest $request, ReplyToQuote $reply): JsonResponse
    {
        return response()->json($reply($id, $request->validated()));
    }

    public function publicQuote(string $token, OpenQuoteRequest $request, OpenQuote $open): JsonResponse
    {
        return response()->json($open($token, $request->validated(), $request->file('photo')), 201);
    }

    public function publicQuoteShow(string $token, string $publicToken): JsonResponse
    {
        return response()->json(OpenQuote::present($this->quote($token, $publicToken)));
    }

    public function publicQuoteAnswer(string $token, string $publicToken, AnswerQuote $answer): JsonResponse
    {
        $accept = request()->boolean('accept');

        return response()->json($answer($token, $publicToken, $accept));
    }

    public function publicQuotePhoto(string $token, string $publicToken): StreamedResponse
    {
        return Storage::disk('local')->response(OpenQuote::photo($this->quote($token, $publicToken)));
    }

    public function publicShow(Request $request, string $token, ListOpenSlots $slots): JsonResponse
    {
        $agency = $this->agency($token);
        [$start, $end] = $this->window($request, $agency);
        $agency->loadMissing('bookingServices');

        return response()->json([
            'agency_name' => $agency->name,
            'currency' => $agency->currency,
            'has_logo' => filled($agency->logo_path),
            'services' => $agency->bookingServices->map(fn ($service): array => [
                'id' => $service->id,
                'name' => $service->name,
                'duration_minutes' => $service->duration_minutes,
                'price_pence' => $service->price_pence,
            ])->values()->all(),
            'days' => $slots($agency, $start, $end),
        ]);
    }

    public function publicStore(string $token, BookSlotRequest $request, BookSlot $book): JsonResponse
    {
        return response()->json($book($token, $request->validated()), 201);
    }

    public function publicLogo(string $token): StreamedResponse
    {
        $agency = Agency::query()->where('booking_token', $token)->first();
        if ($agency === null || ! filled($agency->logo_path) || ! Storage::disk('local')->exists($agency->logo_path)) {
            throw new ApiException(ErrorCodes::NOT_FOUND, 404);
        }

        return Storage::disk('local')->response($agency->logo_path);
    }

    private function quote(string $token, string $publicToken): BookingRequest
    {
        $agency = Agency::query()->where('booking_token', $token)->first();
        $request = $agency === null ? null : BookingRequest::query()
            ->where('agency_id', $agency->id)
            ->where('public_token', $publicToken)
            ->where('kind', 'quote')
            ->first();
        if ($request === null) {
            throw new ApiException(ErrorCodes::BOOKING_NOT_FOUND, 404);
        }

        return $request;
    }

    private function agency(string $token): Agency
    {
        $agency = Agency::query()->where('booking_token', $token)->first();
        if ($agency === null || $agency->bookingServices()->doesntExist() || $agency->bookingHours()->doesntExist()) {
            throw new ApiException(ErrorCodes::BOOKING_NOT_FOUND, 404);
        }

        return $agency;
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function window(Request $request, Agency $agency): array
    {
        $timezone = $agency->timezone ?: 'UTC';
        $today = Carbon::now($timezone)->startOfDay();
        $from = $request->query('from');
        $to = $request->query('to');
        if (! is_string($from) && ! is_string($to)) {
            return [$today, $today->copy()->addMonthNoOverflow()->endOfMonth()];
        }
        if (! is_string($from) || ! is_string($to)) {
            throw new ApiException(ErrorCodes::BOOKING_INVALID_RANGE, 422);
        }

        try {
            $start = Carbon::createFromFormat('!Y-m-d', $from, $timezone)->startOfDay();
            $end = Carbon::createFromFormat('!Y-m-d', $to, $timezone)->endOfDay();
        } catch (\Throwable) {
            throw new ApiException(ErrorCodes::BOOKING_INVALID_RANGE, 422);
        }

        if ($end->lt($start) || $start->diffInDays($end) > 62) {
            throw new ApiException(ErrorCodes::BOOKING_INVALID_RANGE, 422);
        }
        if ($start->lt($today)) {
            $start = $today->copy();
        }

        return [$start, $end];
    }
}
