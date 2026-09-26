<?php

namespace App\Actions\Subscription;

use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use App\Services\Stripe\StripeBilling;
use App\Support\RecordActivity;

class ApplyStripeEvent
{
    public function __invoke(string $payload, ?string $signature, StripeBilling $stripe): void
    {
        try {
            $event = $stripe->event($payload, $signature);
        } catch (\Throwable) {
            return;
        }

        $subscriptionId = (string) ($event['data']['subscription'] ?? '');
        $subscription = $subscriptionId === ''
            ? null
            : Subscription::query()->where('stripe_id', $subscriptionId)->first();

        if ($subscription === null) {
            return;
        }

        match ($event['type'] ?? '') {
            'invoice.payment_succeeded' => $this->mark($subscription, 'active', 'subscription.payment_succeeded'),
            'invoice.payment_failed' => $this->mark($subscription, 'past_due', 'subscription.payment_failed'),
            'customer.subscription.deleted' => $this->mark($subscription, 'cancelled', 'subscription.deleted'),
            'customer.subscription.updated' => $subscription->save(),
            default => null,
        };
    }

    private function mark(Subscription $subscription, string $status, string $action): void
    {
        $subscription->status = SubscriptionStatus::from($status);
        $subscription->stripe_status = $status;
        $subscription->save();
        RecordActivity::add($subscription->agency_id, null, $action);
    }
}
