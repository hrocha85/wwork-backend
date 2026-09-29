<?php

namespace Tests\Feature;

use App\Enums\MembershipRole;
use App\Mail\PartnerInvited;
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

        $before = Invite::query()->findOrFail($inviteId)->token_hash;

        $resent = $this->postJson('/api/v1/invites/'.$inviteId.'/resend')->assertOk();
        $resent->assertJsonMissingPath('invite.token');
        $after = Invite::query()->findOrFail($inviteId)->token_hash;
        $this->assertNotSame($before, $after);
        $this->assertSame(64, strlen($after));
        Mail::assertSent(PartnerInvited::class, 2);

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

    public function test_one_email_carries_the_link_and_only_the_hash_is_stored(): void
    {
        $this->postJson('/api/v1/login', [
            'email' => 'owner@wwork.test',
            'password' => 'demo-seed-test',
        ])->assertOk();

        $created = $this->postJson('/api/v1/partners', [
            'email' => 'Maya@Wwork.test',
            'rate' => 60,
        ])->assertCreated();

        $created->assertJsonMissingPath('invite.token');
        $invite = Invite::query()->findOrFail($created->json('invite.id'));
        $this->assertNull($invite->token);
        $this->assertSame(64, strlen((string) $invite->token_hash));

        $plain = null;
        Mail::assertSent(PartnerInvited::class, function (PartnerInvited $mail) use (&$plain): bool {
            $prefix = config('wwork.frontend_url').'/invites/';
            if (! $mail->hasTo('maya@wwork.test') || ! str_starts_with($mail->url, $prefix)) {
                return false;
            }
            $plain = substr($mail->url, strlen($prefix));

            return true;
        });
        Mail::assertSent(PartnerInvited::class, 1);
        $this->assertSame($invite->token_hash, Invite::hashToken((string) $plain));

        $rendered = (new PartnerInvited($invite, $invite->url((string) $plain)))->locale('pt');
        $rendered->assertSeeInHtml($invite->url((string) $plain));
        $rendered->assertSeeInHtml('Criar minha conta');
        $rendered->assertSeeInText('Criar minha conta');
        $rendered->assertHasSubject('Você foi convidado para a equipe de '.$invite->agency->name.' no WWork');

        $this->postJson('/api/v1/logout')->assertNoContent();

        $this->postJson('/api/v1/invites/'.$invite->token_hash.'/accept', [
            'name' => 'Maya',
            'password' => 'secret123',
            'locale' => 'en',
            'terms_accepted' => true,
        ])->assertNotFound()->assertExactJson(['error' => 'invite.expired']);

        $this->postJson('/api/v1/invites/'.$plain.'/accept', [
            'name' => 'Maya',
            'password' => 'secret123',
            'locale' => 'en',
            'terms_accepted' => true,
        ])->assertOk()->assertJsonPath('user.role', 'invited');

        $this->assertNotNull($invite->fresh()->accepted_at);
        Mail::assertSent(PartnerInvited::class, 1);
    }

    public function test_owner_cancels_a_pending_invite_and_the_link_stops_working(): void
    {
        $this->postJson('/api/v1/login', [
            'email' => 'owner@wwork.test',
            'password' => 'demo-seed-test',
        ])->assertOk();

        $inviteId = $this->postJson('/api/v1/partners', [
            'email' => 'ana@wwork.test',
            'rate' => 55,
        ])->assertCreated()->json('invite.id');

        $plain = null;
        Mail::assertSent(PartnerInvited::class, function (PartnerInvited $mail) use (&$plain): bool {
            $plain = substr($mail->url, strlen(config('wwork.frontend_url').'/invites/'));

            return true;
        });

        $this->postJson('/api/v1/invites/'.$inviteId.'/cancel')->assertNoContent();
        $this->assertNotNull(Invite::query()->findOrFail($inviteId)->cancelled_at);

        $this->getJson('/api/v1/team')->assertOk()->assertJsonCount(0, 'pending_invites');

        $this->postJson('/api/v1/invites/'.$inviteId.'/cancel')
            ->assertStatus(409)
            ->assertExactJson(['error' => 'invite.cancelled']);

        $this->postJson('/api/v1/invites/'.$inviteId.'/resend')
            ->assertStatus(409)
            ->assertExactJson(['error' => 'invite.cancelled']);

        $this->postJson('/api/v1/partners', [
            'email' => 'ana@wwork.test',
            'rate' => 55,
        ])->assertCreated();

        $this->postJson('/api/v1/logout')->assertNoContent();

        $this->postJson('/api/v1/invites/'.$plain.'/accept', [
            'name' => 'Ana',
            'password' => 'secret123',
            'locale' => 'en',
            'terms_accepted' => true,
        ])->assertStatus(409)->assertExactJson(['error' => 'invite.cancelled']);

        $this->assertDatabaseMissing('users', ['email' => 'ana@wwork.test']);
    }

    public function test_invited_cannot_cancel_and_accepted_invite_cannot_be_cancelled(): void
    {
        $owner = User::query()->where('email', 'owner@wwork.test')->firstOrFail();

        $invite = Invite::query()->create([
            'agency_id' => $owner->membership->agency_id,
            'invited_by' => $owner->id,
            'email' => 'done@wwork.test',
            'token' => 'legacy-token',
            'rate' => 50,
            'expires_at' => now()->addDays(7),
            'sent_at' => now(),
            'accepted_at' => now(),
        ]);

        $this->postJson('/api/v1/login', [
            'email' => 'invited@wwork.test',
            'password' => 'demo-seed-test',
        ])->assertOk();

        $this->postJson('/api/v1/invites/'.$invite->id.'/cancel')
            ->assertForbidden()
            ->assertExactJson(['error' => 'team.not_owner']);

        $this->postJson('/api/v1/logout')->assertNoContent();

        $this->postJson('/api/v1/login', [
            'email' => 'owner@wwork.test',
            'password' => 'demo-seed-test',
        ])->assertOk();

        $this->postJson('/api/v1/invites/'.$invite->id.'/cancel')
            ->assertStatus(409)
            ->assertExactJson(['error' => 'invite.already_accepted']);
    }
}
