<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\BookingService;
use App\Models\Client;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketplaceApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->withHeader('referer', config('app.url'));
        $this->withHeader('Accept', 'application/json');
    }

    public function test_search_puts_the_better_rating_first_and_then_the_closer_one(): void
    {
        $far = $this->agency('Far Stars', 5, 51.80, -0.10, 'Jardinagem');
        $near = $this->agency('Near Stars', 5, 51.51, -0.12, 'Piscinas');
        $lower = $this->agency('Lower Stars', 4, 51.50, -0.12, 'Limpeza');

        $names = collect($this->getJson('/api/v1/search?q=Stars&lat=51.50&lng=-0.12')->assertOk()->json('agencies'))
            ->pluck('name')
            ->all();

        $this->assertSame(
            ['Near Stars', 'Far Stars', 'Lower Stars'],
            array_values(array_filter(
                $names,
                fn (string $name): bool => str_contains($name, 'Stars'),
            )),
        );

        $byService = collect($this->getJson('/api/v1/search?q=Jardinagem&lat=51.50&lng=-0.12')->assertOk()->json('agencies'))
            ->pluck('slug')
            ->all();
        $this->assertContains($far->public_slug, $byService);
        $this->assertNotContains($lower->public_slug, $byService);
        $this->assertNotContains($near->public_slug, $byService);
    }

    public function test_client_logs_in_with_the_phone_and_reviews_a_finished_visit(): void
    {
        $this->postJson('/api/v1/login', [
            'login' => '+447700900123',
            'password' => 'demo-seed-test',
        ])->assertOk()
            ->assertJsonPath('user.role', 'client')
            ->assertJsonPath('user.email', null)
            ->assertJsonPath('agency', null);

        $owner = User::query()->where('email', 'owner@wwork.test')->firstOrFail();
        $client = Client::query()->where('phone', '+447700900123')->firstOrFail();
        $visit = Visit::query()->create([
            'agency_id' => $client->agency_id,
            'client_id' => $client->id,
            'assignee_id' => $owner->id,
            'service_date' => '2026-09-20',
            'service_time' => '09:00',
            'description' => 'Serviço de teste',
            'price_pence' => 1000,
            'lat' => 51.5,
            'lng' => -0.1,
            'status' => 'done',
        ]);

        $this->postJson('/api/v1/client/reviews', [
            'visit_id' => $visit->id,
            'rating' => 5,
            'comment' => 'Pontual',
        ])->assertCreated();

        $slug = Agency::query()->findOrFail($client->agency_id)->public_slug;
        $this->getJson('/api/v1/agency/'.$slug.'/reviews')
            ->assertOk()
            ->assertJsonPath('reviews.0.client_name', 'Ana Costa')
            ->assertJsonPath('reviews.0.rating', 5);

        $this->assertDatabaseHas('agencies', [
            'id' => $client->agency_id,
            'average_rating' => 5,
        ]);
    }

    public function test_join_link_creates_a_client_without_email(): void
    {
        $owner = User::query()->where('email', 'owner@wwork.test')->firstOrFail();
        $this->postJson('/api/v1/login', [
            'email' => 'owner@wwork.test',
            'password' => 'demo-seed-test',
        ])->assertOk();

        $client = Client::query()->create([
            'agency_id' => $owner->membership->agency_id,
            'created_by' => $owner->id,
            'name' => 'Novo Cliente',
            'phone' => '+447911000777',
            'address' => '1 Test Street',
            'lat' => 51.5,
            'lng' => -0.1,
        ]);

        $url = $this->postJson('/api/v1/clients/'.$client->id.'/join-link')->assertOk()->json('url');
        $token = basename((string) $url);
        $this->postJson('/api/v1/logout')->assertNoContent();

        $this->postJson('/api/v1/join/'.$token, [
            'name' => 'Novo Cliente',
            'password' => 'demo-seed-test',
        ])->assertCreated();

        $this->assertDatabaseHas('users', [
            'phone' => '447911000777',
            'email' => null,
        ]);
        $this->assertDatabaseHas('clients', [
            'id' => $client->id,
            'user_id' => User::query()->where('phone', '447911000777')->firstOrFail()->id,
        ]);
    }

    public function test_public_agency_returns_indicators_and_completed_work(): void
    {
        $owner = User::query()->where('email', 'owner@wwork.test')->firstOrFail();
        $agency = $owner->membership->agency;
        $agency->update([
            'public_slug' => 'clean-masters',
            'trade' => 'cleaning',
            'average_rating' => 5.0,
        ]);

        $client = Client::query()->where('agency_id', $agency->id)->firstOrFail();

        // Create completed visit
        $visit = Visit::query()->create([
            'agency_id' => $agency->id,
            'client_id' => $client->id,
            'assignee_id' => $owner->id,
            'service_date' => '2026-10-15',
            'service_time' => '10:00',
            'description' => 'Limpeza de Apartamento',
            'price_pence' => 5000,
            'lat' => 51.5,
            'lng' => -0.1,
            'status' => 'done',
        ]);

        // Create in-progress visit (should not count as completed)
        Visit::query()->create([
            'agency_id' => $agency->id,
            'client_id' => $client->id,
            'assignee_id' => $owner->id,
            'service_date' => '2026-10-20',
            'service_time' => '14:00',
            'description' => 'Serviço em andamento',
            'price_pence' => 3000,
            'lat' => 51.5,
            'lng' => -0.1,
            'status' => 'todo',
        ]);

        \App\Models\Review::query()->create([
            'agency_id' => $agency->id,
            'client_id' => $client->id,
            'visit_id' => $visit->id,
            'rating' => 5,
            'comment' => 'Excelente trabalho!',
        ]);

        // Check public profile indicators
        $res = $this->getJson('/api/v1/agency/clean-masters')
            ->assertOk()
            ->assertJsonPath('name', $agency->name)
            ->assertJsonPath('trade', 'cleaning')
            ->assertJsonPath('review_count', 1)
            ->assertJsonPath('completed_count', 1)
            ->assertJsonPath('rating_distribution.5', 1)
            ->assertJsonPath('rating_distribution.4', 0);

        // Check completed work endpoint
        $completedRes = $this->getJson('/api/v1/agency/clean-masters/completed')
            ->assertOk();

        $completedData = $completedRes->json('completed');
        $this->assertCount(1, $completedData);
        $this->assertSame('Limpeza de Apartamento', $completedData[0]['description']);
        $this->assertTrue($completedData[0]['has_review']);
        $this->assertSame(5, $completedData[0]['rating']);

        // Verify privacy: client name, address, price, or client_id are NOT in the payload
        $this->assertArrayNotHasKey('client_name', $completedData[0]);
        $this->assertArrayNotHasKey('client_id', $completedData[0]);
        $this->assertArrayNotHasKey('price_pence', $completedData[0]);
        $this->assertArrayNotHasKey('price', $completedData[0]);
        $this->assertArrayNotHasKey('address', $completedData[0]);

        // Check AuthSession me returns reputation indicators
        $this->postJson('/api/v1/login', [
            'email' => 'owner@wwork.test',
            'password' => 'demo-seed-test',
        ])->assertOk()
            ->assertJsonPath('agency.average_rating', 5)
            ->assertJsonPath('agency.review_count', 1)
            ->assertJsonPath('agency.completed_count', 1);
    }

    private function agency(string $name, float $rating, float $lat, float $lng, string $service): Agency
    {
        $agency = Agency::query()->create([
            'name' => $name,
            'timezone' => 'Europe/London',
            'currency' => 'GBP',
            'country' => 'GB',
            'invoice_region' => 'GB',
            'trade' => 'cleaning',
            'public_slug' => strtolower(str_replace(' ', '-', $name)),
            'latitude' => $lat,
            'longitude' => $lng,
            'average_rating' => $rating,
        ]);
        BookingService::query()->create([
            'agency_id' => $agency->id,
            'name' => $service,
            'duration_minutes' => 60,
            'price_pence' => 1000,
        ]);

        return $agency;
    }
}
