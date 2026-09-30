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
use App\Services\Stripe\StripeResult;
use App\Support\AgencyContext;
use App\Support\ApiException;
use App\Support\ErrorCodes;
use App\Support\RecordActivity;
use Illuminate\Support\Carbon;

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

    public function launchEndsAt(): Carbon
    {
        return Carbon::parse((string) config('wwork.launch_offer_ends_at'), 'Europe/London')->endOfDay();
    }

    public function launchOpen(): bool
    {
        return now()->lessThanOrEqualTo($this->launchEndsAt());
    }

    /**
     * O anual sempre entra com o 1º ano de lançamento; o mensal só dentro da janela.
     */
    public function offerFor(BillingInterval $billing): bool
    {
        return $billing === BillingInterval::Annual || $this->launchOpen();
    }

    /**
     * @return array{intro: PlanPrice, full: PlanPrice}
     */
    public function offerPrices(string $country, PlanCode $plan, BillingInterval $billing, bool $offer): array
    {
        $full = $this->price($country, $plan, $billing, AnnualDiscount::None);

        return [
            'intro' => $offer ? $this->price($country, $plan, $billing, AnnualDiscount::Launch) : $full,
            'full' => $full,
        ];
    }

    public function stripePrice(PlanPrice $price): string
    {
        return (string) ($price->stripe_price_id ?? $price->plan->value.'_'.$price->billing->value.'_'.$price->discount_type->value);
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
            $this->applyTier($agency, $subscription, $needed, $subscription->billing, max($next, $subscription->seats));
            $subscription->save();
            RecordActivity::add($agency->id, null, 'subscription.upgraded');
        }
    }

    /**
     * Troca degrau e/ou intervalo sem perder a oferta: dentro dela, as fases do schedule
     * passam para o preço do novo degrau e a data de fim não muda. Mensal para anual
     * recomeça com o 1º ano de lançamento. Não salva.
     */
    public function applyTier(Agency $agency, Subscription $subscription, PlanCode $plan, BillingInterval $billing, int $seats): StripeResult
    {
        $inOffer = $subscription->discount_type === AnnualDiscount::Launch
            && $subscription->offer_ends_at !== null
            && $subscription->offer_ends_at->isFuture();
        $restart = $billing === BillingInterval::Annual && $subscription->billing !== BillingInterval::Annual;
        $discount = match (true) {
            $inOffer || $restart => AnnualDiscount::Launch,
            $billing === $subscription->billing => $subscription->discount_type,
            default => AnnualDiscount::None,
        };

        $price = $this->price($agency->country, $plan, $billing, $discount);
        $full = $this->price($agency->country, $plan, $billing, AnnualDiscount::None);

        $subscription->plan = $plan;
        $subscription->billing = $billing;
        $subscription->discount_type = $discount;
        $subscription->stripe_price_id = $price->stripe_price_id;
        $subscription->currency = $price->currency;
        $subscription->amount_minor = $this->amount($agency, $subscription, $seats);

        $result = new StripeResult(nextInvoicePence: $subscription->amount_minor);
        if ($subscription->stripe_id && $this->stripe->available()) {
            try {
                $result = match (true) {
                    $restart => $this->stripe->restartOffer(
                        $subscription->stripe_id,
                        $subscription->stripe_schedule_id,
                        $this->stripePrice($price),
                        $this->stripePrice($full),
                        $billing,
                        $subscription->amount_minor,
                    ),
                    $inOffer => $this->stripe->updateOfferPrices(
                        $subscription->stripe_id,
                        $subscription->stripe_schedule_id,
                        $this->stripePrice($price),
                        $this->stripePrice($full),
                        $subscription->amount_minor,
                    ),
                    default => $this->stripe->switchPrice(
                        $subscription->stripe_id,
                        $this->stripePrice($price),
                        $subscription->amount_minor,
                    ),
                };
            } catch (ApiException $e) {
                throw $e;
            } catch (\Throwable) {
                throw new ApiException(ErrorCodes::SUBSCRIPTION_STRIPE_ERROR, 422);
            }
        }

        if ($restart) {
            $subscription->offer_ends_at = $result->offerEndsAt !== null ? Carbon::parse($result->offerEndsAt) : now()->addYear();
            $subscription->offer_reminded_at = null;
        }
        if ($result->scheduleId !== null) {
            $subscription->stripe_schedule_id = $result->scheduleId;
        }

        return $result;
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
