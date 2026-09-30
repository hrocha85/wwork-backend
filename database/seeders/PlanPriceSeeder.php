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
     * Preços criados na conta Stripe de teste (lookup_key wwork_gb_{plano}_{billing}_{desconto}).
     * Em live os preços são outros: criar com o mesmo lookup_key e preencher pelo painel.
     */
    private const TEST_STRIPE_PRICES = [
        'basic.monthly.none' => 'price_1UL88dGrfoSb48KSCLxfg54g',
        'basic.monthly.launch' => 'price_1UL88dGrfoSb48KSeM8D1tKW',
        'basic.annual.none' => 'price_1UL88eGrfoSb48KST34IN2hr',
        'basic.annual.launch' => 'price_1UL88fGrfoSb48KS7kdV2nIP',
        'pro.monthly.none' => 'price_1UL88kGrfoSb48KS1QA0lIXG',
        'pro.monthly.launch' => 'price_1UL88lGrfoSb48KStfYFL8ju',
        'pro.annual.none' => 'price_1UL88mGrfoSb48KSz6fgyw6c',
        'pro.annual.launch' => 'price_1UL88mGrfoSb48KSXvLSnVwP',
        'business.monthly.none' => 'price_1UL88nGrfoSb48KSDKVLAveC',
        'business.monthly.launch' => 'price_1UL88oGrfoSb48KSPCYjP9Np',
        'business.annual.none' => 'price_1UL88pGrfoSb48KSDC23zPyb',
        'business.annual.launch' => 'price_1UL88qGrfoSb48KSfAg2KLLT',
    ];

    /**
     * Catálogo GB. A cobrança lê esta tabela. Não copiar estes valores para o SeatPlan.
     * Lançamento = 60% do cheio no mensal; anual de lançamento = 40% do cheio x 12.
     */
    public function run(): void
    {
        $tiers = [
            PlanCode::Basic->value => ['full' => 4990, 'launch' => 2990, 'annual_launch' => 23880, 'seat' => null],
            PlanCode::Pro->value => ['full' => 7990, 'launch' => 4790, 'annual_launch' => 38280, 'seat' => null],
            PlanCode::Business->value => ['full' => 10990, 'launch' => 6590, 'annual_launch' => 52680, 'seat' => [990, 590, 390]],
        ];

        foreach ($tiers as $plan => $row) {
            [$seatFull, $seatLaunch, $seatAnnual] = $row['seat'] ?? [null, null, null];

            $this->store($plan, BillingInterval::Monthly, AnnualDiscount::None, $row['full'], $seatFull);
            $this->store($plan, BillingInterval::Monthly, AnnualDiscount::Launch, $row['launch'], $seatLaunch);
            $this->store($plan, BillingInterval::Annual, AnnualDiscount::None, $row['full'] * 12, $seatFull === null ? null : $seatFull * 12);
            $this->store($plan, BillingInterval::Annual, AnnualDiscount::Launch, $row['annual_launch'], $seatAnnual === null ? null : $seatAnnual * 12);

            // Fora da oferta; ficam para as assinaturas anuais antigas.
            $this->store($plan, BillingInterval::Annual, AnnualDiscount::TwoMonthsFree, $row['full'] * 10, null);
            $this->store($plan, BillingInterval::Annual, AnnualDiscount::TwentyPercent, intdiv($row['full'] * 12 * 80, 100), null);
        }
    }

    private function store(
        string $plan,
        BillingInterval $billing,
        AnnualDiscount $discount,
        int $amountMinor,
        ?int $extraSeatMinor,
    ): void {
        $price = PlanPrice::query()->firstOrNew([
            'country' => 'GB',
            'plan' => $plan,
            'billing' => $billing->value,
            'discount_type' => $discount->value,
        ]);

        $price->amount_minor = $amountMinor;
        $price->extra_seat_minor = $extraSeatMinor;
        $price->currency = 'GBP';

        if ($price->stripe_price_id === null) {
            $price->stripe_price_id = $this->testPrice($plan, $billing, $discount);
        }

        $price->save();
    }

    private function testPrice(string $plan, BillingInterval $billing, AnnualDiscount $discount): ?string
    {
        if (! str_contains((string) config('services.stripe.secret'), '_test_')) {
            return null;
        }

        $short = str_replace('wwork_', '', $plan);

        return self::TEST_STRIPE_PRICES[$short.'.'.$billing->value.'.'.$discount->value] ?? null;
    }
}
