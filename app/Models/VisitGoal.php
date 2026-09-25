<?php

namespace App\Models;

use App\Models\Concerns\HasSyncUuid;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class VisitGoal extends Model
{
    use HasSyncUuid, SoftDeletes;

    protected $fillable = [
        'visit_id',
        'text',
        'completed',
        'sync_uuid',
    ];

    /**
     * Null means the goal has not been marked yet.
     */
    protected function completed(): Attribute
    {
        return Attribute::make(
            get: function (mixed $value): ?bool {
                if ($value === null) {
                    return null;
                }

                return (bool) $value;
            },
        );
    }

    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }
}
