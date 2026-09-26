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

        $price = $seats->price('GB', PlanCode::Basic, BillingInterval::Monthly, AnnualDiscount::None);
        $secret = $stripe->setupIntent(null);

        return response()->json([
            'client_secret' => $secret->clientSecret,
            'amount_minor' => $price->amount_minor,
            'currency' => $price->currency,
            'max_seats' => PlanCode::Basic->maxSeats(),
        ]);
    }

    public function plans(SeatPlan $seats): JsonResponse
    {
        $agency = $seats->assertOwner();
        $subscription = $agency->subscription;
        $used = $agency->memberships()->count();
        $current = $subscription?->plan ?? PlanCode::Basic;
        $tiers = [];

        foreach (PlanCode::cases() as $plan) {
            $monthly = $seats->price($agency->country, $plan, BillingInterval::Monthly, AnnualDiscount::None);
            $annual = $seats->price($agency->country, $plan, BillingInterval::Annual, AnnualDiscount::TwoMonthsFree);
            $tiers[] = [
                'id' => $plan->value,
                'name' => match ($plan) {
                    PlanCode::Basic => 'Basic',
                    PlanCode::Pro => 'Pro',
                    PlanCode::Business => 'Business',
                },
                'max_seats' => $plan->maxSeats(),
                'monthly_minor' => $monthly->amount_minor,
                'annual_minor' => $annual->amount_minor,
                'currency' => $monthly->currency,
                'current' => $plan === $current,
            ];
        }

        $business = $seats->price($agency->country, PlanCode::Business, BillingInterval::Monthly, AnnualDiscount::None);
        $tiers[] = [
            'id' => 'wwork_business_extra',
            'name' => 'Business + extra seats',
            'max_seats' => null,
            'monthly_minor' => $business->amount_minor,
            'extra_seat_minor' => $business->extra_seat_minor,
            'annual_minor' => $business->amount_minor * 10,
            'currency' => $business->currency,
            'current' => false,
        ];

        $next = match ($current) {
            PlanCode::Basic => PlanCode::Pro,
            PlanCode::Pro => PlanCode::Business,
            PlanCode::Business => null,
        };
        $nextPrice = $next ? $seats->price($agency->country, $next, BillingInterval::Monthly, AnnualDiscount::None) : null;

        return response()->json([
            'current' => [
                'plan' => $current->value,
                'status' => $subscription?->status?->value,
                'seats' => $subscription?->seats,
                'amount' => $subscription?->amount_minor,
                'currency' => $subscription?->currency ?? $agency->currency,
                'country' => $agency->country,
                'billing' => $subscription?->billing?->value,
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
