<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Invite extends Model
{
    public const TTL_DAYS = 7;

    protected $fillable = [
        'agency_id',
        'invited_by',
        'email',
        'token',
        'token_hash',
        'rate',
        'expires_at',
        'sent_at',
        'accepted_at',
        'cancelled_at',
    ];

    protected $hidden = [
        'token',
        'token_hash',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rate' => 'integer',
            'expires_at' => 'datetime',
            'sent_at' => 'datetime',
            'accepted_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public static function hashToken(string $plain): string
    {
        return hash('sha256', $plain);
    }

    /**
     * Gera um token novo, guarda só o hash e devolve o texto puro para o e-mail.
     */
    public function rotateToken(): string
    {
        $plain = Str::random(64);
        $this->token = null;
        $this->token_hash = self::hashToken($plain);

        return $plain;
    }

    /**
     * Convites emitidos antes do hash guardam o token em claro e continuam
     * válidos até vencer. Um hash nunca casa com o ramo antigo, porque ali
     * token_hash é nulo.
     */
    public static function findByPlainToken(string $plain): ?self
    {
        return self::query()
            ->where('token_hash', self::hashToken($plain))
            ->orWhere(fn ($query) => $query->whereNull('token_hash')->where('token', $plain))
            ->first();
    }

    public function url(string $plain): string
    {
        return config('wwork.frontend_url').'/invites/'.$plain;
    }
}
