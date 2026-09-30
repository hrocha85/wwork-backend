<?php

namespace App\Actions\Subscription;

use App\Enums\AnnualDiscount;
use App\Enums\BillingInterval;
use App\Enums\SubscriptionStatus;
use App\Mail\OfferEndingMail;
use App\Models\Subscription;
use App\Services\SeatPlan;
use App\Support\RecordActivity;
use Illuminate\Support\Facades\Mail;

/**
 * Aviso 30 dias antes da subida do mensal ou da renovação do anual (DMCC Act).
 */
class RemindOfferEnding
{
    public function __construct(private SeatPlan $seats) {}

    public function __invoke(): int
    {
        $sent = 0;
        $subscriptions = Subscription::query()
            ->where('discount_type', AnnualDiscount::Launch->value)
            ->where('status', SubscriptionStatus::Active->value)
            ->whereNull('cancel_at')
            ->whereNull('offer_reminded_at')
            ->whereNotNull('offer_ends_at')
            ->whereBetween('offer_ends_at', [now(), now()->addDays(30)])
            ->with('agency.ownerMembership.user')
            ->get();

        foreach ($subscriptions as $subscription) {
            $agency = $subscription->agency;
            $owner = $agency?->ownerMembership?->user;
            if ($owner === null) {
                continue;
            }

            $next = $subscription->replicate();
            $next->discount_type = AnnualDiscount::None;
            $nextAmount = $this->seats->amount($agency, $next, $subscription->seats);
            $locale = $owner->locale->value;

            Mail::to($owner->email)->send((new OfferEndingMail(
                ownerName: $owner->name,
                agencyName: $agency->name,
                annual: $subscription->billing === BillingInterval::Annual,
                today: $this->money($subscription->amount_minor, $subscription->currency),
                next: $this->money($nextAmount, $subscription->currency),
                date: $subscription->offer_ends_at->timezone($agency->timezone)->locale($locale)->isoFormat('LL'),
                manageUrl: config('wwork.frontend_url').'/subscription/',
            ))->locale($locale));

            $subscription->offer_reminded_at = now();
            $subscription->save();
            RecordActivity::add($agency->id, null, 'subscription.offer_reminded');
            $sent++;
        }

        return $sent;
    }

    private function money(int $minor, string $currency): string
    {
        $symbol = $currency === 'GBP' ? '£' : $currency.' ';

        return $symbol.number_format($minor / 100, 2, '.', ',');
    }
}
