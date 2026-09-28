<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OnboardingApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
        $this->withHeader('referer', config('app.url'));
        $this->withHeader('Accept', 'application/json');
    }

    public function test_owner_finishes_first_access_and_invited_cannot(): void
    {
        $this->login('invited@wwork.test');
        $this->postJson('/api/v1/onboarding', [
            'name' => 'Outra',
            'trade' => 'cleaning',
        ])->assertForbidden()->assertExactJson(['error' => 'agency.not_owner']);

        $this->postJson('/api/v1/logout')->assertNoContent();

        $owner = User::query()->where('email', 'owner@wwork.test')->firstOrFail();
        $owner->forceFill(['first_access_at' => null])->save();
        $this->login('owner@wwork.test');

        $this->postJson('/api/v1/onboarding', [
            'name' => 'A',
            'trade' => 'plumbing',
        ])->assertStatus(422)->assertExactJson(['error' => 'onboarding.invalid']);

        $this->postJson('/api/v1/onboarding', [
            'name' => 'Casa Nova',
            'trade' => 'other',
        ])->assertStatus(422)->assertExactJson(['error' => 'onboarding.invalid']);

        $saved = $this->postJson('/api/v1/onboarding', [
            'name' => 'Casa Nova',
            'trade' => 'other',
            'trade_detail' => 'Vidros',
            'legal_address' => '12 High Street',
        ])->assertOk()
            ->assertJsonPath('agency.name', 'Casa Nova')
            ->assertJsonPath('agency.trade', 'other')
            ->assertJsonPath('agency.trade_detail', 'Vidros')
            ->assertJsonPath('agency.legal_address', '12 High Street');

        $this->assertNotNull($saved->json('user.first_access_at'));
        $owner->refresh();
        $this->assertNotNull($owner->first_access_at);
    }

    private function login(string $email): void
    {
        $this->postJson('/api/v1/login', [
            'email' => $email,
            'password' => 'demo-seed-test',
        ])->assertOk();
    }
}
