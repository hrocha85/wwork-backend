<?php

namespace App\Services\Stripe;

final class StripeResult
{
    /**
     * @param  list<array<string, mixed>>  $invoices
     */
    public function __construct(
        public string $customerId = '',
        public string $subscriptionId = '',
        public string $priceId = '',
        public ?string $clientSecret = null,
        public int $prorationCreditPence = 0,
        public int $nextInvoicePence = 0,
        public ?string $cardLast4 = null,
        public ?string $cardBrand = null,
        public ?string $cancelAt = null,
        public array $invoices = [],
        public bool $hasMore = false,
    ) {}
}
