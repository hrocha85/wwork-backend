<?php

namespace App\Actions\Auth;

use App\Enums\AnnualDiscount;
use App\Enums\BillingInterval;
use App\Enums\Locale;
use App\Enums\MembershipRole;
use App\Enums\PlanCode;
use App\Enums\SubscriptionStatus;
use App\Enums\Trade;
use App\Mail\OwnerWelcomeMail;
use App\Models\Agency;
use App\Models\Membership;
use App\Models\Subscription;
use App\Models\User;
use App\Services\SeatPlan;
use App\Services\Stripe\StripeBilling;
use App\Support\ApiException;
use App\Support\ErrorCodes;
use App\Support\MailNotifier;
use App\Support\RecordActivity;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class RegisterOwner
{
    public function __construct(
        private StripeBilling $stripe,
        private SeatPlan $seats,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function __invoke(array $data): User
    {
        if (($data['terms_accepted'] ?? null) !== true) {
            throw new ApiException(ErrorCodes::REGISTER_TERMS_REQUIRED, 422);
        }

        $trade = Trade::tryFrom((string) ($data['trade'] ?? ''));
        if ($trade === null) {
            throw new ApiException(ErrorCodes::REGISTER_INVALID_TRADE, 422);
        }

        $locale = Locale::tryFrom((string) ($data['locale'] ?? ''));
        if ($locale === null) {
            throw new ApiException(ErrorCodes::ME_INVALID_LOCALE, 422);
        }

        if (User::query()->where('email', $data['email'])->exists()) {
            throw new ApiException(ErrorCodes::REGISTER_EMAIL_TAKEN, 422);
        }

        if (! $this->stripe->available()) {
            throw new ApiException(ErrorCodes::REGISTER_PAYMENT_UNAVAILABLE, 503);
        }

        $billing = BillingInterval::tryFrom((string) ($data['billing'] ?? BillingInterval::Monthly->value));
        if ($billing === null) {
            throw new ApiException(ErrorCodes::SUBSCRIPTION_INVALID_PLAN, 422);
        }

        $paymentMethod = (string) ($data['payment_method'] ?? '');
        $offer = false;
        ['intro' => $price, 'full' => $full] = $this->seats->offerPrices('GB', PlanCode::Basic, BillingInterval::Monthly, $offer);

        try {
            $charge = $this->stripe->subscribeWithOffer(
                $paymentMethod,
                $this->seats->stripePrice($price),
                $offer ? $this->seats->stripePrice($full) : null,
                $billing,
                ['country' => 'GB', 'trade' => $trade->value],
            );
        } catch (\Throwable) {
            throw new ApiException(ErrorCodes::REGISTER_PAYMENT_FAILED, 422);
        }

        $user = DB::transaction(function () use ($data, $locale, $trade, $price, $charge, $billing, $offer): User {
            $agency = Agency::query()->create([
                'name' => $data['agency_name'],
                'timezone' => 'Europe/London',
                'currency' => $price->currency,
                'country' => 'GB',
                'invoice_region' => 'GB',
                'trade' => $trade,
                'utm_source' => $data['utm_source'] ?? null,
                'utm_campaign' => $data['utm_campaign'] ?? null,
            ]);

            $owner = User::query()->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'locale' => $locale,
                'terms_accepted_at' => now(),
                'must_change_password' => false,
            ]);

            Membership::query()->create([
                'agency_id' => $agency->id,
                'user_id' => $owner->id,
                'role' => MembershipRole::Owner,
            ]);

            Subscription::query()->create([
                'agency_id' => $agency->id,
                'plan' => PlanCode::Basic,
                'status' => SubscriptionStatus::Active,
                'seats' => 1,
                'amount_minor' => $price->amount_minor,
                'currency' => $price->currency,
                'billing' => $billing,
                'discount_type' => $offer ? AnnualDiscount::Launch : AnnualDiscount::None,
                'offer_ends_at' => $charge->offerEndsAt !== null ? Carbon::parse($charge->offerEndsAt) : null,
                'stripe_id' => $charge->subscriptionId,
                'stripe_price_id' => $price->stripe_price_id,
                'stripe_schedule_id' => $charge->scheduleId,
                'stripe_status' => 'active',
            ]);

            RecordActivity::add($agency->id, $owner->id, 'auth.registered');

            return $owner;
        });

        app(MailNotifier::class)->toUser('account.welcome', $user, new OwnerWelcomeMail(
            ownerName: $user->name,
            agencyName: (string) $data['agency_name'],
            emailAddress: $user->email,
        ), $user->membership?->agency_id, $user->id);

        Auth::guard('web')->login($user);
        request()->session()->regenerate();

        return $user->fresh(['membership.agency.subscription']);
    }
}
