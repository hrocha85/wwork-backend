<?php

namespace App\Models;

use App\Models\Concerns\HasSyncUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class VisitPhoto extends Model
{
    use HasSyncUuid, SoftDeletes;

    protected $fillable = [
        'visit_id',
        'path',
        'sync_uuid',
    ];

    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }
}
