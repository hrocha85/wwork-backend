<?php

namespace App\Models;

use App\Enums\ContactChannel;
use App\Models\Concerns\HasSyncUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Client extends Model
{
    use HasSyncUuid, SoftDeletes;

    protected $fillable = [
        'agency_id',
        'created_by',
        'name',
        'phone',
        'contact_channel',
        'email',
        'user_id',
        'avatar_path',
        'join_token',
        'address',
        'lat',
        'lng',
        'note',
        'onesignal_player_id',
        'agenda_token',
        'profile_invite_stage',
        'profile_invite_last_at',
        'profile_invite_declined_at',
        'sync_uuid',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'lat' => 'decimal:7',
            'lng' => 'decimal:7',
            'contact_channel' => ContactChannel::class,
            'profile_invite_stage' => 'integer',
            'profile_invite_last_at' => 'datetime',
            'profile_invite_declined_at' => 'datetime',
        ];
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function visits(): HasMany
    {
        return $this->hasMany(Visit::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }
}
