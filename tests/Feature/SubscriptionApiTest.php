<?php

namespace Tests\Feature;

use App\Enums\AnnualDiscount;
use App\Enums\BillingInterval;
use App\Enums\PlanCode;
use App\Enums\SubscriptionStatus;
use App\Mail\OfferEndingMail;
use App\Models\PlanPrice;
use App\Models\Subscription;
use App\Services\Stripe\FakeStripeBilling;
use App\Services\Stripe\StripeBilling;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SubscriptionApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
        $this->withHeader('referer', config('app.url'));
        $this->withHeader('Accept', 'application/json');
        $this->app->instance(StripeBilling::class, new FakeStripeBilling);
    }

    public function test_payment_failed_blocks_the_next_invite(): void
    {
        $subscription = Subscription::query()->firstOrFail();
        $subscription->stripe_id = 'sub_demo';
        $subscription->save();

        $this->postJson('/api/v1/stripe/webhook', [
            'type' => 'invoice.payment_failed',
            'data' => ['subscription' => 'sub_demo'],
        ])->assertOk();

        $this->assertSame(SubscriptionStatus::PastDue, $subscription->fresh()->status);

        $this->postJson('/api/v1/login', [
            'email' => 'owner@wwork.test',
            'password' => 'demo-seed-test',
        ])->assertOk();

        $this->postJson('/api/v1/partners', [
            'email' => 'new@wwork.test',
            'rate' => 50,
        ])->assertStatus(402)->assertExactJson(['error' => 'subscription.past_due']);
    }

    public function test_register_with_a_card_starts_basic_in_gb_at_the_launch_price(): void
    {
        config(['wwork.launch_offer_ends_at' => '2027-03-31']);
        $this->travelTo('2026-10-01 10:00:00');
        $stripe = new FakeStripeBilling;
        $this->app->instance(StripeBilling::class, $stripe);

        $this->postJson('/api/v1/register', $this->registerBody())
            ->assertCreated()
            ->assertJsonPath('subscription.plan', 'wwork_basic')
            ->assertJsonPath('subscription.status', 'active')
            ->assertJsonPath('subscription.amount', 2990)
            ->assertJsonPath('subscription.billing', 'monthly')
            ->assertJsonPath('subscription.discount_type', 'launch')
            ->assertJsonPath('agency.country', 'GB')
            ->assertJsonPath('agency.invoice_region', 'GB');

        $subscription = Subscription::query()->where('stripe_id', 'sub_test')->firstOrFail();
        $this->assertSame(AnnualDiscount::Launch, $subscription->discount_type);
        $this->assertSame('2027-10-01', $subscription->offer_ends_at->toDateString());
        $this->assertSame('sub_sched_test', $subscription->stripe_schedule_id);
        $this->assertSame('subscribe', $stripe->calls[0][0]);
        $this->assertNotNull($stripe->calls[0][2]);
    }

    public function test_monthly_after_the_launch_window_starts_at_full_price_but_annual_keeps_the_first_year_offer(): void
    {
        config(['wwork.launch_offer_ends_at' => '2027-03-31']);
        $this->travelTo('2027-04-01 09:00:00');
        $stripe = new FakeStripeBilling;
        $this->app->instance(StripeBilling::class, $stripe);

        $this->postJson('/api/v1/register', $this->registerBody())
            ->assertCreated()
            ->assertJsonPath('subscription.amount', 4990)
            ->assertJsonPath('subscription.discount_type', 'none')
            ->assertJsonPath('subscription.offer_ends_at', null);
        $this->assertNull($stripe->calls[0][2]);

        $this->postJson('/api/v1/logout')->assertNoContent();

        $this->postJson('/api/v1/register', $this->registerBody([
            'email' => 'annual@example.com',
            'billing' => 'annual',
        ]))
            ->assertCreated()
            ->assertJsonPath('subscription.amount', 23880)
            ->assertJsonPath('subscription.billing', 'annual')
            ->assertJsonPath('subscription.discount_type', 'launch');
        $this->assertSame('annual', $stripe->calls[1][3]);

        $this->postJson('/api/v1/register', $this->registerBody([
            'email' => 'weekly@example.com',
            'billing' => 'weekly',
        ]))->assertStatus(422)->assertExactJson(['error' => 'subscription.invalid_plan']);
    }

    public function test_setup_returns_monthly_and_annual_options_with_the_full_price(): void
    {
        config(['wwork.launch_offer_ends_at' => '2027-03-31']);
        $this->travelTo('2026-10-01 10:00:00');

        $this->postJson('/api/v1/setup-intent')
            ->assertOk()
            ->assertJsonPath('client_secret', 'seti_test_secret')
            ->assertJsonPath('max_seats', 3)
            ->assertJsonPath('launch_ends_at', '2027-03-31')
            ->assertJsonPath('options.monthly.amount_minor', 2990)
            ->assertJsonPath('options.monthly.full_minor', 4990)
            ->assertJsonPath('options.monthly.offer', true)
            ->assertJsonPath('options.annual.amount_minor', 23880)
            ->assertJsonPath('options.annual.full_minor', 59880)
            ->assertJsonPath('options.annual.monthly_equivalent_minor', 1990);

        $this->getJson('/api/v1/pricing')
            ->assertOk()
            ->assertJsonPath('launch_open', true)
            ->assertJsonPath('options.monthly.amount_minor', 2990)
            ->assertJsonPath('tiers.1.monthly_minor', 4790)
            ->assertJsonPath('tiers.2.annual_full_minor', 131880);
    }

    public function test_upgrade_during_the_offer_keeps_the_launch_price_and_the_end_date(): void
    {
        $stripe = new FakeStripeBilling;
        $this->app->instance(StripeBilling::class, $stripe);
        $subscription = $this->launchSubscription();
        $ends = $subscription->offer_ends_at->toIso8601String();
        $this->loginOwner();

        $this->getJson('/api/v1/subscription/plans')
            ->assertOk()
            ->assertJsonPath('current.discount_type', 'launch')
            ->assertJsonPath('current.step_up_minor', 4990)
            ->assertJsonPath('tiers.0.monthly_minor', 2990)
            ->assertJsonPath('tiers.0.monthly_full_minor', 4990)
            ->assertJsonPath('next_tier_price', 4790);

        $this->postJson('/api/v1/subscription/upgrade', ['plan' => 'wwork_pro'])
            ->assertOk()
            ->assertJsonPath('amount', 4790)
            ->assertJsonPath('discount_type', 'launch');

        $subscription->refresh();
        $this->assertSame($ends, $subscription->offer_ends_at->toIso8601String());
        $this->assertSame('update_offer', $stripe->calls[0][0]);
        $this->assertSame('sub_sched_demo', $stripe->calls[0][1]);
    }

    public function test_monthly_owner_moving_to_annual_gets_the_launch_year_even_after_the_window(): void
    {
        config(['wwork.launch_offer_ends_at' => '2026-01-31']);
        $stripe = new FakeStripeBilling;
        $this->app->instance(StripeBilling::class, $stripe);
        $subscription = Subscription::query()->firstOrFail();
        $subscription->update(['stripe_id' => 'sub_demo', 'status' => SubscriptionStatus::Active]);
        $this->loginOwner();

        $this->postJson('/api/v1/subscription/annual', ['discount_type' => 'launch'])
            ->assertOk()
            ->assertJsonPath('amount', 23880)
            ->assertJsonPath('discount_type', 'launch');

        $subscription->refresh();
        $this->assertSame(BillingInterval::Annual, $subscription->billing);
        $this->assertTrue($subscription->offer_ends_at->isFuture());
        $this->assertSame('restart_offer', $stripe->calls[0][0]);

        $this->postJson('/api/v1/subscription/annual', ['discount_type' => 'launch'])
            ->assertStatus(422);
    }

    public function test_webhook_moves_the_subscription_to_full_price_when_the_offer_ends(): void
    {
        $subscription = $this->launchSubscription();
        $full = PlanPrice::query()
            ->where('plan', PlanCode::Basic)
            ->where('billing', BillingInterval::Monthly)
            ->where('discount_type', AnnualDiscount::None)
            ->firstOrFail();
        $full->update(['stripe_price_id' => 'price_full_basic']);

        $this->postJson('/api/v1/stripe/webhook', [
            'type' => 'customer.subscription.updated',
            'data' => ['subscription' => 'sub_demo', 'price' => 'price_full_basic', 'schedule' => null],
        ])->assertOk();

        $subscription->refresh();
        $this->assertSame(4990, $subscription->amount_minor);
        $this->assertSame(AnnualDiscount::None, $subscription->discount_type);
        $this->assertNull($subscription->offer_ends_at);
        $this->assertNull($subscription->stripe_schedule_id);
        $this->assertDatabaseHas('activities', ['action' => 'subscription.offer_ended']);
    }

    public function test_owner_gets_one_email_30_days_before_the_price_steps_up(): void
    {
        Mail::fake();
        $subscription = $this->launchSubscription();
        $subscription->update(['offer_ends_at' => now()->addDays(40)]);

        $this->artisan('subscriptions:remind-offer-ending')->assertSuccessful();
        Mail::assertNothingSent();

        $subscription->update(['offer_ends_at' => now()->addDays(29)]);
        $this->artisan('subscriptions:remind-offer-ending')->assertSuccessful();
        $this->artisan('subscriptions:remind-offer-ending')->assertSuccessful();

        Mail::assertSent(OfferEndingMail::class, 1);
        Mail::assertSent(OfferEndingMail::class, function (OfferEndingMail $mail): bool {
            return $mail->hasTo('owner@wwork.test')
                && $mail->today === '£29.90'
                && $mail->next === '£49.90'
                && ! $mail->annual;
        });
        $this->assertNotNull($subscription->fresh()->offer_reminded_at);
    }

    private function launchSubscription(): Subscription
    {
        $subscription = Subscription::query()->firstOrFail();
        $subscription->update([
            'status' => SubscriptionStatus::Active,
            'discount_type' => AnnualDiscount::Launch,
            'amount_minor' => 2990,
            'offer_ends_at' => now()->addMonths(8)->startOfSecond(),
            'stripe_id' => 'sub_demo',
            'stripe_schedule_id' => 'sub_sched_demo',
            'stripe_price_id' => 'price_launch_basic',
        ]);

        return $subscription;
    }

    private function loginOwner(): void
    {
        $this->postJson('/api/v1/login', [
            'email' => 'owner@wwork.test',
            'password' => 'demo-seed-test',
        ])->assertOk();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function registerBody(array $overrides = []): array
    {
        return array_merge([
            'agency_name' => 'Maya Cleaning Ltd',
            'name' => 'Maya Silva',
            'email' => 'maya@example.com',
            'password' => 'secret123',
            'locale' => 'en',
            'trade' => 'cleaning',
            'payment_method' => 'pm_card_visa',
            'terms_accepted' => true,
        ], $overrides);
    }
}
