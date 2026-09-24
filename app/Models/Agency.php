<?php

namespace App\Models;

use App\Concerns\HasSyncUuid;
use App\Concerns\RecordsSyncDeletions;
use App\Support\SyncDeletionTombstone;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * O perfil de quem limpa por conta.
 *
 * Não é um escritório com vários donos: é sempre uma pessoa dona, que também
 * executa serviço, com convidados pendurados nela. A mensalidade é deste
 * perfil.
 */
class Agency extends Model
{
    use HasFactory;
    use HasSyncUuid;
    use RecordsSyncDeletions;
    use SoftDeletes;

    protected $fillable = [
        'sync_uuid',
        'name',
        'timezone',
        'currency',
    ];

    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }

    public function syncEntityType(): string
    {
        return SyncDeletionTombstone::ENTITY_AGENCY;
    }

    public function syncAgencyId(): ?int
    {
        return (int) $this->getKey();
    }

    /** @return list<int> */
    public function syncAffectedUserIds(): array
    {
        return $this->memberships()
            ->withTrashed()
            ->pluck('user_id')
            ->map(static fn ($id) => (int) $id)
            ->values()
            ->all();
    }
}
