<?php

namespace App\Services\Stripe;

use App\Enums\BillingInterval;

class FakeStripeBilling implements StripeBilling
{
    public bool $failPayment = false;

    /** @var list<array<string, mixed>> */
    public array $calls = [];

    public function available(): bool
    {
        return true;
    }

    public function subscribeWithOffer(
        string $paymentMethod,
        string $introPriceId,
        ?string $fullPriceId,
        BillingInterval $billing,
        array $metadata,
    ): StripeResult {
        if ($this->failPayment || $paymentMethod === '') {
            throw new \RuntimeException('card');
        }

        $this->calls[] = ['subscribe', $introPriceId, $fullPriceId, $billing->value];
        $n = count($this->calls) > 1 ? '_'.count($this->calls) : '';

        return new StripeResult(
            customerId: 'cus_test'.$n,
            subscriptionId: 'sub_test'.$n,
            priceId: $introPriceId,
            scheduleId: $fullPriceId === null ? null : 'sub_sched_test',
            offerEndsAt: $fullPriceId === null ? null : now()->add($billing === BillingInterval::Annual ? '1 year' : '12 months')->toIso8601String(),
        );
    }

    public function updateOfferPrices(
        string $subscriptionId,
        ?string $scheduleId,
        string $introPriceId,
        string $fullPriceId,
        int $amountPence,
    ): StripeResult {
        $this->calls[] = ['update_offer', $scheduleId, $introPriceId, $fullPriceId];

        return new StripeResult(
            subscriptionId: $subscriptionId,
            priceId: $introPriceId,
            prorationCreditPence: 1633,
            nextInvoicePence: max(0, $amountPence - 1633),
            scheduleId: $scheduleId,
        );
    }

    public function restartOffer(
        string $subscriptionId,
        ?string $scheduleId,
        string $introPriceId,
        string $fullPriceId,
        BillingInterval $billing,
        int $amountPence,
    ): StripeResult {
        $this->calls[] = ['restart_offer', $scheduleId, $introPriceId, $fullPriceId, $billing->value];

        return new StripeResult(
            subscriptionId: $subscriptionId,
            priceId: $introPriceId,
            prorationCreditPence: 1633,
            nextInvoicePence: max(0, $amountPence - 1633),
            scheduleId: $scheduleId ?? 'sub_sched_test',
            offerEndsAt: now()->add($billing === BillingInterval::Annual ? '1 year' : '12 months')->toIso8601String(),
        );
    }

    public function setupIntent(?string $customerId): StripeResult
    {
        return new StripeResult(clientSecret: 'seti_test_secret');
    }

    public function cancelAtPeriodEnd(string $subscriptionId): StripeResult
    {
        return new StripeResult(cancelAt: '2026-10-25T12:00:00+00:00');
    }

    public function switchPrice(string $subscriptionId, string $priceId, int $amountPence): StripeResult
    {
        $this->calls[] = ['switch', $priceId];

        return new StripeResult(
            subscriptionId: $subscriptionId,
            priceId: $priceId,
            prorationCreditPence: 1633,
            nextInvoicePence: max(0, $amountPence - 1633),
        );
    }

    public function event(string $payload, ?string $signature): array
    {
        $decoded = json_decode($payload, true);

        return is_array($decoded) ? $decoded : ['type' => '', 'data' => []];
    }
}
