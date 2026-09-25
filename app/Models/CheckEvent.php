<?php

namespace App\Models;

use App\Enums\CheckEventType;
use App\Models\Concerns\HasSyncUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CheckEvent extends Model
{
    use HasSyncUuid, SoftDeletes;

    protected $fillable = [
        'visit_id',
        'user_id',
        'type',
        'lat',
        'lng',
        'occurred_at',
        'sync_uuid',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => CheckEventType::class,
            'lat' => 'decimal:7',
            'lng' => 'decimal:7',
            'occurred_at' => 'datetime',
        ];
    }

    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
