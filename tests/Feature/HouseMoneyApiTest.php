<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class HouseMoneyApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
        $this->withHeader('referer', config('app.url'));
        $this->withHeader('Accept', 'application/json');
        Carbon::setTestNow('2026-09-25 12:00:00');
    }

    public function test_invited_cannot_invoice_and_pdf_uses_the_country_region(): void
    {
        $owner = User::query()->where('email', 'owner@wwork.test')->firstOrFail();
        $partner = User::query()->where('email', 'invited@wwork.test')->firstOrFail();
        $this->login('owner@wwork.test');
        $house = $this->house($owner, '10 Downing Street, London');
        $other = $this->house($owner, '11 Downing Street, London');

        $offered = $this->postJson('/api/v1/visits', $this->body($house, $partner->id, 8000))->assertCreated()->json('id');
        $todo = $this->postJson('/api/v1/visits', $this->body($house, $owner->id, 1000))->assertCreated()->json('id');
        $foreign = $this->postJson('/api/v1/visits', $this->body($other, $owner->id, 2000))->assertCreated()->json('id');

        $this->postJson('/api/v1/logout')->assertNoContent();
        $this->login('invited@wwork.test');
        $this->postJson('/api/v1/visits/'.$offered.'/accept')->assertOk();
        $this->postJson('/api/v1/visits/'.$offered.'/events', [
            'type' => 'check_in',
            'lat' => 51.5,
            'lng' => -0.1,
        ])->assertOk();
        $this->postJson('/api/v1/visits/'.$offered.'/events', [
            'type' => 'check_out',
            'lat' => 51.5,
            'lng' => -0.1,
        ])->assertOk();

        $this->postJson('/api/v1/invoices', [
            'client_id' => $house,
            'visit_ids' => [$offered],
        ])->assertForbidden()->assertExactJson(['error' => 'invoice.forbidden']);

        $this->postJson('/api/v1/logout')->assertNoContent();
        $this->login('owner@wwork.test');

        $this->postJson('/api/v1/invoices', [
            'client_id' => $house,
            'visit_ids' => [],
        ])->assertStatus(422)->assertExactJson(['error' => 'invoice.empty']);

        $this->postJson('/api/v1/invoices', [
            'client_id' => $house,
            'visit_ids' => [$todo],
        ])->assertStatus(422)->assertExactJson(['error' => 'invoice.visit_not_done']);

        $this->postJson('/api/v1/visits/'.$foreign.'/events', [
            'type' => 'check_in',
            'lat' => 51.5,
            'lng' => -0.1,
        ])->assertOk();
        $this->postJson('/api/v1/visits/'.$foreign.'/events', [
            'type' => 'check_out',
            'lat' => 51.5,
            'lng' => -0.1,
        ])->assertOk();

        $this->postJson('/api/v1/invoices', [
            'client_id' => $house,
            'visit_ids' => [$foreign],
        ])->assertStatus(422)->assertExactJson(['error' => 'invoice.client_mismatch']);

        $created = $this->postJson('/api/v1/invoices', [
            'client_id' => $house,
            'visit_ids' => [$offered],
        ])->assertCreated();
        $created->assertJsonPath('number', 'INV-0001')
            ->assertJsonPath('status', 'to_send')
            ->assertJsonPath('total_pence', 8000)
            ->assertJsonPath('pdf_url', null)
            ->assertJsonPath('lines.0.price_pence', 8000);
        $this->assertDatabaseHas('invoices', [
            'id' => $created->json('id'),
            'invoice_region' => 'GB',
        ]);

        $pdf = $this->get('/api/v1/invoices/'.$created->json('id').'/pdf')->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $pdf->headers->get('content-type'));
        $bytes = $this->pdfText($pdf->getContent());
        $this->assertStringContainsString(mb_convert_encoding('United Kingdom', 'UTF-16BE'), $bytes);
        $this->assertStringNotContainsString('commission', strtolower($bytes));

        $this->postJson('/api/v1/invoices', [
            'client_id' => $house,
            'visit_ids' => [$offered],
        ])->assertStatus(422)->assertExactJson(['error' => 'invoice.visit_already_invoiced']);

        $share = $this->postJson('/api/v1/invoices/'.$created->json('id').'/share')->assertOk();
        $share->assertJsonPath('pdf_url', fn (string $url): bool => str_contains($url, '/api/v1/invoices/share/'));
        $this->assertDatabaseHas('invoices', ['id' => $created->json('id'), 'status' => 'sent']);

        $token = basename((string) $share->json('pdf_url'));
        $this->get('/api/v1/invoices/share/'.$token)->assertOk();

        Carbon::setTestNow('2026-10-26 12:00:00');
        $this->getJson('/api/v1/invoices/share/'.$token)
            ->assertNotFound()
            ->assertExactJson(['error' => 'invoice.share_expired']);
    }

    public function test_billing_ics_and_agenda_hide_the_house_price(): void
    {
        $owner = User::query()->where('email', 'owner@wwork.test')->firstOrFail();
        $partner = User::query()->where('email', 'invited@wwork.test')->firstOrFail();
        $this->login('owner@wwork.test');
        $house = $this->house($owner, '10 Downing Street, London');
        $visit = $this->postJson('/api/v1/visits', $this->body($house, $partner->id, 8000))->assertCreated()->json('id');

        $this->postJson('/api/v1/logout')->assertNoContent();
        $this->login('invited@wwork.test');
        $this->postJson('/api/v1/visits/'.$visit.'/accept')->assertOk();
        $this->postJson('/api/v1/visits/'.$visit.'/events', [
            'type' => 'check_in',
            'lat' => 51.5,
            'lng' => -0.1,
        ])->assertOk();
        $this->postJson('/api/v1/visits/'.$visit.'/events', [
            'type' => 'check_out',
            'lat' => 51.5,
            'lng' => -0.1,
        ])->assertOk();

        $mine = $this->get('/api/v1/visits.ics?from=2026-09-21&to=2026-09-27')->assertOk();
        $this->assertStringContainsString('text/calendar', (string) $mine->headers->get('content-type'));
        $this->assertStringContainsString('SUMMARY:WWork', $mine->getContent());
        $this->assertStringContainsString('10 Downing Street', $mine->getContent());
        $this->assertStringNotContainsString('8000', $mine->getContent());

        $billing = $this->getJson('/api/v1/billing?period=week')->assertOk();
        $billing->assertJsonPath('totals.earned_pence', 4800)
            ->assertJsonPath('payouts.0.payout_pence', 4800)
            ->assertJsonMissingPath('payouts.0.price_pence')
            ->assertJsonMissingPath('invoices');

        $this->postJson('/api/v1/logout')->assertNoContent();
        $this->login('owner@wwork.test');

        $this->postJson('/api/v1/invoices', [
            'client_id' => $house,
            'visit_ids' => [$visit],
        ])->assertCreated();

        $ownerBill = $this->getJson('/api/v1/billing?period=week')->assertOk();
        $ownerBill->assertJsonPath('totals.invoiced_pence', 8000)
            ->assertJsonPath('totals.to_pay_pence', 4800)
            ->assertJsonPath('payouts.0.price_pence', 8000)
            ->assertJsonPath('payouts.0.rate', 60);

        $payout = $ownerBill->json('payouts.0.id');
        $this->postJson('/api/v1/payouts/'.$payout.'/paid')
            ->assertOk()
            ->assertJsonPath('paid', true);

        $link = $this->postJson('/api/v1/clients/'.$house.'/agenda-link')->assertOk();
        $link->assertJsonPath('whatsapp', '+447911123456');
        $token = basename((string) $link->json('url'));

        $this->postJson('/api/v1/logout')->assertNoContent();
        $public = $this->getJson('/api/v1/agenda/'.$token)->assertOk();
        $public->assertJsonPath('client_name', 'John Smith')
            ->assertJsonPath('visits.0.done', true)
            ->assertJsonMissingPath('visits.0.price_pence');
        $this->assertStringNotContainsString('8000', $public->getContent());

        $this->postJson('/api/v1/agenda/'.$token.'/notify', [])
            ->assertStatus(422)
            ->assertExactJson(['error' => 'agenda.missing_player']);
        $this->postJson('/api/v1/agenda/'.$token.'/notify', [
            'player_id' => 'house-player',
        ])->assertOk()->assertExactJson(['ok' => true]);
        $this->assertDatabaseHas('clients', [
            'id' => $house,
            'onesignal_player_id' => 'house-player',
        ]);
        $this->getJson('/api/v1/agenda/not-a-token')
            ->assertNotFound()
            ->assertExactJson(['error' => 'agenda.invalid_token']);

        Storage::disk('local')->assertExists(
            'invoices/'.$owner->membership->agency_id.'/1.pdf',
        );
    }

    private function pdfText(string $bytes): string
    {
        $text = $bytes;
        if (preg_match_all('/stream\r?\n(.*?)\r?\nendstream/s', $bytes, $matches) === 1 || isset($matches[1])) {
            foreach ($matches[1] as $stream) {
                $decoded = @gzuncompress($stream);
                if (is_string($decoded)) {
                    $text .= $decoded;
                }
            }
        }

        return $text;
    }

    private function login(string $email): void
    {
        $this->postJson('/api/v1/login', [
            'email' => $email,
            'password' => 'demo-seed-test',
        ])->assertOk();
    }

    private function house(User $owner, string $address): int
    {
        return Client::query()->create([
            'agency_id' => $owner->membership->agency_id,
            'created_by' => $owner->id,
            'name' => 'John Smith',
            'whatsapp' => '+447911123456',
            'address' => $address,
            'lat' => 51.5034,
            'lng' => -0.1276,
        ])->id;
    }

    /**
     * @return array<string, mixed>
     */
    private function body(int $client, int $assignee, int $price): array
    {
        return [
            'client_id' => $client,
            'date' => '2026-09-25',
            'time' => '09:00',
            'description' => 'Deep clean',
            'price_pence' => $price,
            'assignee_id' => $assignee,
            'lat' => 51.5034,
            'lng' => -0.1276,
        ];
    }
}
