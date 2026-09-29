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
        $client = Client::query()->where('whatsapp', '+447700900123')->firstOrFail();
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
            'whatsapp' => '+447911000777',
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
