<?php

namespace App\Services\Stripe;

class FakeStripeBilling implements StripeBilling
{
    public bool $failPayment = false;

    public function available(): bool
    {
        return true;
    }

    public function subscribeBasic(string $paymentMethod, string $priceId, array $metadata): StripeResult
    {
        if ($this->failPayment || $paymentMethod === '') {
            throw new \RuntimeException('card');
        }

        return new StripeResult(
            customerId: 'cus_test',
            subscriptionId: 'sub_test',
            priceId: $priceId,
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
