<?php

namespace App\Services\Stripe;

use App\Enums\BillingInterval;

interface StripeBilling
{
    public function available(): bool;

    /**
     * Cobra a primeira fatura na hora. Com $fullPriceId, cria um schedule de duas fases:
     * $introPriceId por 12 meses (mensal) ou 1 ano (anual), depois $fullPriceId, e solta.
     *
     * @param  array{country: string, trade: string}  $metadata
     */
    public function subscribeWithOffer(
        string $paymentMethod,
        string $introPriceId,
        ?string $fullPriceId,
        BillingInterval $billing,
        array $metadata,
    ): StripeResult;

    /**
     * Troca os preços das fases sem mexer nas datas. Sem schedule ativo, troca o preço da assinatura.
     */
    public function updateOfferPrices(
        string $subscriptionId,
        ?string $scheduleId,
        string $introPriceId,
        string $fullPriceId,
        int $amountPence,
    ): StripeResult;

    /**
     * Recomeça a oferta a partir de agora (ex.: mensal para anual), cobrando a diferença na hora.
     */
    public function restartOffer(
        string $subscriptionId,
        ?string $scheduleId,
        string $introPriceId,
        string $fullPriceId,
        BillingInterval $billing,
        int $amountPence,
    ): StripeResult;

    public function setupIntent(?string $customerId): StripeResult;

    public function cancelAtPeriodEnd(string $subscriptionId): StripeResult;

    public function switchPrice(string $subscriptionId, string $priceId, int $amountPence): StripeResult;

    /**
     * @return array{type: string, data: array<string, mixed>}
     */
    public function event(string $payload, ?string $signature): array;
}
