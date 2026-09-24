<?php

namespace App\Models;

use App\Concerns\HasSyncUuid;
use App\Concerns\RecordsSyncDeletions;
use App\Enums\MembershipRole;
use App\Support\SyncDeletionTombstone;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * O vínculo de uma pessoa com um perfil.
 *
 * A taxa de comissão mora aqui porque é dela que a visita copia o número no
 * momento de salvar. A conta em si é fatia 5; o campo nasce agora para o
 * schema não precisar mudar depois.
 */
class Membership extends Model
{
    use HasFactory;
    use HasSyncUuid;
    use RecordsSyncDeletions;
    use SoftDeletes;

    protected $fillable = [
        'sync_uuid',
        'agency_id',
        'user_id',
        'role',
        'commission_rate',
    ];

    protected function casts(): array
    {
        return [
            'role' => MembershipRole::class,
            'commission_rate' => 'integer',
        ];
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function syncEntityType(): string
    {
        return SyncDeletionTombstone::ENTITY_MEMBERSHIP;
    }

    public function syncAgencyId(): ?int
    {
        return $this->agency_id !== null ? (int) $this->agency_id : null;
    }

    /** @return list<int> */
    public function syncAffectedUserIds(): array
    {
        return $this->user_id !== null ? [(int) $this->user_id] : [];
    }
}
