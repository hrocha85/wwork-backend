<?php

namespace App\Models;

use App\Enums\Locale;
use App\Enums\StaffPermissionCode;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable([
    'name',
    'email',
    'password',
    'locale',
    'last_seen_at',
    'must_change_password',
    'onesignal_player_id',
    'terms_accepted_at',
    'last_lat',
    'last_lng',
    'last_located_at',
    'staff_profile_id',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'locale' => Locale::class,
            'last_seen_at' => 'datetime',
            'must_change_password' => 'boolean',
            'terms_accepted_at' => 'datetime',
            'last_lat' => 'decimal:7',
            'last_lng' => 'decimal:7',
            'last_located_at' => 'datetime',
        ];
    }

    public function staffProfile(): BelongsTo
    {
        return $this->belongsTo(StaffProfile::class);
    }

    public function membership(): HasOne
    {
        return $this->hasOne(Membership::class);
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $panel->getId() === 'admin'
            && $this->hasStaffPermission(StaffPermissionCode::ViewAdminPanel);
    }

    public function hasStaffPermission(StaffPermissionCode $permission): bool
    {
        $this->loadMissing('staffProfile.permissions');

        return $this->staffProfile?->permissions->contains(
            fn (StaffPermission $row): bool => $row->code === $permission,
        ) ?? false;
    }
}
