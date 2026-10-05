<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LocationApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
        $this->withHeader('referer', config('app.url'));
    }

    public function test_owner_sees_a_recent_point_and_a_missing_body_does_not_save(): void
    {
        $owner = User::query()->where('email', 'owner@wwork.test')->firstOrFail();
        $events = DB::table('check_events')->count();

        $this->postJson('/api/v1/login', [
            'email' => 'owner@wwork.test',
            'password' => 'demo-seed-test',
        ])->assertOk();

        $owner->refresh();
        $seen = $owner->last_seen_at;

        $this->postJson('/api/v1/me/location', [])
            ->assertStatus(422)
            ->assertExactJson(['error' => 'location.missing']);

        $owner->refresh();
        $this->assertNull($owner->last_lat);
        $this->assertNull($owner->last_located_at);
        $this->assertEquals($seen, $owner->last_seen_at);
        $this->assertSame($events, DB::table('check_events')->count());

        $this->postJson('/api/v1/me/location', ['lat' => 51.5074])
            ->assertStatus(422)
            ->assertExactJson(['error' => 'location.missing']);

        $this->postJson('/api/v1/me/location', [
            'lat' => 51.5074,
            'lng' => -0.1278,
        ])->assertNoContent();

        $owner->refresh();
        $this->assertEquals(51.5074, (float) $owner->last_lat);
        $this->assertEquals(-0.1278, (float) $owner->last_lng);
        $this->assertNotNull($owner->last_located_at);
        $this->assertTrue($owner->last_located_at->greaterThan(now()->subMinutes(2)));
        $this->assertEquals($seen, $owner->last_seen_at);
        $this->assertSame($events, DB::table('check_events')->count());

        $this->getJson('/api/v1/team/locations')
            ->assertOk()
            ->assertJsonStructure([
                'locations' => [[
                    'user_id', 'name', 'has_avatar', 'lat', 'lng', 'at', 'self', 'status', 'since',
                ]],
            ])
            ->assertJsonPath('locations.0.user_id', $owner->id)
            ->assertJsonPath('locations.0.name', $owner->name)
            ->assertJsonPath('locations.0.lat', 51.5074)
            ->assertJsonPath('locations.0.lng', -0.1278)
            ->assertJsonPath('locations.0.self', true)
            ->assertJsonPath('locations.0.has_avatar', false)
            ->assertJsonPath('locations.0.status', 'online')
            ->assertJsonPath('locations.0.since', null);

        $owner->forceFill(['avatar_path' => 'users/'.$owner->id.'/avatar-test.jpg'])->save();

        $this->getJson('/api/v1/team/locations')
            ->assertOk()
            ->assertJsonPath('locations.0.has_avatar', true);

        $this->postJson('/api/v1/logout')->assertNoContent();
    }

    public function test_invited_point_is_listed_for_the_owner_and_hidden_after_two_minutes(): void
    {
        $invited = User::query()->where('email', 'invited@wwork.test')->firstOrFail();

        $this->postJson('/api/v1/login', [
            'email' => 'invited@wwork.test',
            'password' => 'demo-seed-test',
        ])->assertOk();

        $this->getJson('/api/v1/team/locations')
            ->assertForbidden()
            ->assertExactJson(['error' => 'team.invite_forbidden']);

        $this->postJson('/api/v1/me/location', [
            'lat' => 51.512,
            'lng' => -0.118,
        ])->assertNoContent();

        $this->postJson('/api/v1/logout')->assertNoContent();

        $this->postJson('/api/v1/login', [
            'email' => 'owner@wwork.test',
            'password' => 'demo-seed-test',
        ])->assertOk();

        $this->postJson('/api/v1/me/location', [
            'lat' => 51.5074,
            'lng' => -0.1278,
        ])->assertNoContent();

        $fresh = $this->getJson('/api/v1/team/locations')->assertOk();
        $ids = collect($fresh->json('locations'))->pluck('user_id')->all();
        $this->assertContains($invited->id, $ids);
        $this->assertTrue(collect($fresh->json('locations'))->firstWhere('user_id', $invited->id)['self'] === false);

        $invited->forceFill(['last_located_at' => now()->subMinutes(3)])->save();

        $stale = $this->getJson('/api/v1/team/locations')->assertOk();
        $this->assertNotContains($invited->id, collect($stale->json('locations'))->pluck('user_id')->all());
    }
}
