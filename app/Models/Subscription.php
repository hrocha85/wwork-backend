<?php

namespace App\Models;

use App\Enums\AnnualDiscount;
use App\Enums\BillingInterval;
use App\Enums\PlanCode;
use App\Enums\SubscriptionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Subscription extends Model
{
    protected $fillable = [
        'agency_id',
        'plan',
        'status',
        'seats',
        'amount_minor',
        'currency',
        'billing',
        'discount_type',
        'cancel_at',
        'complimentary_until',
        'stripe_id',
        'stripe_price_id',
        'stripe_status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'plan' => PlanCode::class,
            'status' => SubscriptionStatus::class,
            'seats' => 'integer',
            'amount_minor' => 'integer',
            'billing' => BillingInterval::class,
            'discount_type' => AnnualDiscount::class,
            'cancel_at' => 'datetime',
            'complimentary_until' => 'datetime',
        ];
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }
}
