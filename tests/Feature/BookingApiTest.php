<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class BookingApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
        Carbon::setTestNow(Carbon::parse('2026-09-28 08:00:00', 'Europe/London'));
        $this->withHeader('referer', config('app.url'));
        $this->withHeader('Accept', 'application/json');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_invited_cannot_publish_the_agenda(): void
    {
        $this->login('invited@wwork.test');
        $this->putJson('/api/v1/booking', $this->payload())->assertForbidden()
            ->assertExactJson(['error' => 'booking.forbidden']);
    }

    public function test_owner_publishes_hours_and_a_client_books_the_free_slot(): void
    {
        $this->login('owner@wwork.test');
        $this->getJson('/api/v1/booking')->assertOk()
            ->assertJsonPath('ready', false)
            ->assertJsonPath('url', null);

        $saved = $this->putJson('/api/v1/booking', $this->payload())->assertOk()
            ->assertJsonPath('ready', true);
        $serviceId = $saved->json('services.0.id');
        $token = basename((string) $saved->json('url'));

        $this->postJson('/api/v1/agenda/blocks', [
            'starts_at' => '2026-09-28T10:00:00',
            'ends_at' => '2026-09-28T11:00:00',
        ])->assertCreated();

        $this->getJson('/api/v1/book/'.$token)->assertOk()
            ->assertJsonPath('days.0.date', '2026-09-28')
            ->assertJsonPath('days.0.slots.0.time', '09:00')
            ->assertJsonCount(1, 'days.0.slots');

        $booked = $this->postJson('/api/v1/book/'.$token, [
            'service_id' => $serviceId,
            'date' => '2026-09-28',
            'time' => '09:00',
            'name' => 'Cliente novo',
            'phone' => '+447700900111',
        ])->assertCreated()->assertJsonPath('ok', true);

        $this->assertNotEmpty($booked->json('request_id'));

        $this->postJson('/api/v1/book/'.$token, [
            'service_id' => $serviceId,
            'date' => '2026-09-28',
            'time' => '09:00',
            'name' => 'Outro',
            'phone' => '+447700900112',
        ])->assertStatus(409)->assertExactJson(['error' => 'booking.taken']);

        $this->assertDatabaseHas('booking_requests', [
            'id' => $booked->json('request_id'),
            'status' => 'pending',
            'client_name' => 'Cliente novo',
        ]);
        $this->assertDatabaseMissing('visits', [
            'description' => 'Regular clean',
        ]);
    }

    public function test_capacity_counts_visits_and_a_rejected_request_frees_the_slot(): void
    {
        $this->login('owner@wwork.test');
        $saved = $this->putJson('/api/v1/booking', [
            'services' => [[
                'name' => 'Regular clean',
                'duration_minutes' => 60,
                'price_pence' => 8000,
            ]],
            'hours' => [[
                'weekday' => 'mon',
                'starts' => '14:00',
                'ends' => '15:00',
                'concurrent_slots' => 3,
            ]],
        ])->assertOk()->assertJsonPath('hours.0.concurrent_slots', 3);
        $serviceId = $saved->json('services.0.id');
        $token = basename((string) $saved->json('url'));
        $owner = User::query()->where('email', 'owner@wwork.test')->firstOrFail();
        $client = Client::query()->create([
            'agency_id' => $owner->membership->agency_id,
            'created_by' => $owner->id,
            'name' => 'Casa',
            'phone' => '+447700900000',
            'address' => '1 Road',
            'lat' => 51.5,
            'lng' => -0.1,
        ]);
        foreach ([1, 2] as $index) {
            Visit::query()->create([
                'agency_id' => $owner->membership->agency_id,
                'client_id' => $client->id,
                'assignee_id' => $owner->id,
                'service_date' => '2026-09-28',
                'service_time' => '14:00:00',
                'price_pence' => 8000,
                'lat' => 51.5,
                'lng' => -0.1,
                'status' => 'todo',
            ]);
        }

        $this->getJson('/api/v1/book/'.$token)->assertOk()
            ->assertJsonPath('days.0.slots.0.time', '14:00')
            ->assertJsonPath('days.0.slots.0.remaining', 1);

        $booked = $this->postJson('/api/v1/book/'.$token, [
            'service_id' => $serviceId,
            'date' => '2026-09-28',
            'time' => '14:00',
            'name' => 'Mais um',
            'phone' => '+447700900113',
        ])->assertCreated();

        $this->postJson('/api/v1/book/'.$token, [
            'service_id' => $serviceId,
            'date' => '2026-09-28',
            'time' => '14:00',
            'name' => 'Cheio',
            'phone' => '+447700900114',
        ])->assertStatus(409);

        $this->postJson('/api/v1/booking/requests/'.$booked->json('request_id'), [
            'status' => 'rejected',
        ])->assertOk()->assertJsonPath('status', 'rejected');

        $this->getJson('/api/v1/book/'.$token)->assertOk()
            ->assertJsonPath('days.0.slots.0.remaining', 1);
    }

    public function test_unknown_link_is_not_found(): void
    {
        $this->getJson('/api/v1/book/missing-token')->assertNotFound()
            ->assertExactJson(['error' => 'booking.not_found']);
    }

    public function test_approving_a_slot_creates_the_client_and_the_visit(): void
    {
        $this->login('owner@wwork.test');
        $saved = $this->putJson('/api/v1/booking', $this->payload())->assertOk();
        $token = basename((string) $saved->json('url'));
        $serviceId = $saved->json('services.0.id');

        $booked = $this->postJson('/api/v1/book/'.$token, [
            'service_id' => $serviceId,
            'date' => '2026-09-28',
            'time' => '09:00',
            'name' => 'Ana Costa',
            'phone' => '+447700900999',
        ])->assertCreated();

        $id = $this->getJson('/api/v1/booking')->json('requests.0.id');
        $this->postJson('/api/v1/booking/requests/'.$id, ['status' => 'approved'])->assertOk()
            ->assertJsonPath('status', 'approved');

        $this->assertNotNull($booked->json('request_id'));
        $this->assertTrue(Client::query()->where('phone', '+447700900999')->exists());
        $this->assertTrue(Visit::query()->where('price_pence', 8000)->whereDate('service_date', '2026-09-28')->exists());
    }

    public function test_a_quote_is_answered_and_accepted_into_a_visit(): void
    {
        $this->login('owner@wwork.test');
        $saved = $this->putJson('/api/v1/booking', $this->payload())->assertOk();
        $token = basename((string) $saved->json('url'));

        $opened = $this->postJson('/api/v1/book/'.$token.'/quotes', [
            'name' => 'Neide',
            'phone' => '+447700900998',
            'address' => 'Av pinheiro machado 535',
            'description' => 'A torneira não fecha.',
        ])->assertCreated();
        $public = $opened->json('public_token');

        $id = $this->getJson('/api/v1/booking')->json('requests.0.id');
        $this->postJson('/api/v1/booking/requests/'.$id.'/reply', [
            'price_pence' => 12000,
            'note' => 'Troco o reparo da torneira.',
            'date' => '2026-09-29',
            'time' => '10:00',
        ])->assertOk()->assertJsonPath('status', 'quoted');

        $this->getJson('/api/v1/book/'.$token.'/quotes/'.$public)->assertOk()
            ->assertJsonPath('quote_pence', 12000);

        $this->postJson('/api/v1/book/'.$token.'/quotes/'.$public, ['accept' => true])->assertOk()
            ->assertJsonPath('status', 'approved');

        $this->assertTrue(Visit::query()->where('price_pence', 12000)->whereDate('service_date', '2026-09-29')->exists());
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(): array
    {
        return [
            'services' => [[
                'name' => 'Regular clean',
                'duration_minutes' => 60,
                'price_pence' => 8000,
            ]],
            'hours' => [[
                'weekday' => 'mon',
                'starts' => '09:00',
                'ends' => '11:00',
            ]],
        ];
    }

    private function login(string $email): void
    {
        $this->postJson('/api/v1/login', [
            'email' => $email,
            'password' => 'demo-seed-test',
        ])->assertOk();
    }
}
