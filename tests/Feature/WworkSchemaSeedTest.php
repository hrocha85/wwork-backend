<?php

namespace Tests\Feature;

use App\Enums\AnnualDiscount;
use App\Enums\BillingInterval;
use App\Enums\Locale;
use App\Enums\MembershipRole;
use App\Enums\PlanCode;
use App\Enums\StaffPermissionCode;
use App\Enums\StaffProfileCode;
use App\Enums\SubscriptionStatus;
use App\Enums\Trade;
use App\Enums\VisitStatus;
use App\Models\Agency;
use App\Models\Client;
use App\Models\PlanPrice;
use App\Models\User;
use App\Models\Visit;
use App\Models\VisitGoal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class WworkSchemaSeedTest extends TestCase
{
    use RefreshDatabase;

    public function test_seed_creates_staff_prices_and_local_demo_without_houses(): void
    {
        $this->seedDatabase();

        $founder = User::query()->where('email', 'founder@wwork.app')->first();
        $support = User::query()->where('email', 'support@wwork.app')->first();
        $finance = User::query()->where('email', 'finance@wwork.app')->first();

        $this->assertNotNull($founder);
        $this->assertSame(1, $founder->id);
        $this->assertSame(StaffProfileCode::Founder, $founder->staffProfile->code);
        $this->assertTrue($founder->must_change_password);
        $this->assertNull($founder->terms_accepted_at);
        $this->assertNull($founder->membership);
        $this->assertTrue(Hash::check('staff-seed-test', $founder->password));
        $this->assertSame(Locale::En, $founder->locale);

        $this->assertCount(7, $founder->staffProfile->permissions);
        $this->assertTrue($this->allows($founder, StaffPermissionCode::ManageStaff));
        $this->assertCount(4, $support->staffProfile->permissions);
        $this->assertTrue($this->allows($support, StaffPermissionCode::ResetPassword));
        $this->assertFalse($this->allows($support, StaffPermissionCode::ManagePlans));
        $this->assertFalse($this->allows($support, StaffPermissionCode::ViewRevenue));
        $this->assertCount(5, $finance->staffProfile->permissions);
        $this->assertTrue($this->allows($finance, StaffPermissionCode::ManagePlans));
        $this->assertTrue($this->allows($finance, StaffPermissionCode::ViewRevenue));
        $this->assertFalse($this->allows($finance, StaffPermissionCode::ManageStaff));

        $basic = PlanPrice::query()
            ->where('country', 'GB')
            ->where('plan', PlanCode::Basic)
            ->where('billing', BillingInterval::Monthly)
            ->where('discount_type', AnnualDiscount::None)
            ->first();

        $this->assertNotNull($basic);
        $this->assertSame(4900, $basic->amount_minor);
        $this->assertSame('GBP', $basic->currency);
        $this->assertNull($basic->stripe_price_id);
        $this->assertSame(9, PlanPrice::query()->count());
        $this->assertSame(49000, $this->annual(PlanCode::Basic, AnnualDiscount::TwoMonthsFree));
        $this->assertSame(47040, $this->annual(PlanCode::Basic, AnnualDiscount::TwentyPercent));
        $this->assertSame(1000, PlanPrice::query()
            ->where('plan', PlanCode::Business)
            ->where('billing', BillingInterval::Monthly)
            ->value('extra_seat_minor'));

        $owner = User::query()->where('email', 'owner@wwork.test')->first();
        $invited = User::query()->where('email', 'invited@wwork.test')->first();

        $this->assertNotNull($owner);
        $this->assertNotNull($invited);
        $this->assertFalse($owner->must_change_password);
        $this->assertNotNull($owner->terms_accepted_at);
        $this->assertNull($owner->staff_profile_id);
        $this->assertTrue(Hash::check('demo-seed-test', $owner->password));
        $this->assertSame(MembershipRole::Owner, $owner->membership->role);
        $this->assertNull($owner->membership->rate);

        $agency = $owner->membership->agency;
        $this->assertSame('GB', $agency->country);
        $this->assertSame('GB', $agency->invoice_region);
        $this->assertSame('GBP', $agency->currency);
        $this->assertSame('Europe/London', $agency->timezone);
        $this->assertSame(Trade::Cleaning, $agency->trade);
        $this->assertSame($agency->id, $invited->membership->agency_id);
        $this->assertSame(MembershipRole::Invited, $invited->membership->role);
        $this->assertSame(60, $invited->membership->rate);
        $this->assertSame(0, $agency->clients()->count());
        $this->assertSame(0, $agency->visits()->count());

        $subscription = $agency->subscription;
        $this->assertSame(PlanCode::Basic, $subscription->plan);
        $this->assertSame(SubscriptionStatus::Complimentary, $subscription->status);
        $this->assertSame(2, $subscription->seats);
        $this->assertSame($basic->amount_minor, $subscription->amount_minor);
        $this->assertNull($subscription->stripe_id);
    }

    public function test_production_skips_the_demo_agency(): void
    {
        $this->app['env'] = 'production';
        config(['wwork.seed_demo' => false]);

        $this->seedDatabase();

        $this->assertDatabaseHas('users', ['email' => 'founder@wwork.app']);
        $this->assertDatabaseMissing('users', ['email' => 'owner@wwork.test']);
        $this->assertSame(0, Agency::query()->count());
        $this->assertSame(9, PlanPrice::query()->count());
    }

    public function test_seed_demo_flag_creates_the_demo_agency_in_production(): void
    {
        $this->app['env'] = 'production';
        config(['wwork.seed_demo' => true]);

        $this->seedDatabase();

        $this->assertDatabaseHas('users', ['email' => 'owner@wwork.test']);
        $this->assertDatabaseHas('users', ['email' => 'invited@wwork.test']);
    }

    public function test_invoice_region_copies_the_country_when_omitted(): void
    {
        $agency = Agency::query()->create([
            'name' => 'Region',
            'timezone' => 'Europe/London',
            'currency' => 'GBP',
            'country' => 'GB',
            'trade' => Trade::Cleaning,
        ]);

        $this->assertSame('GB', $agency->invoice_region);
    }

    public function test_wallet_tables_keep_sync_uuid_and_soft_delete(): void
    {
        foreach (['clients', 'visits', 'visit_goals', 'check_events', 'visit_photos', 'invoices', 'payouts', 'memberships'] as $table) {
            $this->assertTrue(Schema::hasColumn($table, 'sync_uuid'));
            $this->assertTrue(Schema::hasColumn($table, 'deleted_at'));
        }

        foreach (['locale', 'last_seen_at', 'must_change_password', 'onesignal_player_id', 'terms_accepted_at', 'last_lat', 'last_lng', 'last_located_at', 'staff_profile_id'] as $column) {
            $this->assertTrue(Schema::hasColumn('users', $column));
        }

        $this->assertTrue(Schema::hasTable('sync_deletions'));
        $this->assertTrue(Schema::hasTable('plan_prices'));
        $this->assertTrue(Schema::hasColumn('invoice_lines', 'visit_id'));
        $this->assertFalse(Schema::hasColumn('invoice_lines', 'sync_uuid'));

        $owner = User::factory()->create();
        $agency = Agency::query()->create([
            'name' => 'Probe',
            'timezone' => 'Europe/London',
            'currency' => 'GBP',
            'country' => 'GB',
            'trade' => Trade::Lawn,
        ]);

        $client = Client::query()->create([
            'agency_id' => $agency->id,
            'created_by' => $owner->id,
            'name' => 'House',
            'whatsapp' => '+447700900123',
            'address' => '1 Test Street, London',
            'lat' => 51.5034,
            'lng' => -0.1276,
        ]);

        $this->assertNotSame('', $client->sync_uuid);

        $visit = Visit::query()->create([
            'agency_id' => $agency->id,
            'client_id' => $client->id,
            'assignee_id' => $owner->id,
            'service_date' => '2026-09-28',
            'service_time' => '09:00:00',
            'price_pence' => 8000,
            'lat' => 51.5034,
            'lng' => -0.1276,
            'status' => VisitStatus::Todo,
        ]);

        $goal = VisitGoal::query()->create([
            'visit_id' => $visit->id,
            'text' => 'Clean kitchen',
        ]);

        $this->assertNull($goal->fresh()->completed);
        $this->assertNotSame('', $visit->sync_uuid);

        $uuid = $client->sync_uuid;
        $client->delete();
        $this->assertSoftDeleted($client);
        $this->assertSame($uuid, Client::withTrashed()->find($client->id)->sync_uuid);
    }

    private function seedDatabase(): void
    {
        $this->artisan('db:seed', [
            '--no-interaction' => true,
            '--force' => true,
        ]);
    }

    private function allows(User $user, StaffPermissionCode $permission): bool
    {
        return $user->staffProfile->permissions->contains(
            fn ($row) => $row->code === $permission,
        );
    }

    private function annual(PlanCode $plan, AnnualDiscount $discount): int
    {
        return (int) PlanPrice::query()
            ->where('plan', $plan)
            ->where('billing', BillingInterval::Annual)
            ->where('discount_type', $discount)
            ->value('amount_minor');
    }
}
