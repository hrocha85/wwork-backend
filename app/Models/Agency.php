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
        'trade_detail',
        'utm_source',
        'utm_campaign',
        'legal_address',
        'phone',
        'payment_method',
        'payment_details',
        'payment_link',
        'logo_path',
        'vat_registered',
        'tax_id',
        'booking_token',
        'bio',
        'website',
        'public_slug',
        'latitude',
        'longitude',
        'average_rating',
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
            'latitude' => 'decimal:8',
            'longitude' => 'decimal:8',
            'average_rating' => 'decimal:2',
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

    public function bookingServices(): HasMany
    {
        return $this->hasMany(BookingService::class);
    }

    public function bookingHours(): HasMany
    {
        return $this->hasMany(BookingHour::class);
    }

    public function bookingRequests(): HasMany
    {
        return $this->hasMany(BookingRequest::class);
    }

    public function portfolioPhotos(): HasMany
    {
        return $this->hasMany(PortfolioPhoto::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function isPaidMode(): bool
    {
        if ($this->subscription === null) {
            return false;
        }

        if (in_array($this->subscription->status, [
            \App\Enums\SubscriptionStatus::Active,
            \App\Enums\SubscriptionStatus::Complimentary,
            \App\Enums\SubscriptionStatus::PaidOffline,
        ], true)) {
            return true;
        }

        // Paid model in 30-day trial
        if ($this->subscription->status === \App\Enums\SubscriptionStatus::Trial && ! $this->isTrialExpired()) {
            return true;
        }

        return false;
    }

    public function isFreeMode(): bool
    {
        return ! $this->isPaidMode();
    }

    public function hasActiveSubscription(): bool
    {
        if ($this->subscription === null) {
            return false;
        }

        return in_array($this->subscription->status, [
            \App\Enums\SubscriptionStatus::Active,
            \App\Enums\SubscriptionStatus::Complimentary,
            \App\Enums\SubscriptionStatus::PaidOffline,
        ], true);
    }

    public function trialEndsAt(): \Illuminate\Support\Carbon
    {
        if ($this->subscription?->offer_ends_at !== null) {
            return $this->subscription->offer_ends_at;
        }

        return ($this->created_at ?? now())->copy()->addDays(30);
    }

    public function isTrial(): bool
    {
        return $this->subscription?->status === \App\Enums\SubscriptionStatus::Trial;
    }

    public function isTrialExpired(): bool
    {
        if ($this->hasActiveSubscription()) {
            return false;
        }

        if (! $this->isTrial()) {
            return true;
        }

        return now()->greaterThan($this->trialEndsAt());
    }

    public function canCreateRecords(): bool
    {
        return ! $this->isTrialExpired();
    }

    public function trialDaysRemaining(): int
    {
        if ($this->hasActiveSubscription()) {
            return 0;
        }

        if ($this->isTrialExpired()) {
            return 0;
        }

        return (int) ceil(max(0, now()->floatDiffInDays($this->trialEndsAt(), false)));
    }
}

