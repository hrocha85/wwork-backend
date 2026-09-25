<?php

namespace App\Models;

use App\Enums\AnnualDiscount;
use App\Enums\BillingInterval;
use App\Enums\PlanCode;
use Illuminate\Database\Eloquent\Model;

class PlanPrice extends Model
{
    protected $fillable = [
        'country',
        'plan',
        'billing',
        'discount_type',
        'amount_minor',
        'extra_seat_minor',
        'currency',
        'stripe_price_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'plan' => PlanCode::class,
            'billing' => BillingInterval::class,
            'discount_type' => AnnualDiscount::class,
            'amount_minor' => 'integer',
            'extra_seat_minor' => 'integer',
        ];
    }
}
