<?php

namespace App\Services\Stripe;

use App\Enums\BillingInterval;
use App\Support\ApiException;
use App\Support\ErrorCodes;
use Stripe\StripeClient;
use Stripe\SubscriptionSchedule;
use Stripe\Webhook;

class LiveStripeBilling implements StripeBilling
{
    public function available(): bool
    {
        return filled(config('services.stripe.secret')) && class_exists(StripeClient::class);
    }

    public function subscribeWithOffer(
        string $paymentMethod,
        string $introPriceId,
        ?string $fullPriceId,
        BillingInterval $billing,
        array $metadata,
    ): StripeResult {
        $stripe = $this->client();
        $customer = $stripe->customers->create([
            'payment_method' => $paymentMethod,
            'invoice_settings' => ['default_payment_method' => $paymentMethod],
            'metadata' => $metadata,
        ]);

        if ($fullPriceId === null) {
            $subscription = $stripe->subscriptions->create([
                'customer' => $customer->id,
                'items' => [['price' => $introPriceId]],
                'metadata' => $metadata,
                'default_payment_method' => $paymentMethod,
            ]);
            $this->assertPaid($subscription->status);

            return new StripeResult(
                customerId: $customer->id,
                subscriptionId: $subscription->id,
                priceId: $introPriceId,
            );
        }

        $schedule = $stripe->subscriptionSchedules->create([
            'customer' => $customer->id,
            'start_date' => 'now',
            'end_behavior' => 'release',
            'metadata' => $metadata,
            'default_settings' => [
                'collection_method' => 'charge_automatically',
                'default_payment_method' => $paymentMethod,
            ],
            'phases' => [
                [
                    'items' => [['price' => $introPriceId]],
                    'duration' => $this->offerLength($billing),
                    'metadata' => $metadata,
                ],
                [
                    'items' => [['price' => $fullPriceId]],
                    'duration' => $this->period($billing),
                    'metadata' => $metadata,
                ],
            ],
            'expand' => ['subscription'],
        ]);

        $subscription = $schedule->subscription;
        if (! in_array($subscription->status ?? null, ['active', 'trialing'], true)) {
            $stripe->subscriptionSchedules->cancel($schedule->id);
            $this->assertPaid(null);
        }

        return new StripeResult(
            customerId: $customer->id,
            subscriptionId: (string) $subscription->id,
            priceId: $introPriceId,
            scheduleId: $schedule->id,
            offerEndsAt: date('c', (int) $schedule->phases[0]->end_date),
        );
    }

    public function updateOfferPrices(
        string $subscriptionId,
        ?string $scheduleId,
        string $introPriceId,
        string $fullPriceId,
        int $amountPence,
    ): StripeResult {
        $stripe = $this->client();
        $schedule = $scheduleId === null ? null : $stripe->subscriptionSchedules->retrieve($scheduleId);
        if ($schedule === null || ! $this->running($schedule)) {
            return $this->switchPrice($subscriptionId, $fullPriceId, $amountPence);
        }

        $now = time();
        $phases = [];
        $first = true;
        foreach ($schedule->phases as $phase) {
            if ((int) $phase->end_date <= $now) {
                continue;
            }
            $phases[] = [
                'items' => [['price' => $first && $this->isIntro($schedule, $phase) ? $introPriceId : $fullPriceId]],
                'start_date' => (int) $phase->start_date,
                'end_date' => (int) $phase->end_date,
                'metadata' => $phase->metadata?->toArray() ?? [],
            ];
            $first = false;
        }

        $stripe->subscriptionSchedules->update($schedule->id, [
            'phases' => $phases,
            'proration_behavior' => 'create_prorations',
        ]);

        return $this->preview($subscriptionId, $introPriceId, $amountPence, $schedule->id, (int) $schedule->phases[0]->end_date);
    }

    public function restartOffer(
        string $subscriptionId,
        ?string $scheduleId,
        string $introPriceId,
        string $fullPriceId,
        BillingInterval $billing,
        int $amountPence,
    ): StripeResult {
        $stripe = $this->client();
        $schedule = $scheduleId === null ? null : $stripe->subscriptionSchedules->retrieve($scheduleId);
        if ($schedule === null || ! $this->running($schedule)) {
            $schedule = $stripe->subscriptionSchedules->create(['from_subscription' => $subscriptionId]);
        }

        $current = $schedule->current_phase;
        $start = (int) ($current->start_date ?? time());
        $offerEnd = (int) strtotime($billing === BillingInterval::Annual ? '+1 year' : '+12 months');

        $updated = $stripe->subscriptionSchedules->update($schedule->id, [
            'end_behavior' => 'release',
            'proration_behavior' => 'always_invoice',
            'phases' => [
                [
                    'items' => [['price' => $introPriceId]],
                    'start_date' => $start,
                    'end_date' => $offerEnd,
                    'billing_cycle_anchor' => 'phase_start',
                ],
                [
                    'items' => [['price' => $fullPriceId]],
                    'duration' => $this->period($billing),
                ],
            ],
        ]);

        return $this->preview($subscriptionId, $introPriceId, $amountPence, $updated->id, $offerEnd);
    }

    public function setupIntent(?string $customerId): StripeResult
    {
        $params = ['usage' => 'off_session'];
        if ($customerId !== null) {
            $params['customer'] = $customerId;
        }
        $intent = $this->client()->setupIntents->create($params);

        return new StripeResult(clientSecret: $intent->client_secret);
    }

    public function cancelAtPeriodEnd(string $subscriptionId): StripeResult
    {
        $stripe = $this->client();
        $current = $stripe->subscriptions->retrieve($subscriptionId);
        if (is_string($current->schedule) && $current->schedule !== '') {
            $stripe->subscriptionSchedules->release($current->schedule);
        }
        $subscription = $stripe->subscriptions->update($subscriptionId, [
            'cancel_at_period_end' => true,
        ]);
        $end = $subscription->cancel_at ?? $subscription->items->data[0]->current_period_end ?? time();

        return new StripeResult(cancelAt: date('c', (int) $end));
    }

    public function switchPrice(string $subscriptionId, string $priceId, int $amountPence): StripeResult
    {
        $stripe = $this->client();
        $subscription = $stripe->subscriptions->retrieve($subscriptionId);
        $item = $subscription->items->data[0]->id;
        $updated = $stripe->subscriptions->update($subscriptionId, [
            'items' => [['id' => $item, 'price' => $priceId]],
            'proration_behavior' => 'create_prorations',
        ]);

        return $this->preview($updated->id, $priceId, $amountPence);
    }

    public function event(string $payload, ?string $signature): array
    {
        $secret = (string) config('services.stripe.webhook_secret');
        $event = Webhook::constructEvent($payload, (string) $signature, $secret);
        $object = $event->data->object;
        $isSubscription = ($object->object ?? null) === 'subscription';

        return [
            'type' => $event->type,
            'data' => [
                'id' => $object->id ?? null,
                'subscription' => $object->subscription ?? ($object->id ?? null),
                'metadata' => isset($object->metadata) ? $object->metadata->toArray() : [],
                'status' => $object->status ?? null,
                'price' => $isSubscription ? ($object->items->data[0]->price->id ?? null) : null,
                'schedule' => $isSubscription ? $object->schedule : null,
            ],
        ];
    }

    private function preview(string $subscriptionId, string $priceId, int $amountPence, ?string $scheduleId = null, ?int $offerEnd = null): StripeResult
    {
        $upcoming = $this->client()->invoices->createPreview([
            'subscription' => $subscriptionId,
        ]);

        return new StripeResult(
            subscriptionId: $subscriptionId,
            priceId: $priceId,
            prorationCreditPence: max(0, $amountPence - (int) $upcoming->amount_due),
            nextInvoicePence: (int) $upcoming->amount_due,
            scheduleId: $scheduleId,
            offerEndsAt: $offerEnd === null ? null : date('c', $offerEnd),
        );
    }

    private function running(SubscriptionSchedule $schedule): bool
    {
        return in_array($schedule->status, ['active', 'not_started'], true);
    }

    private function isIntro(SubscriptionSchedule $schedule, object $phase): bool
    {
        return count($schedule->phases) > 1 && (int) $phase->start_date === (int) $schedule->phases[0]->start_date;
    }

    /**
     * @return array{interval: string, interval_count: int}
     */
    private function offerLength(BillingInterval $billing): array
    {
        return $billing === BillingInterval::Annual
            ? ['interval' => 'year', 'interval_count' => 1]
            : ['interval' => 'month', 'interval_count' => 12];
    }

    /**
     * @return array{interval: string, interval_count: int}
     */
    private function period(BillingInterval $billing): array
    {
        return $billing === BillingInterval::Annual
            ? ['interval' => 'year', 'interval_count' => 1]
            : ['interval' => 'month', 'interval_count' => 1];
    }

    private function assertPaid(?string $status): void
    {
        if (! in_array($status, ['active', 'trialing'], true)) {
            throw new \RuntimeException('payment_incomplete');
        }
    }

    private function client(): StripeClient
    {
        if (! $this->available()) {
            throw new ApiException(ErrorCodes::REGISTER_PAYMENT_UNAVAILABLE, 503);
        }

        return new StripeClient((string) config('services.stripe.secret'));
    }
}
