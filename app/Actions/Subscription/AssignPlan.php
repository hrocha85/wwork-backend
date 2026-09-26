<?php

namespace App\Actions\Subscription;

use App\Enums\PlanCode;
use App\Enums\SubscriptionStatus;
use App\Models\Agency;
use App\Models\Subscription;
use App\Services\SeatPlan;
use App\Services\Stripe\StripeBilling;
use App\Support\ApiException;
use App\Support\ErrorCodes;
use App\Support\RecordActivity;

class AssignPlan
{
    public function __construct(
        private SeatPlan $seats,
        private StripeBilling $stripe,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function __invoke(Agency $agency, array $data, ?int $staffId): Subscription
    {
        $plan = PlanCode::tryFrom((string) ($data['plan'] ?? ''));
        $reason = (string) ($data['reason'] ?? '');
        if ($plan === null || ! in_array($reason, ['pay', 'complimentary', 'correction', 'paid_offline'], true)) {
            throw new ApiException(ErrorCodes::SUBSCRIPTION_INVALID_PLAN, 422);
        }

        $subscription = $agency->subscription;
        if ($subscription === null) {
            throw new ApiException(ErrorCodes::SUBSCRIPTION_INVALID_PLAN, 422);
        }

        $people = $agency->memberships()->count();
        if ($people > $plan->maxSeats() && ! ($plan === PlanCode::Business && $people > 10)) {
            throw new ApiException(ErrorCodes::PLAN_SEATS_EXCEEDED, 422);
        }

        $subscription->plan = $plan;
        $subscription->seats = $people;
        $subscription->amount_minor = $this->seats->amount($agency, $subscription, $people);
        $price = $this->seats->price($agency->country, $plan, $subscription->billing, $subscription->discount_type);
        $subscription->stripe_price_id = $price->stripe_price_id;
        $subscription->currency = $price->currency;

        if ($reason === 'pay' && $subscription->stripe_id && $this->stripe->available()) {
            try {
                $this->stripe->switchPrice(
                    $subscription->stripe_id,
                    (string) ($price->stripe_price_id ?? $plan->value),
                    $subscription->amount_minor,
                );
            } catch (\Throwable) {
                throw new ApiException(ErrorCodes::SUBSCRIPTION_STRIPE_ERROR, 422);
            }
            $subscription->status = SubscriptionStatus::Active;
        }

        if ($reason === 'complimentary') {
            $subscription->status = SubscriptionStatus::Complimentary;
            $subscription->complimentary_until = $data['until'] ?? null;
            $subscription->paid_offline_until = null;
        }

        if ($reason === 'paid_offline') {
            $subscription->status = SubscriptionStatus::PaidOffline;
            $subscription->paid_offline_until = $data['until'] ?? null;
            $subscription->complimentary_until = null;
        }

        $subscription->save();
        RecordActivity::add($agency->id, $staffId, 'subscription.assigned', [
            'reason' => $reason,
            'plan' => $plan->value,
            'until' => $data['until'] ?? null,
        ]);

        return $subscription;
    }
}
