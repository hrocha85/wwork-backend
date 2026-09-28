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
use App\Models\BookingService;
use App\Models\Client;
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
        $this->client($agency, $password);

        $this->command?->info('Demo: owner@wwork.test (owner), invited@wwork.test (invited, rate 60), client phone +447700900123.');
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

    private function client(Agency $agency, string $password): void
    {
        if ($agency->public_slug === null) {
            $agency->public_slug = 'demo-cleaning';
        }
        if ($agency->latitude === null) {
            $agency->latitude = 51.50340000;
            $agency->longitude = -0.12760000;
        }
        if ($agency->bio === null) {
            $agency->bio = 'Field service with a public profile, reviews and photos.';
        }
        $agency->save();

        if ($agency->bookingServices()->doesntExist()) {
            BookingService::query()->create([
                'agency_id' => $agency->id,
                'name' => 'Limpeza',
                'duration_minutes' => 120,
                'price_pence' => 8000,
            ]);
        }

        $phone = '447700900123';
        $user = User::query()->where('phone', $phone)->first();

        if ($user === null) {
            $user = new User;
            $user->password = $password;
            $user->must_change_password = false;
            $user->locale = Locale::En;
            $user->terms_accepted_at = now();
            $user->first_access_at = now();
            $user->phone = $phone;
            $user->email = null;
        }

        $user->name = 'Ana Costa';
        $user->save();

        $house = Client::query()->where('agency_id', $agency->id)->where('whatsapp', '+447700900123')->first();

        if ($house === null) {
            Client::query()->create([
                'agency_id' => $agency->id,
                'created_by' => $agency->ownerMembership?->user_id,
                'name' => 'Ana Costa',
                'whatsapp' => '+447700900123',
                'user_id' => $user->id,
                'address' => '10 Downing Street, London',
                'lat' => 51.5034,
                'lng' => -0.1276,
            ]);

            return;
        }

        $house->user_id = $user->id;
        $house->save();
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
        if ($user->first_access_at === null) {
            $user->first_access_at = now();
        }
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
