<?php

namespace Tests\Feature;

use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use App\Services\Stripe\FakeStripeBilling;
use App\Services\Stripe\StripeBilling;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function test_register_with_a_card_starts_basic_in_gb(): void
    {
        $this->postJson('/api/v1/register', [
            'agency_name' => 'Maya Cleaning Ltd',
            'name' => 'Maya Silva',
            'email' => 'maya@example.com',
            'password' => 'secret123',
            'locale' => 'en',
            'trade' => 'cleaning',
            'payment_method' => 'pm_card_visa',
            'terms_accepted' => true,
        ])->assertCreated()
            ->assertJsonPath('subscription.plan', 'wwork_basic')
            ->assertJsonPath('subscription.status', 'active')
            ->assertJsonPath('subscription.amount', 4900)
            ->assertJsonPath('agency.country', 'GB')
            ->assertJsonPath('agency.invoice_region', 'GB');
    }
}
