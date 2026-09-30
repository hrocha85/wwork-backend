<?php

namespace App\Actions\Subscription;

use App\Enums\AnnualDiscount;
use App\Enums\SubscriptionStatus;
use App\Models\PlanPrice;
use App\Models\Subscription;
use App\Services\SeatPlan;
use App\Services\Stripe\StripeBilling;
use App\Support\RecordActivity;

class ApplyStripeEvent
{
    public function __construct(private SeatPlan $seats) {}

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
            'customer.subscription.updated' => $this->sync($subscription, $event['data']),
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

    /**
     * Quando a fase 2 do schedule começa, o item passa para o preço cheio.
     *
     * @param  array<string, mixed>  $data
     */
    private function sync(Subscription $subscription, array $data): void
    {
        if (array_key_exists('schedule', $data) && $data['schedule'] === null) {
            $subscription->stripe_schedule_id = null;
        }

        $priceId = $data['price'] ?? null;
        $row = is_string($priceId) && $priceId !== $subscription->stripe_price_id
            ? PlanPrice::query()->where('stripe_price_id', $priceId)->first()
            : null;

        if ($row !== null) {
            $wasOffer = $subscription->discount_type === AnnualDiscount::Launch;
            $subscription->plan = $row->plan;
            $subscription->billing = $row->billing;
            $subscription->discount_type = $row->discount_type;
            $subscription->stripe_price_id = $row->stripe_price_id;
            $subscription->currency = $row->currency;
            $subscription->amount_minor = $this->seats->amount($subscription->agency, $subscription, $subscription->seats);
            if ($row->discount_type !== AnnualDiscount::Launch) {
                $subscription->offer_ends_at = null;
            }
            if ($wasOffer && $row->discount_type === AnnualDiscount::None) {
                RecordActivity::add($subscription->agency_id, null, 'subscription.offer_ended', [
                    'amount' => $subscription->amount_minor,
                ]);
            }
        }

        $subscription->save();
    }
}
