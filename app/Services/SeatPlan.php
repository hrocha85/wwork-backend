<?php

namespace App\Services;

use App\Enums\AnnualDiscount;
use App\Enums\BillingInterval;
use App\Enums\MembershipRole;
use App\Enums\PlanCode;
use App\Enums\SubscriptionStatus;
use App\Models\Agency;
use App\Models\PlanPrice;
use App\Models\Subscription;
use App\Services\Stripe\StripeBilling;
use App\Support\AgencyContext;
use App\Support\ApiException;
use App\Support\ErrorCodes;
use App\Support\RecordActivity;

class SeatPlan
{
    public function __construct(private StripeBilling $stripe) {}

    public function price(string $country, PlanCode $plan, BillingInterval $billing, AnnualDiscount $discount): PlanPrice
    {
        $row = PlanPrice::query()
            ->where('country', $country)
            ->where('plan', $plan->value)
            ->where('billing', $billing->value)
            ->where('discount_type', $discount->value)
            ->first();

        if ($row === null) {
            throw new ApiException(ErrorCodes::SUBSCRIPTION_INVALID_PLAN, 422);
        }

        return $row;
    }

    public function prepareInvite(Agency $agency): void
    {
        if ($agency->subscription?->status === SubscriptionStatus::PastDue) {
            throw new ApiException(ErrorCodes::SUBSCRIPTION_PAST_DUE, 402);
        }
    }

    public function recount(Agency $agency): void
    {
        $subscription = $agency->subscription;
        if ($subscription === null) {
            return;
        }

        $seats = $agency->memberships()->count();
        $subscription->seats = $seats;
        $subscription->amount_minor = $this->amount($agency, $subscription, $seats);
        $subscription->save();
    }

    public function assertOwner(): Agency
    {
        $membership = AgencyContext::membership();
        if ($membership->role !== MembershipRole::Owner) {
            throw new ApiException(ErrorCodes::SUBSCRIPTION_FORBIDDEN, 403);
        }

        return $membership->agency;
    }

    private function assertWritable(?Subscription $subscription, bool $invite): void
    {
        if ($subscription?->status === SubscriptionStatus::PastDue && $invite) {
            throw new ApiException(ErrorCodes::SUBSCRIPTION_PAST_DUE, 402);
        }
    }

    private function fit(Agency $agency, ?Subscription $subscription, int $next): void
    {
        if ($subscription === null) {
            throw new ApiException(ErrorCodes::TEAM_SEAT_LIMIT, 403);
        }

        $needed = $this->tierFor($next);
        $currentMax = $subscription->plan->maxSeats();

        if ($next <= $currentMax && $next <= 10) {
            return;
        }

        if ($needed === $subscription->plan && $next <= 10) {
            return;
        }

        if ($next > 10 && $subscription->plan !== PlanCode::Business && $needed !== PlanCode::Business) {
            throw new ApiException(ErrorCodes::TEAM_SEAT_LIMIT, 403);
        }

        if ($next > $currentMax || $next > 10) {
            $subscription->plan = $needed;
            $subscription->seats = $subscription->seats;
            $subscription->amount_minor = $this->amount($agency, $subscription, max($next, $subscription->seats));
            $price = $this->price(
                $agency->country,
                $needed,
                $subscription->billing,
                $subscription->discount_type,
            );
            if ($subscription->stripe_id && $this->stripe->available()) {
                $this->stripe->switchPrice(
                    $subscription->stripe_id,
                    (string) ($price->stripe_price_id ?? $needed->value),
                    $subscription->amount_minor,
                );
            }
            $subscription->stripe_price_id = $price->stripe_price_id;
            $subscription->save();
            RecordActivity::add($agency->id, null, 'subscription.upgraded');
        }
    }

    public function tierFor(int $seats): PlanCode
    {
        return match (true) {
            $seats <= 3 => PlanCode::Basic,
            $seats <= 6 => PlanCode::Pro,
            default => PlanCode::Business,
        };
    }

    public function amount(Agency $agency, Subscription $subscription, int $seats): int
    {
        $price = $this->price($agency->country, $subscription->plan, $subscription->billing, $subscription->discount_type);
        if ($subscription->plan === PlanCode::Business && $seats > 10) {
            return $price->amount_minor + (($price->extra_seat_minor ?? 0) * ($seats - 10));
        }

        return $price->amount_minor;
    }
}
