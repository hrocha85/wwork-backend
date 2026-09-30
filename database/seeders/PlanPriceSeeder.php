<?php

namespace Database\Seeders;

use App\Enums\AnnualDiscount;
use App\Enums\BillingInterval;
use App\Enums\PlanCode;
use App\Models\PlanPrice;
use Illuminate\Database\Seeder;

class PlanPriceSeeder extends Seeder
{
    /**
     * Catálogo GB. A cobrança lê esta tabela. Não copiar estes valores para o SeatPlan.
     */
    public function run(): void
    {
        $monthly = [
            PlanCode::Basic->value => ['amount_minor' => 4900, 'extra_seat_minor' => null],
            PlanCode::Pro->value => ['amount_minor' => 7900, 'extra_seat_minor' => null],
            PlanCode::Business->value => ['amount_minor' => 10900, 'extra_seat_minor' => 1000],
        ];

        foreach ($monthly as $plan => $row) {
            $this->store(
                plan: $plan,
                billing: BillingInterval::Monthly->value,
                discount: AnnualDiscount::None->value,
                amountMinor: $row['amount_minor'],
                extraSeatMinor: $row['extra_seat_minor'],
            );

            $this->store(
                plan: $plan,
                billing: BillingInterval::Annual->value,
                discount: AnnualDiscount::TwoMonthsFree->value,
                amountMinor: $row['amount_minor'] * 10,
                extraSeatMinor: null,
            );

            $this->store(
                plan: $plan,
                billing: BillingInterval::Annual->value,
                discount: AnnualDiscount::TwentyPercent->value,
                amountMinor: intdiv($row['amount_minor'] * 12 * 80, 100),
                extraSeatMinor: null,
            );
        }
    }

    private function store(
        string $plan,
        string $billing,
        string $discount,
        int $amountMinor,
        ?int $extraSeatMinor,
    ): void {
        $price = PlanPrice::query()->firstOrNew([
            'country' => 'GB',
            'plan' => $plan,
            'billing' => $billing,
            'discount_type' => $discount,
        ]);

        $price->amount_minor = $amountMinor;
        $price->extra_seat_minor = $extraSeatMinor;
        $price->currency = 'GBP';

        if (! $price->exists) {
            $price->stripe_price_id = null;
        }

        $price->save();
    }
}
