<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserPreferencesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
        $this->withHeader('referer', config('app.url'));
        $this->withHeader('Accept', 'application/json');
    }

    public function test_user_model_casts_preferences_as_array(): void
    {
        $user = User::query()->where('email', 'invited@wwork.test')->firstOrFail();
        $this->assertNull($user->preferences);

        $user->preferences = ['theme' => 'dark', 'notifications' => ['email' => true]];
        $user->save();

        $refreshed = User::query()->where('email', 'invited@wwork.test')->firstOrFail();
        $this->assertIsArray($refreshed->preferences);
        $this->assertSame('dark', $refreshed->preferences['theme']);
        $this->assertTrue($refreshed->preferences['notifications']['email']);
    }

    public function test_user_can_update_preferences_via_patch_me(): void
    {
        $this->postJson('/api/v1/login', [
            'email' => 'invited@wwork.test',
            'password' => 'demo-seed-test',
        ])->assertOk();

        $response = $this->getJson('/api/v1/me')->assertOk();
        $this->assertArrayHasKey('preferences', $response->json('user'));

        $this->patchJson('/api/v1/me', [
            'preferences' => ['theme' => 'dark'],
        ])
            ->assertOk()
            ->assertJsonPath('user.preferences.theme', 'dark');

        $user = User::query()->where('email', 'invited@wwork.test')->firstOrFail();
        $this->assertSame('dark', $user->preferences['theme'] ?? null);
    }

    public function test_user_can_update_locale_and_preferences_simultaneously(): void
    {
        $this->postJson('/api/v1/login', [
            'email' => 'invited@wwork.test',
            'password' => 'demo-seed-test',
        ])->assertOk();

        $this->patchJson('/api/v1/me', [
            'locale' => 'pt',
            'preferences' => ['compact_view' => true],
        ])
            ->assertOk()
            ->assertJsonPath('user.locale', 'pt')
            ->assertJsonPath('user.preferences.compact_view', true);

        $user = User::query()->where('email', 'invited@wwork.test')->firstOrFail();
        $this->assertSame('pt', $user->locale->value);
        $this->assertTrue($user->preferences['compact_view'] ?? false);
    }

    public function test_patch_me_merges_with_existing_preferences(): void
    {
        $this->postJson('/api/v1/login', [
            'email' => 'invited@wwork.test',
            'password' => 'demo-seed-test',
        ])->assertOk();

        $this->patchJson('/api/v1/me', [
            'preferences' => ['theme' => 'dark', 'font_size' => 14],
        ])->assertOk();

        $this->patchJson('/api/v1/me', [
            'preferences' => ['font_size' => 16, 'sidebar_collapsed' => true],
        ])
            ->assertOk()
            ->assertJsonPath('user.preferences.theme', 'dark')
            ->assertJsonPath('user.preferences.font_size', 16)
            ->assertJsonPath('user.preferences.sidebar_collapsed', true);
    }

    public function test_patch_me_can_update_only_locale_without_affecting_preferences(): void
    {
        $this->postJson('/api/v1/login', [
            'email' => 'invited@wwork.test',
            'password' => 'demo-seed-test',
        ])->assertOk();

        $this->patchJson('/api/v1/me', [
            'preferences' => ['theme' => 'system'],
        ])->assertOk();

        $this->patchJson('/api/v1/me', [
            'locale' => 'es',
        ])
            ->assertOk()
            ->assertJsonPath('user.locale', 'es')
            ->assertJsonPath('user.preferences.theme', 'system');
    }

    public function test_patch_me_can_clear_preferences_with_null(): void
    {
        $this->postJson('/api/v1/login', [
            'email' => 'invited@wwork.test',
            'password' => 'demo-seed-test',
        ])->assertOk();

        $this->patchJson('/api/v1/me', [
            'preferences' => ['theme' => 'dark'],
        ])->assertOk();

        $this->patchJson('/api/v1/me', [
            'preferences' => null,
        ])
            ->assertOk()
            ->assertJsonPath('user.preferences', null);

        $user = User::query()->where('email', 'invited@wwork.test')->firstOrFail();
        $this->assertNull($user->preferences);
    }

    public function test_patch_me_rejects_invalid_preferences_type(): void
    {
        $this->postJson('/api/v1/login', [
            'email' => 'invited@wwork.test',
            'password' => 'demo-seed-test',
        ])->assertOk();

        $this->patchJson('/api/v1/me', [
            'preferences' => 'invalid-string',
        ])->assertStatus(422);
    }
}
