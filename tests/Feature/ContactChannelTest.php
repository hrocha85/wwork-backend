<?php

namespace Tests\Feature;

use App\Enums\ContactChannel;
use App\Models\Agency;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ContactChannelTest extends TestCase
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

    public function test_client_is_created_with_a_generic_phone_and_a_preferred_channel(): void
    {
        $this->login('owner@wwork.test');

        $this->postJson('/api/v1/clients', [
            'name' => 'Casa com canal',
            'phone' => '+447700900555',
            'contact_channel' => ContactChannel::Sms->value,
            'address' => '1 Road',
            'lat' => 51.5,
            'lng' => -0.1,
        ])->assertCreated()
            ->assertJsonPath('phone', '+447700900555')
            ->assertJsonPath('contact_channel', 'sms');

        $this->assertDatabaseHas('clients', [
            'phone' => '+447700900555',
            'contact_channel' => 'sms',
        ]);
    }

    public function test_legacy_whatsapp_payload_still_creates_the_client_as_phone(): void
    {
        $this->login('owner@wwork.test');

        $this->postJson('/api/v1/clients', [
            'name' => 'Casa antiga',
            'whatsapp' => '+447700900556',
            'address' => '2 Road',
            'lat' => 51.5,
            'lng' => -0.1,
        ])->assertCreated()
            ->assertJsonPath('phone', '+447700900556')
            ->assertJsonPath('contact_channel', 'whatsapp');

        $this->assertDatabaseHas('clients', [
            'phone' => '+447700900556',
            'contact_channel' => 'whatsapp',
        ]);
    }

    /**
     * O alias `whatsapp` é temporário: existe para não derrubar cliente que
     * ainda manda o corpo antigo. Quando a janela fechar, apague o alias de
     * StoreClientRequest, BookSlotRequest, OpenQuoteRequest e ClientController
     * — e apague este teste junto.
     */
    public function test_the_legacy_whatsapp_alias_is_still_inside_its_window(): void
    {
        $this->assertTrue(
            now()->lessThan('2027-01-01'),
            'A janela do alias `whatsapp` expirou: remova o alias e este teste.',
        );
    }

    public function test_unknown_channel_is_rejected(): void
    {
        $this->login('owner@wwork.test');

        $this->postJson('/api/v1/clients', [
            'name' => 'Casa errada',
            'phone' => '+447700900557',
            'contact_channel' => 'pigeon',
            'address' => '3 Road',
            'lat' => 51.5,
            'lng' => -0.1,
        ])->assertStatus(422);
    }

    public function test_update_changes_the_phone_and_the_channel(): void
    {
        $this->login('owner@wwork.test');

        $id = $this->postJson('/api/v1/clients', [
            'name' => 'Casa muda',
            'phone' => '+447700900558',
            'address' => '4 Road',
            'lat' => 51.5,
            'lng' => -0.1,
        ])->assertCreated()->json('id');

        $this->patchJson('/api/v1/clients/'.$id, [
            'phone' => '+447700900559',
            'contact_channel' => ContactChannel::Call->value,
        ])->assertOk()
            ->assertJsonPath('phone', '+447700900559')
            ->assertJsonPath('contact_channel', 'call');

        $this->assertDatabaseHas('clients', [
            'id' => $id,
            'phone' => '+447700900559',
            'contact_channel' => 'call',
        ]);
    }

    /**
     * O número nunca se perdeu: linha escrita direto no banco (sem passar pelo
     * model) ainda cai no default da migration, que é o mesmo que o backfill
     * gravou para os clientes que já existiam antes do rename.
     */
    public function test_contact_channel_defaults_to_whatsapp_for_rows_written_without_it(): void
    {
        $agency = Agency::query()->firstOrFail();
        $owner = User::query()->where('email', 'owner@wwork.test')->firstOrFail();

        DB::table('clients')->insert([
            'agency_id' => $agency->id,
            'created_by' => $owner->id,
            'name' => 'Casa backfill',
            'phone' => '+447700900560',
            'address' => '5 Road',
            'lat' => 51.5,
            'lng' => -0.1,
            'sync_uuid' => '00000000-0000-0000-0000-0000000000aa',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertDatabaseHas('clients', [
            'phone' => '+447700900560',
            'contact_channel' => 'whatsapp',
        ]);
    }

    public function test_booking_request_carries_the_channel_the_client_picked(): void
    {
        $this->login('owner@wwork.test');
        $saved = $this->putJson('/api/v1/booking', $this->payload())->assertOk();
        $token = basename((string) $saved->json('url'));
        $serviceId = $saved->json('services.0.id');

        $withChannel = $this->postJson('/api/v1/book/'.$token, [
            'service_id' => $serviceId,
            'date' => '2026-09-28',
            'time' => '09:00',
            'name' => 'Cliente com canal',
            'phone' => '+447700900561',
            'contact_channel' => ContactChannel::Other->value,
        ])->assertCreated();

        $this->assertDatabaseHas('booking_requests', [
            'id' => $withChannel->json('request_id'),
            'client_phone' => '+447700900561',
            'client_contact_channel' => 'other',
        ]);

        // O pedido sem canal continua caindo no default do lançamento.
        $default = $this->postJson('/api/v1/book/'.$token, [
            'service_id' => $serviceId,
            'date' => '2026-09-28',
            'time' => '10:00',
            'name' => 'Cliente sem canal',
            'whatsapp' => '+447700900562',
        ])->assertCreated();

        $this->assertDatabaseHas('booking_requests', [
            'id' => $default->json('request_id'),
            'client_phone' => '+447700900562',
            'client_contact_channel' => 'whatsapp',
        ]);

        // O dono vê o canal ao lado do telefone na lista de pedidos.
        $requests = $this->getJson('/api/v1/booking/requests')
            ->assertOk()
            ->json('requests');
        $row = collect($requests)->firstWhere('id', $withChannel->json('request_id'));

        $this->assertNotNull($row);
        $this->assertSame('other', $row['client_contact_channel']);
        $this->assertSame('+447700900561', $row['client_phone']);
    }

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
