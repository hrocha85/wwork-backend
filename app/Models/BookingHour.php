<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingHour extends Model
{
    protected $fillable = [
        'agency_id',
        'weekday',
        'starts_at',
        'ends_at',
        'concurrent_slots',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'concurrent_slots' => 'integer',
        ];
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }
}
