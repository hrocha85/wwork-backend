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
use App\Support\StoredImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class OpenQuote
{
    /**
     * @param  array<string, mixed>  $input
     * @return array{ok: true, public_token: string}
     */
    public function __invoke(string $token, array $input, ?UploadedFile $photo): array
    {
        return DB::transaction(function () use ($token, $input, $photo): array {
            $agency = Agency::query()->where('booking_token', $token)->lockForUpdate()->first();
            if ($agency === null) {
                throw new ApiException(ErrorCodes::BOOKING_NOT_FOUND, 404);
            }

            $agency->loadMissing('subscription');
            if ($agency->subscription?->status === SubscriptionStatus::Cancelled) {
                throw new ApiException(ErrorCodes::SUBSCRIPTION_INACTIVE, 402);
            }

            $owner = $agency->ownerMembership()->first();
            if ($owner === null) {
                throw new ApiException(ErrorCodes::BOOKING_NOT_FOUND, 404);
            }

            $request = BookingRequest::query()->create([
                'agency_id' => $agency->id,
                'booking_service_id' => null,
                'client_name' => $input['name'],
                'client_phone' => $input['whatsapp'],
                'kind' => 'quote',
                'address' => $input['address'],
                'description' => $input['description'],
                'status' => BookingRequestStatus::Pending,
            ]);

            if ($photo !== null) {
                StoredImage::shrink($photo);
                $path = $photo->store('quotes/'.$agency->id, 'local');
                $request->photo_path = $path;
                $request->save();
            }

            RecordActivity::add($agency->id, $owner->user_id, 'booking.requested');

            app(MailNotifier::class)->toOwner('booking.quote_requested', $agency, new BookingRequestedMail($request));

            return ['ok' => true, 'public_token' => $request->public_token];
        });
    }

    /**
     * @return array<string, mixed>
     */
    public static function present(BookingRequest $request): array
    {
        $request->loadMissing('agency');

        return [
            'agency_name' => $request->agency->name,
            'currency' => $request->agency->currency,
            'client_name' => $request->client_name,
            'address' => $request->address,
            'description' => $request->description,
            'status' => $request->status->value,
            'quote_pence' => $request->quote_pence,
            'quote_note' => $request->quote_note,
            'date' => $request->proposed_date?->toDateString(),
            'time' => $request->proposed_time === null ? null : substr((string) $request->proposed_time, 0, 5),
            'has_photo' => filled($request->photo_path),
        ];
    }

    public static function photo(BookingRequest $request): string
    {
        if (! filled($request->photo_path) || ! Storage::disk('local')->exists($request->photo_path)) {
            throw new ApiException(ErrorCodes::NOT_FOUND, 404);
        }

        return $request->photo_path;
    }
}
