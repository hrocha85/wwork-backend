<?php

namespace App\Filament\Support;

use App\Enums\AnnualDiscount;
use App\Enums\BillingInterval;
use App\Enums\MembershipRole;
use App\Enums\PlanCode;
use App\Enums\SubscriptionStatus;
use App\Enums\Trade;
use App\Models\Subscription;

class PanelLabels
{
    public static function trade(mixed $trade): string
    {
        return self::label('panel.trade.', $trade instanceof Trade ? $trade->value : (string) $trade);
    }

    public static function plan(mixed $plan): string
    {
        return self::label('panel.plan.', $plan instanceof PlanCode ? $plan->value : (string) $plan);
    }

    public static function status(mixed $status): string
    {
        return self::label('panel.status.', $status instanceof SubscriptionStatus ? $status->value : (string) $status);
    }

    public static function statusColor(mixed $status): string
    {
        $value = $status instanceof SubscriptionStatus ? $status->value : (string) $status;

        return match ($value) {
            SubscriptionStatus::Active->value => 'success',
            SubscriptionStatus::PastDue->value => 'warning',
            SubscriptionStatus::Complimentary->value, SubscriptionStatus::PaidOffline->value => 'primary',
            default => 'gray',
        };
    }

    public static function role(mixed $role): string
    {
        return self::label('panel.role.', $role instanceof MembershipRole ? $role->value : (string) $role);
    }

    public static function roleColor(mixed $role): string
    {
        $value = $role instanceof MembershipRole ? $role->value : (string) $role;

        return $value === MembershipRole::Owner->value ? 'primary' : 'success';
    }

    public static function billing(mixed $billing): string
    {
        return self::label('panel.billing_interval.', $billing instanceof BillingInterval ? $billing->value : (string) $billing);
    }

    public static function discount(mixed $discount): string
    {
        $value = $discount instanceof AnnualDiscount ? $discount->value : (string) $discount;
        if ($value === '' || $value === AnnualDiscount::None->value) {
            return '—';
        }

        return self::label('panel.discount.', $value);
    }

    public static function money(int $minor, string $currency): string
    {
        return number_format($minor / 100, 2, '.', '').' '.$currency;
    }

    public static function seatUsage(Subscription $subscription): string
    {
        $plan = $subscription->plan;
        $people = (int) ($subscription->agency->memberships_count ?? $subscription->agency?->memberships()->count() ?? 0);
        if (! $plan instanceof PlanCode) {
            return (string) $people;
        }

        $cap = (string) $plan->maxSeats();
        if ($plan === PlanCode::Business) {
            $cap .= '+';
        }

        return $people.' / '.$cap;
    }

    public static function atCeiling(Subscription $subscription): bool
    {
        $plan = $subscription->plan;
        if (! $plan instanceof PlanCode) {
            return false;
        }

        $people = (int) ($subscription->agency->memberships_count ?? $subscription->agency?->memberships()->count() ?? 0);

        return $people >= $plan->maxSeats();
    }

    public static function attention(Subscription $subscription): ?string
    {
        $soon = now()->addDays(30);

        if ($subscription->status === SubscriptionStatus::PastDue) {
            return __('panel.attention.past_due');
        }

        if ($subscription->cancel_at !== null && $subscription->cancel_at->lte($soon)) {
            return __('panel.attention.cancel');
        }

        if ($subscription->complimentary_until !== null && $subscription->complimentary_until->lte($soon)) {
            return __('panel.attention.complimentary');
        }

        if ($subscription->paid_offline_until !== null && $subscription->paid_offline_until->lte($soon)) {
            return __('panel.attention.paid_offline');
        }

        if (self::atCeiling($subscription)) {
            return __('panel.attention.full');
        }

        return null;
    }

    public static function campaign(?string $campaign, ?string $source): string
    {
        $value = filled($campaign) ? $campaign : (filled($source) ? $source : 'Direct');

        return $value === 'Direct' ? __('panel.campaign.direct') : $value;
    }

    /**
     * @return array<string, string>
     */
    public static function trades(): array
    {
        $options = [];
        foreach (Trade::cases() as $trade) {
            $options[$trade->value] = self::trade($trade);
        }

        return $options;
    }

    /**
     * @return array<string, string>
     */
    public static function plans(): array
    {
        $options = [];
        foreach (PlanCode::cases() as $plan) {
            $options[$plan->value] = self::plan($plan);
        }

        return $options;
    }

    private static function label(string $prefix, string $value): string
    {
        if ($value === '') {
            return '—';
        }

        $key = $prefix.$value;
        $label = __($key);

        return $label === $key ? $value : $label;
    }
}
