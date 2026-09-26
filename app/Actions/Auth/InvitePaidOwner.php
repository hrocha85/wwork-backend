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
use App\Support\ApiException;
use App\Support\ErrorCodes;
use App\Support\RecordActivity;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class InvitePaidOwner
{
    public function __construct(private SeatPlan $seats) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function __invoke(array $data, ?int $staffId): User
    {
        $trade = Trade::tryFrom((string) ($data['trade'] ?? ''));
        $plan = PlanCode::tryFrom((string) ($data['plan'] ?? ''));
        if ($trade === null || $plan === null) {
            throw new ApiException(ErrorCodes::SUBSCRIPTION_INVALID_PLAN, 422);
        }

        if (User::query()->where('email', $data['email'])->exists()) {
            throw new ApiException(ErrorCodes::REGISTER_EMAIL_TAKEN, 422);
        }

        $price = $this->seats->price('GB', $plan, BillingInterval::Monthly, AnnualDiscount::None);
        $password = (string) $data['password'];

        $owner = DB::transaction(function () use ($data, $trade, $plan, $price, $password, $staffId): User {
            $agency = Agency::query()->create([
                'name' => $data['agency_name'],
                'timezone' => 'Europe/London',
                'currency' => $price->currency,
                'country' => 'GB',
                'invoice_region' => 'GB',
                'trade' => $trade,
            ]);

            $owner = User::query()->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $password,
                'locale' => Locale::En,
                'must_change_password' => true,
            ]);

            Membership::query()->create([
                'agency_id' => $agency->id,
                'user_id' => $owner->id,
                'role' => MembershipRole::Owner,
            ]);

            Subscription::query()->create([
                'agency_id' => $agency->id,
                'plan' => $plan,
                'status' => SubscriptionStatus::PaidOffline,
                'seats' => 1,
                'amount_minor' => $price->amount_minor,
                'currency' => $price->currency,
                'billing' => BillingInterval::Monthly,
                'discount_type' => AnnualDiscount::None,
                'stripe_price_id' => $price->stripe_price_id,
                'paid_offline_until' => $data['until'] ?? null,
            ]);

            RecordActivity::add($agency->id, $staffId, 'owner.invited', [
                'plan' => $plan->value,
                'until' => $data['until'] ?? null,
            ]);

            return $owner;
        });

        $this->send($owner, $password);

        return $owner;
    }

    public function resend(Agency $agency, ?int $staffId): string
    {
        $owner = $agency->ownerMembership?->user;
        if ($owner === null || $agency->subscription?->status !== SubscriptionStatus::PaidOffline) {
            throw new ApiException(ErrorCodes::SUBSCRIPTION_INVALID_PLAN, 422);
        }

        $password = Str::password(12);
        $owner->password = $password;
        $owner->must_change_password = true;
        $owner->save();

        RecordActivity::add($agency->id, $staffId, 'owner.invite_resent');
        $this->send($owner, $password);

        return $password;
    }

    private function send(User $owner, string $password): void
    {
        $agency = $owner->membership?->agency;
        try {
            Mail::to($owner->email)->queue(new OwnerWelcomeMail(
                ownerName: $owner->name,
                agencyName: (string) $agency?->name,
                emailAddress: $owner->email,
                temporaryPassword: $password,
                appUrl: rtrim((string) config('wwork.frontend_url'), '/').'/login',
            ));
        } catch (\Throwable) {
            RecordActivity::add($agency?->id, null, 'mail.failed');
        }
    }
}
