<?php

namespace Tests\Feature;

use App\Enums\MembershipRole;
use App\Models\Invite;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class TeamApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
        $this->withHeader('referer', config('app.url'));
        Mail::fake();
    }

    public function test_invited_cannot_invite_and_expired_invite_is_refused(): void
    {
        $this->postJson('/api/v1/login', [
            'email' => 'invited@wwork.test',
            'password' => 'demo-seed-test',
        ])->assertOk();

        $this->postJson('/api/v1/partners', [
            'email' => 'new@wwork.test',
            'rate' => 50,
        ])->assertForbidden()->assertExactJson(['error' => 'team.invite_forbidden']);

        $this->postJson('/api/v1/logout')->assertNoContent();

        $owner = User::query()->where('email', 'owner@wwork.test')->firstOrFail();
        $agencyId = $owner->membership->agency_id;

        Invite::query()->create([
            'agency_id' => $agencyId,
            'invited_by' => $owner->id,
            'email' => 'late@wwork.test',
            'token' => 'expired-token',
            'rate' => 40,
            'expires_at' => now()->subDay(),
            'sent_at' => now()->subDays(8),
        ]);

        $this->postJson('/api/v1/invites/expired-token/accept', [
            'name' => 'Late Person',
            'password' => 'secret123',
            'locale' => 'en',
            'terms_accepted' => true,
        ])->assertNotFound()->assertExactJson(['error' => 'invite.expired']);

        $this->assertDatabaseMissing('users', ['email' => 'late@wwork.test']);
    }

    public function test_owner_invites_within_seven_days_and_seat_cap_blocks_the_fourth(): void
    {
        $this->postJson('/api/v1/login', [
            'email' => 'owner@wwork.test',
            'password' => 'demo-seed-test',
        ])->assertOk();

        $created = $this->postJson('/api/v1/partners', [
            'email' => 'ana@wwork.test',
            'rate' => 60,
        ])->assertCreated();

        $created->assertJsonPath('invite.email', 'ana@wwork.test');
        $created->assertJsonPath('invite.rate', 60);
        $created->assertJsonPath('invite.accepted', false);

        $expires = $created->json('invite.expires_at');
        $this->assertNotNull($expires);
        $this->assertTrue(now()->addDays(6)->lt($expires));
        $this->assertTrue(now()->addDays(8)->gt($expires));

        $this->postJson('/api/v1/partners', [
            'email' => 'ana@wwork.test',
            'rate' => 60,
        ])->assertStatus(422)->assertExactJson(['error' => 'team.email_already_invited']);

        $this->getJson('/api/v1/team')
            ->assertOk()
            ->assertJsonPath('pending_invites.0.email', 'ana@wwork.test');

        $owner = User::query()->where('email', 'owner@wwork.test')->firstOrFail();

        Membership::query()->create([
            'agency_id' => $owner->membership->agency_id,
            'user_id' => User::factory()->create(['email' => 'third@wwork.test'])->id,
            'role' => MembershipRole::Invited,
            'rate' => 10,
        ]);

        $this->postJson('/api/v1/partners', [
            'email' => 'fourth@wwork.test',
            'rate' => 20,
        ])->assertForbidden()->assertExactJson(['error' => 'team.seat_limit']);
    }

    public function test_accept_creates_the_invited_membership_with_the_invite_rate(): void
    {
        $owner = User::query()->where('email', 'owner@wwork.test')->firstOrFail();

        Invite::query()->create([
            'agency_id' => $owner->membership->agency_id,
            'invited_by' => $owner->id,
            'email' => 'joao@wwork.test',
            'token' => 'join-token',
            'rate' => 60,
            'expires_at' => now()->addDays(7),
            'sent_at' => now(),
        ]);

        $this->postJson('/api/v1/invites/join-token/accept', [
            'name' => 'Joao Souza',
            'password' => 'secret123',
            'locale' => 'pt',
            'terms_accepted' => false,
        ])->assertStatus(422)->assertExactJson(['error' => 'register.terms_required']);

        $this->postJson('/api/v1/invites/join-token/accept', [
            'name' => 'Joao Souza',
            'password' => 'secret123',
            'locale' => 'pt',
            'terms_accepted' => true,
        ])
            ->assertOk()
            ->assertJsonPath('user.email', 'joao@wwork.test')
            ->assertJsonPath('user.role', 'invited')
            ->assertJsonPath('user.locale', 'pt');

        $this->assertDatabaseHas('memberships', [
            'user_id' => User::query()->where('email', 'joao@wwork.test')->value('id'),
            'rate' => 60,
            'role' => 'invited',
        ]);
    }

    public function test_owner_can_resend_change_rate_and_remove_an_invited_member(): void
    {
        $this->postJson('/api/v1/login', [
            'email' => 'owner@wwork.test',
            'password' => 'demo-seed-test',
        ])->assertOk();

        $inviteId = $this->postJson('/api/v1/partners', [
            'email' => 'ana@wwork.test',
            'rate' => 55,
        ])->assertCreated()->json('invite.id');

        $before = Invite::query()->findOrFail($inviteId)->token;

        $resent = $this->postJson('/api/v1/invites/'.$inviteId.'/resend')->assertOk();
        $after = $resent->json('invite.token');
        $this->assertNotSame($before, $after);
        $this->assertSame(40, strlen($after));

        $invited = User::query()->where('email', 'invited@wwork.test')->firstOrFail();
        $owner = User::query()->where('email', 'owner@wwork.test')->firstOrFail();

        $this->patchJson('/api/v1/team/'.$invited->id.'/rate', ['rate' => 65])
            ->assertOk()
            ->assertExactJson(['id' => $invited->id, 'rate' => 65]);

        $this->patchJson('/api/v1/team/'.$owner->id.'/rate', ['rate' => 10])
            ->assertForbidden()
            ->assertExactJson(['error' => 'team.cannot_rate_owner']);

        $this->deleteJson('/api/v1/team/'.$owner->id)
            ->assertForbidden()
            ->assertExactJson(['error' => 'team.cannot_remove_owner']);

        $this->deleteJson('/api/v1/team/'.$invited->id)->assertNoContent();

        $this->assertSoftDeleted('memberships', ['user_id' => $invited->id]);
        $this->assertDatabaseHas('sync_deletions', [
            'table_name' => 'memberships',
            'sync_uuid' => Membership::withTrashed()->where('user_id', $invited->id)->value('sync_uuid'),
        ]);
    }
}
