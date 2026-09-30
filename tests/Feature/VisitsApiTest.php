<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Enums\Locale;
use App\Enums\MembershipRole;
use App\Enums\Trade;
use App\Enums\VisitStatus;
use App\Models\Agency;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\Membership;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class VisitsApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
        $this->withHeader('referer', config('app.url'));
        Carbon::setTestNow('2026-09-25 12:00:00');
    }

    public function test_owner_creates_an_offered_visit_and_the_partner_accepts_or_declines(): void
    {
        $owner = User::query()->where('email', 'owner@wwork.test')->firstOrFail();
        $partner = User::query()->where('email', 'invited@wwork.test')->firstOrFail();
        $this->login('owner@wwork.test');

        $client = $this->house($owner);

        $this->postJson('/api/v1/visits', [
            'client_id' => $client,
            'date' => '2026-09-25',
            'time' => '09:00',
            'price_pence' => 8000,
        ])->assertStatus(422)->assertExactJson(['error' => 'visit.missing_assignee']);

        $this->postJson('/api/v1/visits', [
            'client_id' => $client,
            'date' => '2026-09-25',
            'time' => '09:00',
            'price_pence' => 8000,
            'assignee_id' => $partner->id,
        ])->assertStatus(422)->assertExactJson(['error' => 'visit.missing_point']);

        $this->assertDatabaseCount('visits', 0);

        $mine = $this->postJson('/api/v1/visits', [
            'client_id' => $client,
            'date' => '2026-09-25',
            'time' => '08:00',
            'price_pence' => 5000,
            'assignee_id' => $owner->id,
            'lat' => 51.5,
            'lng' => -0.1,
        ])->assertCreated();

        $mine->assertJsonPath('status', 'todo');
        $mine->assertJsonPath('rate', null);
        $mine->assertJsonPath('partner_earning_pence', null);
        $mine->assertJsonPath('assignee.id', $owner->id);

        $offered = $this->postJson('/api/v1/visits', [
            'client_id' => $client,
            'date' => '2026-09-25',
            'time' => '09:00',
            'description' => 'Deep clean',
            'price_pence' => 8000,
            'assignee_id' => $partner->id,
            'lat' => 51.5034,
            'lng' => -0.1276,
            'goals' => [
                ['text' => 'Clean kitchen'],
            ],
        ])->assertCreated();

        $offered->assertJsonPath('status', 'offered');
        $offered->assertJsonPath('rate', 60);
        $offered->assertJsonPath('partner_earning_pence', 4800);
        $offered->assertJsonPath('assignee.id', $partner->id);
        $visitId = $offered->json('id');

        $this->postJson('/api/v1/visits/'.$visitId.'/accept')
            ->assertForbidden()
            ->assertExactJson(['error' => 'visit.forbidden']);

        Membership::query()->where('user_id', $partner->id)->update(['rate' => 10]);

        $this->patchJson('/api/v1/visits/'.$visitId, [
            'price_pence' => 10000,
        ])->assertOk()
            ->assertJsonPath('partner_earning_pence', 6000)
            ->assertJsonPath('rate', 60)
            ->assertJsonPath('status', 'offered');

        $this->getJson('/api/v1/visits?day=today&assignee='.$partner->id)
            ->assertOk()
            ->assertJsonCount(1, 'visits')
            ->assertJsonPath('visits.0.id', $visitId)
            ->assertJsonPath('visits.0.client.whatsapp', '+447911123456')
            ->assertJsonPath('visits.0.price_pence', 10000)
            ->assertJsonPath('visits.0.description', 'Deep clean')
            ->assertJsonPath('visits.0.invoiced', false);

        $this->postJson('/api/v1/logout')->assertNoContent();
        $this->login('invited@wwork.test');

        $this->postJson('/api/v1/visits', [
            'client_id' => $client,
            'date' => '2026-09-25',
            'time' => '11:00',
            'price_pence' => 1000,
            'assignee_id' => $partner->id,
            'lat' => 51.5,
            'lng' => -0.1,
        ])->assertForbidden()->assertExactJson(['error' => 'visit.forbidden']);

        $this->getJson('/api/v1/visits/'.$mine->json('id'))
            ->assertForbidden()
            ->assertExactJson(['error' => 'visit.not_assignee']);

        $this->getJson('/api/v1/visits?day=today&assignee='.$owner->id)
            ->assertOk()
            ->assertJsonCount(1, 'visits')
            ->assertJsonPath('visits.0.id', $visitId)
            ->assertJsonMissingPath('visits.0.price_pence')
            ->assertJsonMissingPath('visits.0.rate')
            ->assertJsonMissingPath('visits.0.client.whatsapp')
            ->assertJsonPath('visits.0.partner_earning_pence', 6000)
            ->assertJsonPath('visits.0.goals.0.text', 'Clean kitchen')
            ->assertJsonPath('visits.0.goals.0.completed', null);

        $this->postJson('/api/v1/visits/'.$visitId.'/accept')
            ->assertOk()
            ->assertExactJson([
                'id' => $visitId,
                'status' => 'todo',
            ]);

        $this->postJson('/api/v1/visits/'.$visitId.'/decline')
            ->assertStatus(409)
            ->assertExactJson(['error' => 'visit.not_offered']);

        $second = Visit::query()->create([
            'agency_id' => $owner->membership->agency_id,
            'client_id' => $client,
            'assignee_id' => $partner->id,
            'service_date' => '2026-09-25',
            'service_time' => '15:00:00',
            'price_pence' => 4000,
            'rate' => 60,
            'partner_earning_pence' => 2400,
            'lat' => 51.5,
            'lng' => -0.1,
            'status' => VisitStatus::Offered,
        ]);

        $this->postJson('/api/v1/visits/'.$second->id.'/decline')
            ->assertOk()
            ->assertExactJson([
                'id' => $second->id,
                'status' => 'todo',
                'assignee_id' => $owner->id,
            ]);

        $this->assertDatabaseHas('visits', [
            'id' => $second->id,
            'assignee_id' => $owner->id,
            'status' => 'todo',
            'rate' => null,
            'partner_earning_pence' => null,
        ]);
    }

    public function test_owner_cannot_cancel_a_finished_visit_and_goals_are_required_with_two_partners(): void
    {
        $owner = User::query()->where('email', 'owner@wwork.test')->firstOrFail();
        $partner = User::query()->where('email', 'invited@wwork.test')->firstOrFail();
        $this->login('owner@wwork.test');
        $client = $this->house($owner);

        $extra = User::factory()->create();
        Membership::query()->create([
            'agency_id' => $owner->membership->agency_id,
            'user_id' => $extra->id,
            'role' => MembershipRole::Invited,
            'rate' => 40,
        ]);

        $this->postJson('/api/v1/visits', [
            'client_id' => $client,
            'date' => '2026-09-26',
            'time' => '10:00',
            'price_pence' => 8000,
            'assignee_id' => $partner->id,
            'lat' => 51.5,
            'lng' => -0.1,
        ])->assertStatus(422)->assertExactJson(['error' => 'visit.goals_required']);

        $open = $this->postJson('/api/v1/visits', [
            'client_id' => $client,
            'date' => '2026-09-26',
            'time' => '10:00',
            'price_pence' => 8000,
            'assignee_id' => $partner->id,
            'lat' => 51.5,
            'lng' => -0.1,
            'goals' => [
                ['text' => 'Vacuum bedrooms'],
            ],
        ])->assertCreated()->json('id');

        $this->patchJson('/api/v1/visits/'.$open, [
            'assignee_id' => $owner->id,
            'date' => '2026-09-26',
        ])->assertOk()
            ->assertJsonPath('status', 'todo')
            ->assertJsonPath('assignee.id', $owner->id)
            ->assertJsonPath('rate', null);

        $done = Visit::query()->create([
            'agency_id' => $owner->membership->agency_id,
            'client_id' => $client,
            'assignee_id' => $owner->id,
            'service_date' => '2026-09-25',
            'service_time' => '09:00:00',
            'price_pence' => 8000,
            'lat' => 51.5,
            'lng' => -0.1,
            'status' => VisitStatus::Done,
        ]);

        $this->deleteJson('/api/v1/visits/'.$done->id)
            ->assertStatus(409)
            ->assertExactJson(['error' => 'visit.already_done']);

        $this->patchJson('/api/v1/visits/'.$done->id, [
            'description' => 'nope',
        ])->assertStatus(409)->assertExactJson(['error' => 'visit.already_done']);

        $billed = Visit::query()->create([
            'agency_id' => $owner->membership->agency_id,
            'client_id' => $client,
            'assignee_id' => $owner->id,
            'service_date' => '2026-09-24',
            'service_time' => '09:00:00',
            'price_pence' => 8000,
            'lat' => 51.5,
            'lng' => -0.1,
            'status' => VisitStatus::Todo,
        ]);
        $invoice = Invoice::query()->create([
            'agency_id' => $owner->membership->agency_id,
            'client_id' => $client,
            'created_by' => $owner->id,
            'number' => 1,
            'status' => InvoiceStatus::ToSend,
            'total_pence' => 8000,
            'locale' => Locale::En,
            'invoice_region' => 'GB',
        ]);
        InvoiceLine::query()->create([
            'invoice_id' => $invoice->id,
            'visit_id' => $billed->id,
            'service_date' => '2026-09-24',
            'description' => 'Clean',
            'price_pence' => 8000,
        ]);

        $this->deleteJson('/api/v1/visits/'.$billed->id)
            ->assertStatus(409)
            ->assertExactJson(['error' => 'visit.invoiced']);

        $this->deleteJson('/api/v1/visits/'.$open)->assertNoContent();
        $this->assertSoftDeleted('visits', ['id' => $open]);
        $this->assertDatabaseHas('sync_deletions', [
            'table_name' => 'visits',
            'sync_uuid' => Visit::withTrashed()->findOrFail($open)->sync_uuid,
        ]);

        $foreign = $this->foreignClient();
        $this->postJson('/api/v1/visits', [
            'client_id' => $foreign->id,
            'date' => '2026-09-25',
            'time' => '09:00',
            'price_pence' => 1000,
            'assignee_id' => $owner->id,
            'lat' => 51.5,
            'lng' => -0.1,
            'goals' => [
                ['text' => 'Nope'],
            ],
        ])->assertForbidden()->assertExactJson(['error' => 'visit.forbidden']);

        $this->postJson('/api/v1/visits', [
            'client_id' => $client,
            'date' => '2026-09-25',
            'time' => '09:00',
            'price_pence' => 1000,
            'assignee_id' => $foreign->created_by,
            'lat' => 51.5,
            'lng' => -0.1,
            'goals' => [
                ['text' => 'Nope'],
            ],
        ])->assertStatus(422)->assertExactJson(['error' => 'visit.invalid_assignee']);
    }

    private function login(string $email): void
    {
        $this->postJson('/api/v1/login', [
            'email' => $email,
            'password' => 'demo-seed-test',
        ])->assertOk();
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

    private function foreignClient(): Client
    {
        $agency = Agency::query()->create([
            'name' => 'Other Agency',
            'timezone' => 'Europe/London',
            'currency' => 'GBP',
            'country' => 'GB',
            'trade' => Trade::Cleaning,
        ]);
        $owner = User::factory()->create();
        Membership::query()->create([
            'agency_id' => $agency->id,
            'user_id' => $owner->id,
            'role' => MembershipRole::Owner,
        ]);

        return Client::query()->create([
            'agency_id' => $agency->id,
            'created_by' => $owner->id,
            'name' => 'Foreign House',
            'whatsapp' => '+447911999999',
            'address' => '9 Other Street',
            'lat' => 51.5,
            'lng' => -0.1,
        ]);
    }
}
