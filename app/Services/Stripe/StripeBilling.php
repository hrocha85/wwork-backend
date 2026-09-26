<?php

namespace App\Services\Stripe;

interface StripeBilling
{
    public function available(): bool;

    /**
     * @param  array{country: string, trade: string}  $metadata
     */
    public function subscribeBasic(string $paymentMethod, string $priceId, array $metadata): StripeResult;

    public function setupIntent(?string $customerId): StripeResult;

    public function cancelAtPeriodEnd(string $subscriptionId): StripeResult;

    public function switchPrice(string $subscriptionId, string $priceId, int $amountPence): StripeResult;

    /**
     * @return array{type: string, data: array<string, mixed>}
     */
    public function event(string $payload, ?string $signature): array;
}
