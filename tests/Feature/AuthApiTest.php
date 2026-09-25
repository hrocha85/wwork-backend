<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
        $this->withHeader('referer', config('app.url'));
    }

    public function test_csrf_cookie_login_and_me(): void
    {
        $this->get('/sanctum/csrf-cookie')->assertNoContent();

        $this->postJson('/api/v1/login', [
            'email' => 'owner@wwork.test',
            'password' => 'wrong-password',
        ])->assertUnauthorized()->assertExactJson(['error' => 'auth.failed']);

        $this->postJson('/api/v1/login', [
            'email' => 'founder@wwork.app',
            'password' => 'staff-seed-test',
        ])->assertUnauthorized()->assertExactJson(['error' => 'auth.failed']);

        $this->postJson('/api/v1/login', [
            'email' => 'owner@wwork.test',
            'password' => 'demo-seed-test',
        ])
            ->assertOk()
            ->assertJsonPath('user.email', 'owner@wwork.test')
            ->assertJsonPath('user.role', 'owner')
            ->assertJsonPath('user.must_change_password', false)
            ->assertJsonPath('agency.country', 'GB')
            ->assertJsonPath('agency.trade', 'cleaning')
            ->assertJsonPath('agency.currency', 'GBP')
            ->assertJsonPath('agency.timezone', 'Europe/London');

        $this->assertDatabaseHas('activities', ['action' => 'auth.login']);

        $this->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('agency.country', 'GB')
            ->assertJsonPath('agency.invoice_region', 'GB')
            ->assertJsonPath('agency.subscription.plan', 'wwork_basic')
            ->assertJsonPath('agency.subscription.amount', 4900)
            ->assertJsonPath('team_count', 2)
            ->assertJsonPath('max_seats', 3);

        $this->patchJson('/api/v1/me', ['locale' => 'nope'])
            ->assertStatus(422)
            ->assertExactJson(['error' => 'me.invalid_locale']);

        $this->patchJson('/api/v1/me', ['locale' => 'pt'])
            ->assertOk()
            ->assertJsonPath('user.locale', 'pt');

        $this->postJson('/api/v1/logout')->assertNoContent();

        $this->getJson('/api/v1/me')
            ->assertUnauthorized()
            ->assertExactJson(['error' => 'unauthenticated']);
    }

    public function test_register_refuses_without_stripe_and_keeps_domain_errors(): void
    {
        $before = User::query()->count();

        $this->postJson('/api/v1/register', $this->registerBody(['terms_accepted' => false]))
            ->assertStatus(422)
            ->assertExactJson(['error' => 'register.terms_required']);

        $this->postJson('/api/v1/register', $this->registerBody(['trade' => 'plumbing']))
            ->assertStatus(422)
            ->assertExactJson(['error' => 'register.invalid_trade']);

        $this->postJson('/api/v1/register', $this->registerBody(['email' => 'owner@wwork.test']))
            ->assertStatus(422)
            ->assertExactJson(['error' => 'register.email_taken']);

        $this->postJson('/api/v1/register', $this->registerBody())
            ->assertStatus(503)
            ->assertExactJson(['error' => 'register.payment_unavailable']);

        $this->assertSame($before, User::query()->count());
    }

    public function test_password_change_is_required_before_locale(): void
    {
        $owner = User::query()->where('email', 'owner@wwork.test')->firstOrFail();
        $owner->forceFill(['must_change_password' => true])->save();

        $this->postJson('/api/v1/login', [
            'email' => 'owner@wwork.test',
            'password' => 'demo-seed-test',
        ])->assertOk()->assertJsonPath('user.must_change_password', true);

        $this->getJson('/api/v1/me')->assertOk();

        $this->patchJson('/api/v1/me', ['locale' => 'pt'])
            ->assertForbidden()
            ->assertExactJson(['error' => 'auth.must_change_password']);

        $this->postJson('/api/v1/password/change', [
            'current_password' => 'demo-seed-test',
            'password' => 'new-pass-1',
            'password_confirmation' => 'other-pass',
        ])->assertStatus(422)->assertExactJson(['error' => 'auth.password_mismatch']);

        $this->postJson('/api/v1/password/change', [
            'current_password' => 'nope-nope',
            'password' => 'new-pass-1',
            'password_confirmation' => 'new-pass-1',
        ])->assertStatus(422)->assertExactJson(['error' => 'auth.current_password']);

        $this->postJson('/api/v1/password/change', [
            'current_password' => 'demo-seed-test',
            'password' => 'new-pass-1',
            'password_confirmation' => 'new-pass-1',
        ])->assertOk();

        $this->patchJson('/api/v1/me', ['locale' => 'es'])
            ->assertOk()
            ->assertJsonPath('user.locale', 'es');
    }

    public function test_forgot_and_reset_password(): void
    {
        Notification::fake();

        $this->postJson('/api/v1/forgot-password', ['email' => 'nobody@wwork.test'])
            ->assertOk()
            ->assertExactJson(['ok' => true]);

        $this->postJson('/api/v1/forgot-password', ['email' => 'founder@wwork.app'])
            ->assertOk()
            ->assertExactJson(['ok' => true]);

        Notification::assertNothingSent();

        $owner = User::query()->where('email', 'owner@wwork.test')->firstOrFail();

        $this->postJson('/api/v1/forgot-password', ['email' => 'owner@wwork.test'])
            ->assertOk();

        Notification::assertSentTo($owner, ResetPassword::class, function (ResetPassword $notification) use ($owner): bool {
            $url = $notification->toMail($owner)->actionUrl;

            return str_contains($url, config('wwork.frontend_url').'/reset-password?token=');
        });

        $token = Password::broker()->createToken($owner);

        $this->postJson('/api/v1/reset-password', [
            'email' => 'owner@wwork.test',
            'token' => 'not-the-token',
            'password' => 'reset-pass',
            'password_confirmation' => 'reset-pass',
        ])->assertNotFound()->assertExactJson(['error' => 'auth.reset_invalid']);

        DB::table('password_reset_tokens')->where('email', 'owner@wwork.test')->update([
            'created_at' => now()->subHours(2),
        ]);

        $this->postJson('/api/v1/reset-password', [
            'email' => 'owner@wwork.test',
            'token' => $token,
            'password' => 'reset-pass',
            'password_confirmation' => 'reset-pass',
        ])->assertNotFound()->assertExactJson(['error' => 'auth.reset_expired']);

        $token = Password::broker()->createToken($owner);

        $this->postJson('/api/v1/reset-password', [
            'email' => 'owner@wwork.test',
            'token' => $token,
            'password' => 'reset-pass',
            'password_confirmation' => 'reset-pass',
        ])->assertOk();

        $owner->refresh();
        $this->assertTrue(Hash::check('reset-pass', $owner->password));
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
            'payment_method' => 'pm_test',
            'terms_accepted' => true,
        ], $overrides);
    }
}
