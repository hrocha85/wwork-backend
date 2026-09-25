<?php

namespace App\Models;

use App\Enums\MembershipRole;
use App\Enums\Trade;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Agency extends Model
{
    protected $fillable = [
        'name',
        'timezone',
        'currency',
        'country',
        'invoice_region',
        'trade',
        'utm_source',
        'utm_campaign',
        'legal_address',
        'vat_registered',
        'tax_id',
    ];

    protected static function booted(): void
    {
        static::creating(function (Agency $agency): void {
            if (! filled($agency->invoice_region)) {
                $agency->invoice_region = $agency->country;
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'trade' => Trade::class,
            'vat_registered' => 'boolean',
        ];
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }

    public function ownerMembership(): HasOne
    {
        return $this->hasOne(Membership::class)->where('role', MembershipRole::Owner->value);
    }

    public function clients(): HasMany
    {
        return $this->hasMany(Client::class);
    }

    public function visits(): HasMany
    {
        return $this->hasMany(Visit::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function subscription(): HasOne
    {
        return $this->hasOne(Subscription::class);
    }
}
