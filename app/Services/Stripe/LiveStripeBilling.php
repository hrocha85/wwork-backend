<?php

namespace App\Services\Stripe;

use App\Support\ApiException;
use App\Support\ErrorCodes;
use Stripe\StripeClient;
use Stripe\Webhook;

class LiveStripeBilling implements StripeBilling
{
    public function available(): bool
    {
        return filled(config('services.stripe.secret')) && class_exists(StripeClient::class);
    }

    public function subscribeBasic(string $paymentMethod, string $priceId, array $metadata): StripeResult
    {
        $stripe = $this->client();
        $customer = $stripe->customers->create([
            'payment_method' => $paymentMethod,
            'invoice_settings' => ['default_payment_method' => $paymentMethod],
            'metadata' => $metadata,
        ]);
        $subscription = $stripe->subscriptions->create([
            'customer' => $customer->id,
            'items' => [['price' => $priceId]],
            'metadata' => $metadata,
            'default_payment_method' => $paymentMethod,
        ]);

        return new StripeResult(
            customerId: $customer->id,
            subscriptionId: $subscription->id,
            priceId: $priceId,
        );
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
        $subscription = $this->client()->subscriptions->update($subscriptionId, [
            'cancel_at_period_end' => true,
        ]);
        $end = $subscription->cancel_at ?? $subscription->current_period_end;

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
        $upcoming = $stripe->invoices->createPreview([
            'subscription' => $subscriptionId,
        ]);

        return new StripeResult(
            subscriptionId: $updated->id,
            priceId: $priceId,
            prorationCreditPence: max(0, $amountPence - (int) $upcoming->amount_due),
            nextInvoicePence: (int) $upcoming->amount_due,
        );
    }

    public function event(string $payload, ?string $signature): array
    {
        $secret = (string) config('services.stripe.webhook_secret');
        $event = Webhook::constructEvent($payload, (string) $signature, $secret);
        $object = $event->data->object;

        return [
            'type' => $event->type,
            'data' => [
                'id' => $object->id ?? null,
                'subscription' => $object->subscription ?? ($object->id ?? null),
                'metadata' => isset($object->metadata) ? $object->metadata->toArray() : [],
                'status' => $object->status ?? null,
            ],
        ];
    }

    private function client(): StripeClient
    {
        if (! $this->available()) {
            throw new ApiException(ErrorCodes::REGISTER_PAYMENT_UNAVAILABLE, 503);
        }

        return new StripeClient((string) config('services.stripe.secret'));
    }
}
