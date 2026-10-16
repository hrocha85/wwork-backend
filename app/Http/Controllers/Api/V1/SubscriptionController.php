<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Subscription\ApplyStripeEvent;
use App\Actions\Subscription\ChangeSubscription;
use App\Enums\AnnualDiscount;
use App\Enums\BillingInterval;
use App\Enums\PlanCode;
use App\Enums\SubscriptionStatus;
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
            throw new ApiException(ErrorCodes::SUBSCRIPTION_PAYMENT_FAILED, 503);
        }

        $agency = $seats->assertOwner();
        $intent = $stripe->setupIntent($agency->id);
        $offer = $this->offer($seats);

        return response()->json([
            'client_secret' => $intent->clientSecret,
            'amount_minor' => $offer['options']['monthly']['amount_minor'],
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
        $isFree = $subscription?->status === SubscriptionStatus::Free;
        $current = $isFree ? null : ($subscription?->plan ?? PlanCode::Basic);
        $billing = $subscription?->billing ?? BillingInterval::Monthly;
        $inOffer = $subscription?->discount_type === AnnualDiscount::Launch
            && $subscription->offer_ends_at !== null
            && $subscription->offer_ends_at->isFuture();

        $hasUsedTrial = $subscription?->hasUsedTrial() ?? false;
        $isOnTrial = $subscription?->isOnTrial() ?? false;
        $activeTrialPlan = $isOnTrial ? ($subscription?->trial_plan ?? $current?->value) : null;
        $activeTrialEndsAt = $isOnTrial ? $subscription?->offer_ends_at?->timezone($agency->timezone)->toIso8601String() : null;

        $tiers = [];

        foreach (PlanCode::cases() as $plan) {
            $monthly = $seats->offerPrices($agency->country, $plan, BillingInterval::Monthly, $inOffer && $billing === BillingInterval::Monthly);
            $annual = $seats->offerPrices($agency->country, $plan, BillingInterval::Annual, true);

            // Apenas o plano Básico pode ser acessado como plano de teste, e apenas se a conta ainda não usou o teste único
            $hasTrial = ($plan === PlanCode::Basic) && ! $hasUsedTrial;

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
                'current' => ! $isFree && $plan === $current,
                'has_trial' => $hasTrial,
                'is_trial_eligible' => $plan === PlanCode::Basic,
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
            'has_trial' => false,
            'is_trial_eligible' => false,
        ];

        $next = $isFree ? PlanCode::Basic : match ($current) {
            PlanCode::Basic => PlanCode::Pro,
            PlanCode::Pro => PlanCode::Business,
            PlanCode::Business => null,
            default => PlanCode::Basic,
        };
        $nextPrice = $next ? $seats->offerPrices($agency->country, $next, $billing, $inOffer)['intro'] : null;
        $stepUp = ($inOffer && $current) ? $seats->price($agency->country, $current, $billing, AnnualDiscount::None) : null;

        return response()->json([
            'current' => [
                'plan' => $isFree ? 'wwork_free' : $current?->value,
                'name' => $isFree ? 'Plano Gratuito' : match ($current) {
                    PlanCode::Basic => 'Basic',
                    PlanCode::Pro => 'Pro',
                    PlanCode::Business => 'Business',
                    default => 'Basic',
                },
                'is_free' => $isFree,
                'has_payment_method' => filled($agency->payment_method),
                'card' => filled($agency->payment_method) ? [
                    'brand' => 'Visa',
                    'last4' => str_starts_with((string) $agency->payment_method, 'pm_card_') ? substr((string) $agency->payment_method, -4) : '4242',
                    'exp' => '12/28',
                ] : null,
                'status' => $subscription?->status?->value,
                'seats' => $isFree ? 1 : $subscription?->seats,
                'amount' => $isFree ? 0 : $subscription?->amount_minor,
                'currency' => $subscription?->currency ?? $agency->currency,
                'country' => $agency->country,
                'billing' => $billing->value,
                'discount_type' => $subscription?->discount_type?->value,
                'offer_ends_at' => $subscription?->offer_ends_at?->timezone($agency->timezone)->toIso8601String(),
                'step_up_minor' => $stepUp?->amount_minor,
                'cancel_at' => $subscription?->cancel_at?->timezone($agency->timezone)->toIso8601String(),
                'has_used_trial' => $hasUsedTrial,
                'is_on_trial' => $isOnTrial,
                'trial_plan' => $activeTrialPlan,
                'trial_plan_name' => match ($activeTrialPlan) {
                    'wwork_basic', 'basic' => 'Plano Basic',
                    'wwork_pro', 'pro' => 'Plano Pro',
                    'wwork_business', 'business' => 'Plano Business',
                    default => $activeTrialPlan ? ucfirst(str_replace('wwork_', '', $activeTrialPlan)) : null,
                },
                'trial_ends_at' => $activeTrialEndsAt,
            ],
            'tiers' => $tiers,
            'seats_used' => $used,
            'seats_remaining_in_tier' => max(0, ($current ? $current->maxSeats() : 1) - $used),
            'next_tier' => $next?->value,
            'next_tier_price' => $nextPrice?->amount_minor,
            'next_tier_currency' => $nextPrice?->currency,
            'next_tier_seats_needed' => max(0, ($current ? $current->maxSeats() : 1) - $used + 1),
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
