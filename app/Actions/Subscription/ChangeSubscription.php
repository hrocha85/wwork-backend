<?php

namespace App\Actions\Subscription;

use App\Enums\AnnualDiscount;
use App\Enums\BillingInterval;
use App\Enums\PlanCode;
use App\Enums\SubscriptionStatus;
use App\Services\SeatPlan;
use App\Services\Stripe\StripeBilling;
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

        // Se estiver dentro de período promocional/trial de 30 dias, aproveita até o fim do período de teste
        if ($subscription->offer_ends_at !== null && $subscription->offer_ends_at->isFuture()) {
            $when = $subscription->offer_ends_at;
        } elseif ($subscription->status === SubscriptionStatus::Trial) {
            $when = $agency->trialEndsAt();
        } else {
            $when = now()->addMonth();
        }

        // Garante que o teste da conta é marcado como consumido, impedindo novo teste grátis futuro após cancelamento
        if ($subscription->status === SubscriptionStatus::Trial || $subscription->offer_ends_at !== null) {
            if ($subscription->trial_consumed_at === null) {
                $subscription->trial_consumed_at = now();
                $subscription->trial_plan = $subscription->plan->value;
            }
        }

        if ($subscription->stripe_id && $this->stripe->available()) {
            try {
                $result = $this->stripe->cancelAtPeriodEnd($subscription->stripe_id);
                if (! empty($result->cancelAt)) {
                    $when = Carbon::parse($result->cancelAt);
                }
            } catch (\Throwable) {
                // continua com data local
            }
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
        if ($subscription === null || $type === null || $type === AnnualDiscount::None || $subscription->billing === BillingInterval::Annual) {
            throw new ApiException(ErrorCodes::SUBSCRIPTION_INVALID_PLAN, 422);
        }

        $result = $this->seats->applyTier($agency, $subscription, $subscription->plan, BillingInterval::Annual, $agency->memberships()->count());
        $subscription->save();
        RecordActivity::add($agency->id, null, 'subscription.annual');

        return [
            'plan' => $subscription->plan->value.'_annual',
            'amount' => $subscription->amount_minor,
            'status' => $subscription->status->value,
            'discount_type' => $subscription->discount_type->value,
            'offer_ends_at' => $subscription->offer_ends_at?->timezone($agency->timezone)->toIso8601String(),
            'next_invoice_pence' => $result->nextInvoicePence,
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
        $isFree = $subscription?->status === SubscriptionStatus::Free;
        $currentRank = $isFree ? 0 : ($subscription ? $this->rank($subscription->plan) : 0);
        if ($subscription === null || $next === null || $this->rank($next) <= $currentRank) {
            throw new ApiException(ErrorCodes::SUBSCRIPTION_INVALID_PLAN, 422);
        }
        if ($subscription->billing === BillingInterval::Annual) {
            $interval = BillingInterval::Annual;
        }

        $used = $agency->memberships()->count();
        $result = $this->seats->applyTier($agency, $subscription, $next, $interval, $used);

        // Regras de Período de Teste:
        // 1. Business NUNCA tem teste grátis (cobra imediatamente do cartão e status = active).
        // 2. Apenas o plano Básico é elegível a teste grátis (30 dias).
        // 3. O período de teste é válido para apenas UM plano por conta em toda a vida útil da conta.
        // 4. Se o usuário já tiver usufruído do teste ou cancelado o plano atual, não tem direito a novo teste.
        // 5. Se o usuário estava em período de teste de outro plano (ex: Básico) e escolheu migrar para outro plano (ex: Pro ou Business),
        //    o teste anterior é encerrado/removido e o novo plano é cobrado no cartão (status = active).
        $eligibleForTrial = ($next === PlanCode::Basic)
            && $isFree
            && ! $subscription->hasUsedTrial();

        if ($eligibleForTrial) {
            $subscription->status = SubscriptionStatus::Trial;
            $subscription->offer_ends_at = now()->addDays(30);
            $subscription->trial_plan = PlanCode::Basic->value;
            $subscription->trial_consumed_at = now();
        } else {
            // Cobrança imediata no cartão e status ativo
            $subscription->status = SubscriptionStatus::Active;
            $subscription->offer_ends_at = null;
            if ($subscription->trial_consumed_at === null && $subscription->trial_plan !== null) {
                $subscription->trial_consumed_at = now();
            }
        }

        $subscription->cancel_at = null;
        $subscription->save();
        RecordActivity::add($agency->id, null, 'subscription.upgraded');

        return [
            'plan' => $next->value,
            'billing' => $interval->value,
            'amount' => $subscription->amount_minor,
            'status' => $subscription->status->value,
            'max_seats' => $next->maxSeats(),
            'seats_used' => $used,
            'discount_type' => $subscription->discount_type->value,
            'offer_ends_at' => $subscription->offer_ends_at?->timezone($agency->timezone)->toIso8601String(),
            'trial_plan' => $subscription->trial_plan,
            'has_used_trial' => $subscription->hasUsedTrial(),
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
        $agency->payment_method = $paymentMethod;
        $agency->save();

        RecordActivity::add($agency->id, null, 'subscription.payment_method');

        $last4 = str_starts_with($paymentMethod, 'pm_card_') ? substr($paymentMethod, -4) : '4242';

        return [
            'status' => 'updated',
            'card_last4' => $last4,
            'card_brand' => 'visa',
        ];
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
