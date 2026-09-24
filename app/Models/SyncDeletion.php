<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A lápide de um registro apagado.
 *
 * Sem timestamps: a única data que importa é a da exclusão, e é ela que o
 * POST /sync/pull do app nativo vai comparar com o lastPulledAt.
 */
class SyncDeletion extends Model
{
    public $timestamps = false;

    protected $table = 'sync_deletions';

    protected $fillable = [
        'entity_type',
        'sync_uuid',
        'server_id',
        'agency_id',
        'affected_user_ids',
        'deleted_at',
    ];

    protected function casts(): array
    {
        return [
            'affected_user_ids' => 'array',
            'deleted_at' => 'datetime',
            'server_id' => 'integer',
            'agency_id' => 'integer',
        ];
    }
}
