<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

trait HasSyncUuid
{
    protected static function bootHasSyncUuid(): void
    {
        static::creating(function (Model $model): void {
            if (! filled($model->getAttribute('sync_uuid'))) {
                $model->setAttribute('sync_uuid', (string) Str::uuid());
            }
        });
    }
}
