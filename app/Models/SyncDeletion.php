<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SyncDeletion extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'agency_id',
        'table_name',
        'sync_uuid',
    ];

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }
}
