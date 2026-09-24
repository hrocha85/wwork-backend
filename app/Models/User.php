<?php

namespace App\Models;

use App\Concerns\HasSyncUuid;
use App\Concerns\RecordsSyncDeletions;
use App\Enums\UserRole;
use App\Support\SyncDeletionTombstone;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use HasSyncUuid;
    use Notifiable;
    use RecordsSyncDeletions;
    use SoftDeletes;

    protected $fillable = [
        'sync_uuid',
        'name',
        'email',
        'password',
        'role',
        'locale',
        'last_seen_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
        ];
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }

    /**
     * O /admin é do fundador. O dono do perfil e o convidado nunca entram,
     * mesmo autenticados.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->role === UserRole::Founder;
    }

    /**
     * Atualizado no login e, na fatia 4, também no check-in. É o número que o
     * perfil do dono e o painel do fundador leem.
     */
    public function touchLastSeen(): void
    {
        $this->forceFill(['last_seen_at' => now()])->saveQuietly();
    }

    public function syncEntityType(): string
    {
        return SyncDeletionTombstone::ENTITY_USER;
    }

    public function syncAgencyId(): ?int
    {
        $agencyId = $this->memberships()->withTrashed()->value('agency_id');

        return $agencyId !== null ? (int) $agencyId : null;
    }

    /** @return list<int> */
    public function syncAffectedUserIds(): array
    {
        return [(int) $this->getKey()];
    }
}
