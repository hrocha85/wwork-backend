<?php

namespace App\Actions\Booking;

use App\Enums\BookingRequestStatus;
use App\Mail\QuoteAnsweredMail;
use App\Models\Agency;
use App\Models\BookingRequest;
use App\Support\ApiException;
use App\Support\ErrorCodes;
use App\Support\MailNotifier;
use Illuminate\Support\Facades\DB;

class AnswerQuote
{
    /**
     * @return array<string, mixed>
     */
    public function __invoke(string $token, string $publicToken, bool $accept): array
    {
        return DB::transaction(function () use ($token, $publicToken, $accept): array {
            $agency = Agency::query()->where('booking_token', $token)->first();
            if ($agency === null) {
                throw new ApiException(ErrorCodes::BOOKING_NOT_FOUND, 404);
            }

            $request = BookingRequest::query()
                ->where('agency_id', $agency->id)
                ->where('public_token', $publicToken)
                ->where('kind', 'quote')
                ->lockForUpdate()
                ->first();

            if ($request === null) {
                throw new ApiException(ErrorCodes::BOOKING_NOT_FOUND, 404);
            }
            if ($request->status !== BookingRequestStatus::Quoted) {
                throw new ApiException(ErrorCodes::BOOKING_NOT_READY, 422);
            }

            if ($accept) {
                app(ScheduleAcceptedRequest::class)($request);
                $request->status = BookingRequestStatus::Approved;
            } else {
                $request->status = BookingRequestStatus::Rejected;
            }
            $request->save();

            app(MailNotifier::class)->toOwner(
                $accept ? 'quote.accepted' : 'quote.rejected',
                $agency,
                new QuoteAnsweredMail($request, $accept),
            );

            return OpenQuote::present($request->fresh('agency'));
        });
    }
}
