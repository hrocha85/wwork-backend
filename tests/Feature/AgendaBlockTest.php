<?php

namespace Tests\Feature;

use App\Enums\SubscriptionStatus;
use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgendaBlockTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
        $this->withHeader('referer', config('app.url'));
        $this->withHeader('Accept', 'application/json');
    }

    public function test_owner_and_invited_block_the_owner_agenda_and_a_new_owner_job_cannot_overlap(): void
    {
        $owner = User::query()->where('email', 'owner@wwork.test')->firstOrFail();
        $partner = User::query()->where('email', 'invited@wwork.test')->firstOrFail();
        $house = $this->house($owner);

        $this->login('invited@wwork.test');
        $created = $this->postJson('/api/v1/agenda/blocks', [
            'starts_at' => '2026-09-25T09:00:00',
            'ends_at' => '2026-09-25T11:00:00',
            'note' => 'School run',
        ])->assertCreated();
        $created->assertJsonPath('created_by', 'invited')->assertJsonPath('mine', true);
        $blockId = $created->json('id');

        $this->postJson('/api/v1/agenda/blocks', [
            'starts_at' => '2026-09-25T12:00:00',
            'ends_at' => '2026-09-25T11:00:00',
        ])->assertStatus(422)->assertExactJson(['error' => 'agenda.ends_before_start']);

        $this->postJson('/api/v1/agenda/blocks', [
            'starts_at' => '2026-09-25T12:00:00',
            'ends_at' => '2026-09-25T13:00:00',
            'note' => str_repeat('a', 121),
        ])->assertStatus(422)->assertExactJson(['error' => 'agenda.note_too_long']);

        $this->getJson('/api/v1/agenda/blocks?from=2026-09-25&to=2026-09-25')
            ->assertOk()
            ->assertJsonPath('blocks.0.note', 'School run');

        $this->login('owner@wwork.test');
        $this->postJson('/api/v1/visits', $this->body($house, $owner->id, '09:30'))
            ->assertStatus(409)
            ->assertExactJson(['error' => 'agenda.blocked']);
        $this->postJson('/api/v1/visits', $this->body($house, $partner->id, '09:30'))->assertCreated();
        $this->postJson('/api/v1/visits', $this->body($house, $owner->id, '12:00'))->assertCreated();

        $ics = $this->get('/api/v1/visits.ics?from=2026-09-25&to=2026-09-25')->assertOk()->getContent();
        $this->assertStringContainsString('WWork unavailable', $ics);
        $this->assertStringNotContainsString('School run', $ics);

        $this->login('invited@wwork.test');
        $partnerIcs = $this->get('/api/v1/visits.ics?from=2026-09-25&to=2026-09-25')->assertOk()->getContent();
        $this->assertStringNotContainsString('WWork unavailable', $partnerIcs);

        $ownerBlock = $this->loginAndBlock('owner@wwork.test');
        $this->login('invited@wwork.test');
        $this->deleteJson('/api/v1/agenda/blocks/'.$ownerBlock)->assertForbidden()
            ->assertExactJson(['error' => 'agenda.block_forbidden']);
        $this->deleteJson('/api/v1/agenda/blocks/'.$blockId)->assertNoContent();

        $this->login('owner@wwork.test');
        $this->deleteJson('/api/v1/agenda/blocks/'.$ownerBlock)->assertNoContent();
    }

    public function test_a_cancelled_plan_cannot_create_a_block(): void
    {
        $owner = User::query()->where('email', 'owner@wwork.test')->firstOrFail();
        $owner->membership->agency->subscription->update(['status' => SubscriptionStatus::Cancelled]);

        $this->login('owner@wwork.test');
        $this->postJson('/api/v1/agenda/blocks', [
            'starts_at' => '2026-09-26T09:00:00',
            'ends_at' => '2026-09-26T10:00:00',
        ])->assertStatus(402)->assertExactJson(['error' => 'subscription.inactive']);
    }

    private function login(string $email): void
    {
        $this->postJson('/api/v1/logout');
        $this->postJson('/api/v1/login', [
            'email' => $email,
            'password' => 'demo-seed-test',
        ])->assertOk();
    }

    private function loginAndBlock(string $email): int
    {
        $this->login($email);

        return (int) $this->postJson('/api/v1/agenda/blocks', [
            'starts_at' => '2026-09-26T15:00:00',
            'ends_at' => '2026-09-26T16:00:00',
        ])->assertCreated()->json('id');
    }

    private function house(User $owner): int
    {
        return Client::query()->create([
            'agency_id' => $owner->membership->agency_id,
            'created_by' => $owner->id,
            'name' => 'John Smith',
            'whatsapp' => '+447911123456',
            'address' => '10 Downing Street, London',
            'lat' => 51.5034,
            'lng' => -0.1276,
        ])->id;
    }

    /**
     * @return array<string, mixed>
     */
    private function body(int $client, int $assignee, string $time): array
    {
        return [
            'client_id' => $client,
            'date' => '2026-09-25',
            'time' => $time,
            'description' => 'Deep clean',
            'price_pence' => 8000,
            'assignee_id' => $assignee,
            'lat' => 51.5034,
            'lng' => -0.1276,
        ];
    }
}
