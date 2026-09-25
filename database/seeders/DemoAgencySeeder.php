<?php

namespace Database\Seeders;

use App\Enums\AnnualDiscount;
use App\Enums\BillingInterval;
use App\Enums\Locale;
use App\Enums\MembershipRole;
use App\Enums\PlanCode;
use App\Enums\SubscriptionStatus;
use App\Enums\Trade;
use App\Models\Agency;
use App\Models\Membership;
use App\Models\PlanPrice;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

class DemoAgencySeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production') && ! config('wwork.seed_demo')) {
            return;
        }

        $password = $this->password();
        $agency = $this->agency($password);
        $this->partner($agency, $password);

        $this->command?->info('Demo: owner@wwork.test (owner), invited@wwork.test (invited, rate 60). No houses.');
        $this->command?->line('DEMO_SEED_PASSWORD='.$password);
    }

    private function agency(string $password): Agency
    {
        $owner = $this->user('owner@wwork.test', 'Demo Owner', $password);
        $membership = Membership::withTrashed()
            ->where('user_id', $owner->id)
            ->where('role', MembershipRole::Owner->value)
            ->first();

        if ($membership !== null) {
            $this->ensureSubscription($membership->agency);

            return $membership->agency;
        }

        $agency = Agency::query()->create([
            'name' => 'Demo Cleaning',
            'timezone' => 'Europe/London',
            'currency' => 'GBP',
            'country' => 'GB',
            'invoice_region' => 'GB',
            'trade' => Trade::Cleaning,
        ]);

        Membership::query()->create([
            'agency_id' => $agency->id,
            'user_id' => $owner->id,
            'role' => MembershipRole::Owner,
            'rate' => null,
        ]);

        $this->ensureSubscription($agency);

        return $agency;
    }

    private function ensureSubscription(Agency $agency): void
    {
        if ($agency->subscription()->exists()) {
            return;
        }

        $price = PlanPrice::query()
            ->where('country', 'GB')
            ->where('plan', PlanCode::Basic->value)
            ->where('billing', BillingInterval::Monthly->value)
            ->where('discount_type', AnnualDiscount::None->value)
            ->firstOrFail();

        Subscription::query()->create([
            'agency_id' => $agency->id,
            'plan' => PlanCode::Basic,
            'status' => SubscriptionStatus::Complimentary,
            'seats' => 2,
            'amount_minor' => $price->amount_minor,
            'currency' => $price->currency,
            'billing' => BillingInterval::Monthly,
            'discount_type' => AnnualDiscount::None,
        ]);
    }

    private function partner(Agency $agency, string $password): void
    {
        $invited = $this->user('invited@wwork.test', 'Demo Partner', $password);

        $exists = Membership::withTrashed()
            ->where('agency_id', $agency->id)
            ->where('user_id', $invited->id)
            ->exists();

        if ($exists) {
            return;
        }

        Membership::query()->create([
            'agency_id' => $agency->id,
            'user_id' => $invited->id,
            'role' => MembershipRole::Invited,
            'rate' => 60,
        ]);
    }

    private function user(string $email, string $name, string $password): User
    {
        $user = User::query()->firstOrNew(['email' => $email]);

        if (! $user->exists) {
            $user->password = $password;
            $user->must_change_password = false;
            $user->locale = Locale::En;
            $user->terms_accepted_at = now();
        }

        $user->name = $name;
        $user->save();

        return $user;
    }

    private function password(): string
    {
        $password = config('wwork.demo_seed_password');

        if (! is_string($password) || strlen($password) < 8) {
            throw new RuntimeException('DEMO_SEED_PASSWORD must be at least 8 characters.');
        }

        return $password;
    }
}
