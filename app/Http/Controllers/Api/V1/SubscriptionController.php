<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Subscription\ApplyStripeEvent;
use App\Actions\Subscription\ChangeSubscription;
use App\Enums\AnnualDiscount;
use App\Enums\BillingInterval;
use App\Enums\PlanCode;
use App\Http\Controllers\Controller;
use App\Services\SeatPlan;
use App\Services\Stripe\StripeBilling;
use App\Support\ApiException;
use App\Support\ErrorCodes;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    public function webhook(Request $request, ApplyStripeEvent $apply, StripeBilling $stripe): JsonResponse
    {
        $apply($request->getContent(), $request->header('Stripe-Signature'), $stripe);

        return response()->json(['ok' => true]);
    }

    public function setup(SeatPlan $seats, StripeBilling $stripe): JsonResponse
    {
        if (! $stripe->available()) {
            throw new ApiException(ErrorCodes::REGISTER_PAYMENT_UNAVAILABLE, 503);
        }

        $secret = $stripe->setupIntent(null);
        $offer = $this->offer($seats);

        return response()->json([
            'client_secret' => $secret->clientSecret,
            'amount_minor' => $offer['options'][BillingInterval::Monthly->value]['amount_minor'],
            'currency' => 'GBP',
            'max_seats' => PlanCode::Basic->maxSeats(),
            ...$offer,
        ]);
    }

    public function pricing(SeatPlan $seats): JsonResponse
    {
        $tiers = [];
        foreach (PlanCode::cases() as $plan) {
            $monthly = $seats->offerPrices('GB', $plan, BillingInterval::Monthly, $seats->launchOpen());
            $annual = $seats->offerPrices('GB', $plan, BillingInterval::Annual, true);
            $tiers[] = [
                'id' => $plan->value,
                'max_seats' => $plan->maxSeats(),
                'monthly_minor' => $monthly['intro']->amount_minor,
                'monthly_full_minor' => $monthly['full']->amount_minor,
                'annual_minor' => $annual['intro']->amount_minor,
                'annual_full_minor' => $annual['full']->amount_minor,
            ];
        }

        return response()->json([
            'currency' => 'GBP',
            'max_seats' => PlanCode::Basic->maxSeats(),
            ...$this->offer($seats),
            'tiers' => $tiers,
        ]);
    }

    /**
     * @return array{launch_ends_at: string, launch_open: bool, options: array<string, array<string, mixed>>}
     */
    private function offer(SeatPlan $seats): array
    {
        $options = [];
        foreach (BillingInterval::cases() as $billing) {
            $offer = $seats->offerFor($billing);
            ['intro' => $intro, 'full' => $full] = $seats->offerPrices('GB', PlanCode::Basic, $billing, $offer);
            $options[$billing->value] = [
                'amount_minor' => $intro->amount_minor,
                'full_minor' => $full->amount_minor,
                'offer' => $offer,
                'monthly_equivalent_minor' => $billing === BillingInterval::Annual ? intdiv($intro->amount_minor, 12) : $intro->amount_minor,
            ];
        }

        return [
            'launch_ends_at' => $seats->launchEndsAt()->toDateString(),
            'launch_open' => $seats->launchOpen(),
            'options' => $options,
        ];
    }

    public function plans(SeatPlan $seats): JsonResponse
    {
        $agency = $seats->assertOwner();
        $subscription = $agency->subscription;
        $used = $agency->memberships()->count();
        $current = $subscription?->plan ?? PlanCode::Basic;
        $billing = $subscription?->billing ?? BillingInterval::Monthly;
        $inOffer = $subscription?->discount_type === AnnualDiscount::Launch
            && $subscription->offer_ends_at !== null
            && $subscription->offer_ends_at->isFuture();
        $tiers = [];

        foreach (PlanCode::cases() as $plan) {
            $monthly = $seats->offerPrices($agency->country, $plan, BillingInterval::Monthly, $inOffer && $billing === BillingInterval::Monthly);
            $annual = $seats->offerPrices($agency->country, $plan, BillingInterval::Annual, true);
            $tiers[] = [
                'id' => $plan->value,
                'name' => match ($plan) {
                    PlanCode::Basic => 'Basic',
                    PlanCode::Pro => 'Pro',
                    PlanCode::Business => 'Business',
                },
                'max_seats' => $plan->maxSeats(),
                'monthly_minor' => $monthly['intro']->amount_minor,
                'monthly_full_minor' => $monthly['full']->amount_minor,
                'annual_minor' => $annual['intro']->amount_minor,
                'annual_full_minor' => $annual['full']->amount_minor,
                'currency' => $monthly['full']->currency,
                'current' => $plan === $current,
            ];
        }

        $business = $seats->offerPrices($agency->country, PlanCode::Business, BillingInterval::Monthly, $inOffer && $billing === BillingInterval::Monthly);
        $tiers[] = [
            'id' => 'wwork_business_extra',
            'name' => 'Business + extra seats',
            'max_seats' => null,
            'monthly_minor' => $business['intro']->amount_minor,
            'monthly_full_minor' => $business['full']->amount_minor,
            'extra_seat_minor' => $business['intro']->extra_seat_minor,
            'annual_minor' => $seats->price($agency->country, PlanCode::Business, BillingInterval::Annual, AnnualDiscount::Launch)->amount_minor,
            'currency' => $business['full']->currency,
            'current' => false,
        ];

        $next = match ($current) {
            PlanCode::Basic => PlanCode::Pro,
            PlanCode::Pro => PlanCode::Business,
            PlanCode::Business => null,
        };
        $nextPrice = $next ? $seats->offerPrices($agency->country, $next, $billing, $inOffer)['intro'] : null;
        $stepUp = $inOffer ? $seats->price($agency->country, $current, $billing, AnnualDiscount::None) : null;

        return response()->json([
            'current' => [
                'plan' => $current->value,
                'status' => $subscription?->status?->value,
                'seats' => $subscription?->seats,
                'amount' => $subscription?->amount_minor,
                'currency' => $subscription?->currency ?? $agency->currency,
                'country' => $agency->country,
                'billing' => $billing->value,
                'discount_type' => $subscription?->discount_type?->value,
                'offer_ends_at' => $inOffer ? $subscription->offer_ends_at->timezone($agency->timezone)->toIso8601String() : null,
                'step_up_minor' => $stepUp?->amount_minor,
                'cancel_at' => $subscription?->cancel_at?->timezone($agency->timezone)->toIso8601String(),
            ],
            'tiers' => $tiers,
            'seats_used' => $used,
            'seats_remaining_in_tier' => max(0, $current->maxSeats() - $used),
            'next_tier' => $next?->value,
            'next_tier_price' => $nextPrice?->amount_minor,
            'next_tier_currency' => $nextPrice?->currency,
            'next_tier_seats_needed' => max(0, $current->maxSeats() - $used + 1),
        ]);
    }

    public function cancel(ChangeSubscription $change): JsonResponse
    {
        return response()->json($change->cancel());
    }

    public function annual(Request $request, ChangeSubscription $change): JsonResponse
    {
        return response()->json($change->annual((string) $request->input('discount_type')));
    }

    public function upgrade(Request $request, ChangeSubscription $change): JsonResponse
    {
        return response()->json($change->upgrade(
            (string) $request->input('plan'),
            (string) $request->input('billing', 'monthly'),
        ));
    }

    public function paymentMethod(Request $request, ChangeSubscription $change): JsonResponse
    {
        return response()->json($change->paymentMethod((string) $request->input('payment_method')));
    }

    public function invoices(): JsonResponse
    {
        return response()->json([
            'invoices' => [],
            'has_more' => false,
        ]);
    }
}
