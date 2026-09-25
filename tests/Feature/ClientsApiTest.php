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

class ClientsApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
        $this->withHeader('referer', config('app.url'));
        Carbon::setTestNow('2026-09-25 12:00:00');
    }

    public function test_invited_can_create_but_cannot_list_or_open_another_agencys_client(): void
    {
        $this->postJson('/api/v1/login', [
            'email' => 'invited@wwork.test',
            'password' => 'demo-seed-test',
        ])->assertOk();

        $this->postJson('/api/v1/clients', [
            'name' => 'House',
            'whatsapp' => '+447911123456',
            'address' => '1 Road',
        ])->assertStatus(422)->assertExactJson(['error' => 'client.missing_point']);

        $created = $this->postJson('/api/v1/clients', [
            'name' => 'John Smith',
            'whatsapp' => '+447911123456',
            'address' => '10 Downing Street, London',
            'lat' => 51.5034,
            'lng' => -0.1276,
        ])->assertCreated();

        $created->assertJsonPath('name', 'John Smith');
        $created->assertJsonPath('created_by', User::query()->where('email', 'invited@wwork.test')->value('id'));
        $this->assertNotEmpty($created->json('sync_uuid'));

        $this->getJson('/api/v1/clients')->assertForbidden()->assertExactJson(['error' => 'client.forbidden']);

        $foreign = $this->foreignClient();

        $this->getJson('/api/v1/clients/'.$foreign->id)
            ->assertForbidden()
            ->assertExactJson(['error' => 'client.forbidden']);

        $this->deleteJson('/api/v1/clients/'.$foreign->id)
            ->assertForbidden()
            ->assertExactJson(['error' => 'client.forbidden']);
    }

    public function test_owner_lists_by_weekday_and_refuses_to_delete_a_house_with_work(): void
    {
        $owner = User::query()->where('email', 'owner@wwork.test')->firstOrFail();
        $this->postJson('/api/v1/login', [
            'email' => 'owner@wwork.test',
            'password' => 'demo-seed-test',
        ])->assertOk();

        $monday = $this->postJson('/api/v1/clients', [
            'name' => 'Monday House',
            'whatsapp' => '+447911000001',
            'address' => '1 Monday Street',
            'lat' => 51.5,
            'lng' => -0.1,
        ])->assertCreated()->json('id');

        $tuesday = $this->postJson('/api/v1/clients', [
            'name' => 'Tuesday House',
            'whatsapp' => '+447911000002',
            'address' => '2 Tuesday Street',
            'lat' => 51.51,
            'lng' => -0.11,
        ])->assertCreated()->json('id');

        $this->visit($owner, $monday, '2026-09-28', VisitStatus::Todo);
        $this->visit($owner, $tuesday, '2026-09-29', VisitStatus::Todo);

        $this->getJson('/api/v1/clients?day=mon')
            ->assertOk()
            ->assertJsonCount(1, 'clients')
            ->assertJsonPath('clients.0.name', 'Monday House')
            ->assertJsonPath('clients.0.next_visit.date', '2026-09-28')
            ->assertJsonPath('clients.0.next_visit.time', '09:00')
            ->assertJsonPath('clients.0.next_visit.status', 'todo');

        $this->getJson('/api/v1/clients/'.$monday)
            ->assertOk()
            ->assertJsonPath('visits.0.price_pence', 8000)
            ->assertJsonPath('whatsapp', '+447911000001');

        $this->deleteJson('/api/v1/clients/'.$monday)
            ->assertStatus(409)
            ->assertExactJson(['error' => 'client.has_active_visits']);
    }

    public function test_owner_deletes_a_finished_house_and_blocks_one_already_invoiced(): void
    {
        $owner = User::query()->where('email', 'owner@wwork.test')->firstOrFail();
        $this->postJson('/api/v1/login', [
            'email' => 'owner@wwork.test',
            'password' => 'demo-seed-test',
        ])->assertOk();

        $clear = Client::query()->create([
            'agency_id' => $owner->membership->agency_id,
            'created_by' => $owner->id,
            'name' => 'Finished',
            'whatsapp' => '+447911000003',
            'address' => '3 Done Street',
            'lat' => 51.52,
            'lng' => -0.12,
        ]);
        $this->visit($owner, $clear->id, '2026-09-21', VisitStatus::Done);

        $this->deleteJson('/api/v1/clients/'.$clear->id)->assertNoContent();
        $this->assertSoftDeleted('clients', ['id' => $clear->id]);
        $this->assertDatabaseHas('sync_deletions', [
            'table_name' => 'clients',
            'sync_uuid' => $clear->sync_uuid,
        ]);

        $billed = Client::query()->create([
            'agency_id' => $owner->membership->agency_id,
            'created_by' => $owner->id,
            'name' => 'Billed',
            'whatsapp' => '+447911000004',
            'address' => '4 Bill Street',
            'lat' => 51.53,
            'lng' => -0.13,
        ]);
        $visit = $this->visit($owner, $billed->id, '2026-09-22', VisitStatus::Done);
        $invoice = Invoice::query()->create([
            'agency_id' => $owner->membership->agency_id,
            'client_id' => $billed->id,
            'created_by' => $owner->id,
            'number' => 1,
            'status' => InvoiceStatus::ToSend,
            'total_pence' => 8000,
            'locale' => Locale::En,
            'invoice_region' => 'GB',
        ]);
        InvoiceLine::query()->create([
            'invoice_id' => $invoice->id,
            'visit_id' => $visit->id,
            'service_date' => '2026-09-22',
            'description' => 'Clean',
            'price_pence' => 8000,
        ]);

        $this->deleteJson('/api/v1/clients/'.$billed->id)
            ->assertStatus(409)
            ->assertExactJson(['error' => 'client.has_invoices']);
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

    private function visit(User $owner, int $clientId, string $date, VisitStatus $status): Visit
    {
        return Visit::query()->create([
            'agency_id' => $owner->membership->agency_id,
            'client_id' => $clientId,
            'assignee_id' => $owner->id,
            'service_date' => $date,
            'service_time' => '09:00:00',
            'price_pence' => 8000,
            'lat' => 51.5,
            'lng' => -0.1,
            'status' => $status,
        ]);
    }
}
