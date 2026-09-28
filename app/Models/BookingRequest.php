<?php

namespace App\Models;

use App\Enums\BookingRequestStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingRequest extends Model
{
    protected $fillable = [
        'agency_id',
        'booking_service_id',
        'client_name',
        'client_phone',
        'requested_date',
        'requested_time',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'requested_date' => 'date',
            'status' => BookingRequestStatus::class,
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
