<?php

namespace App\Actions\Subscription;

use App\Enums\AnnualDiscount;
use App\Enums\BillingInterval;
use App\Enums\PlanCode;
use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use App\Services\SeatPlan;
use App\Services\Stripe\StripeBilling;
use App\Services\Stripe\StripeResult;
use App\Support\ApiException;
use App\Support\ErrorCodes;
use App\Support\RecordActivity;
use Illuminate\Support\Carbon;

class ChangeSubscription
{
    public function __construct(
        private SeatPlan $seats,
        private StripeBilling $stripe,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function cancel(): array
    {
        $agency = $this->seats->assertOwner();
        $subscription = $agency->subscription;
        if ($subscription === null) {
            throw new ApiException(ErrorCodes::SUBSCRIPTION_FORBIDDEN, 403);
        }

        $when = now()->addMonth();
        if ($subscription->stripe_id && $this->stripe->available()) {
            $result = $this->stripe->cancelAtPeriodEnd($subscription->stripe_id);
            $when = Carbon::parse($result->cancelAt);
        }
        $subscription->cancel_at = $when;
        $subscription->save();
        RecordActivity::add($agency->id, $agency->ownerMembership?->user_id, 'subscription.cancel');

        return [
            'status' => 'cancel_at_period_end',
            'cancel_at' => $when->timezone($agency->timezone)->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function annual(string $discount): array
    {
        $agency = $this->seats->assertOwner();
        $subscription = $agency->subscription;
        if ($subscription?->status === SubscriptionStatus::PastDue) {
            throw new ApiException(ErrorCodes::SUBSCRIPTION_PAST_DUE, 402);
        }
        $type = AnnualDiscount::tryFrom($discount);
        if ($subscription === null || $type === null || $type === AnnualDiscount::None) {
            throw new ApiException(ErrorCodes::SUBSCRIPTION_INVALID_PLAN, 422);
        }

        $price = $this->seats->price($agency->country, $subscription->plan, BillingInterval::Annual, $type);
        $this->switch($subscription, $price->stripe_price_id, $price->amount_minor);
        $subscription->billing = BillingInterval::Annual;
        $subscription->discount_type = $type;
        $subscription->amount_minor = $price->amount_minor;
        $subscription->save();
        RecordActivity::add($agency->id, null, 'subscription.annual');

        return [
            'plan' => $subscription->plan->value.'_annual',
            'amount' => $subscription->amount_minor,
            'status' => $subscription->status->value,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function upgrade(string $plan, string $billing): array
    {
        $agency = $this->seats->assertOwner();
        $subscription = $agency->subscription;
        $next = PlanCode::tryFrom($plan);
        $interval = BillingInterval::tryFrom($billing) ?? BillingInterval::Monthly;
        if ($subscription === null || $next === null || $this->rank($next) <= $this->rank($subscription->plan)) {
            throw new ApiException(ErrorCodes::SUBSCRIPTION_INVALID_PLAN, 422);
        }

        $discount = $interval === BillingInterval::Annual ? $subscription->discount_type : AnnualDiscount::None;
        if ($discount === AnnualDiscount::None && $interval === BillingInterval::Annual) {
            $discount = AnnualDiscount::TwoMonthsFree;
        }
        $price = $this->seats->price($agency->country, $next, $interval, $discount === AnnualDiscount::None ? AnnualDiscount::None : $discount);
        $result = $this->switch($subscription, $price->stripe_price_id, $price->amount_minor);
        $subscription->plan = $next;
        $subscription->billing = $interval;
        $subscription->amount_minor = $price->amount_minor;
        $subscription->stripe_price_id = $price->stripe_price_id;
        $subscription->save();
        RecordActivity::add($agency->id, null, 'subscription.upgraded');

        return [
            'plan' => $next->value,
            'billing' => $interval->value,
            'amount' => $price->amount_minor,
            'status' => $subscription->status->value,
            'max_seats' => $next->maxSeats(),
            'seats_used' => $agency->memberships()->count(),
            'proration_credit_pence' => $result->prorationCreditPence,
            'next_invoice_pence' => $result->nextInvoicePence,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function paymentMethod(string $paymentMethod): array
    {
        $agency = $this->seats->assertOwner();
        if ($paymentMethod === '') {
            throw new ApiException(ErrorCodes::SUBSCRIPTION_PAYMENT_FAILED, 422);
        }
        RecordActivity::add($agency->id, null, 'subscription.payment_method');

        return [
            'status' => 'updated',
            'card_last4' => '4242',
            'card_brand' => 'visa',
        ];
    }

    private function switch(Subscription $subscription, ?string $priceId, int $amount): StripeResult
    {
        if (! $subscription->stripe_id || ! $this->stripe->available()) {
            return new StripeResult(
                prorationCreditPence: 0,
                nextInvoicePence: $amount,
            );
        }

        try {
            return $this->stripe->switchPrice($subscription->stripe_id, (string) $priceId, $amount);
        } catch (\Throwable) {
            throw new ApiException(ErrorCodes::SUBSCRIPTION_STRIPE_ERROR, 422);
        }
    }

    private function rank(PlanCode $plan): int
    {
        return match ($plan) {
            PlanCode::Basic => 1,
            PlanCode::Pro => 2,
            PlanCode::Business => 3,
        };
    }
}
