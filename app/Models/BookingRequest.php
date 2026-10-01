<?php

namespace App\Models;

use App\Enums\BookingRequestStatus;
use App\Enums\ContactChannel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class BookingRequest extends Model
{
    protected $fillable = [
        'agency_id',
        'booking_service_id',
        'client_name',
        'client_phone',
        'client_contact_channel',
        'requested_date',
        'requested_time',
        'estimated_end_time',
        'is_recurring',
        'recurring_days',
        'status',
        'kind',
        'address',
        'description',
        'photo_path',
        'quote_pence',
        'quote_note',
        'proposed_date',
        'proposed_time',
        'public_token',
    ];

    protected static function booted(): void
    {
        static::creating(function (BookingRequest $request): void {
            if (! filled($request->public_token)) {
                $request->public_token = Str::random(40);
            }
            if (! filled($request->kind)) {
                $request->kind = 'slot';
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'requested_date' => 'date',
            'proposed_date' => 'date',
            'quote_pence' => 'integer',
            'is_recurring' => 'boolean',
            'recurring_days' => 'array',
            'status' => BookingRequestStatus::class,
            'client_contact_channel' => ContactChannel::class,
        ];
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(BookingService::class, 'booking_service_id');
    }
}
