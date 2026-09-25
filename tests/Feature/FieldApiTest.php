<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\User;
use App\Models\VisitGoal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FieldApiTest extends TestCase
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

    public function test_checkout_pays_only_the_invited_partner_and_keeps_the_clock(): void
    {
        $owner = User::query()->where('email', 'owner@wwork.test')->firstOrFail();
        $partner = User::query()->where('email', 'invited@wwork.test')->firstOrFail();
        $owner->forceFill(['onesignal_player_id' => 'owner-player'])->save();
        $this->login('owner@wwork.test');
        $client = $this->house($owner);

        $offered = $this->postJson('/api/v1/visits', $this->body($client, $partner->id))->assertCreated()->json('id');

        $this->postJson('/api/v1/visits/'.$offered.'/events', [
            'type' => 'check_in',
            'lat' => 51.5,
            'lng' => -0.1,
        ])->assertForbidden()->assertExactJson(['error' => 'visit.not_assignee']);

        $mine = $this->postJson('/api/v1/visits', $this->body($client, $owner->id))->assertCreated()->json('id');
        $this->postJson('/api/v1/visits/'.$mine.'/events', [
            'type' => 'check_in',
        ])->assertStatus(422)->assertExactJson(['error' => 'visit.missing_gps']);

        $checked = $this->postJson('/api/v1/visits/'.$mine.'/events', [
            'type' => 'check_in',
            'lat' => 51.5,
            'lng' => -0.1,
        ])->assertOk();
        $checked->assertJsonPath('visit_status', 'checked_in');
        $this->assertNotNull($checked->json('check_in_at'));

        $this->getJson('/api/v1/visits/'.$mine)
            ->assertOk()
            ->assertJsonPath('check_in_at', $checked->json('check_in_at'))
            ->assertJsonPath('status', 'checked_in');

        Carbon::setTestNow('2026-09-25 12:01:30');
        $this->postJson('/api/v1/visits/'.$mine.'/events', [
            'type' => 'check_out',
            'lat' => 51.5,
            'lng' => -0.1,
        ])->assertOk()->assertJsonPath('visit_status', 'done')->assertJsonPath('duration_seconds', 90);

        $this->assertDatabaseCount('payouts', 0);

        $this->postJson('/api/v1/logout')->assertNoContent();
        $this->login('invited@wwork.test');

        $this->postJson('/api/v1/visits/'.$offered.'/events', [
            'type' => 'check_in',
            'lat' => 51.5,
            'lng' => -0.1,
        ])->assertStatus(409)->assertExactJson(['error' => 'visit.not_accepted']);

        $this->postJson('/api/v1/visits/'.$offered.'/accept')->assertOk();
        $this->postJson('/api/v1/visits/'.$offered.'/events', [
            'type' => 'check_in',
            'lat' => 51.5034,
            'lng' => -0.1276,
        ])->assertOk()->assertJsonPath('visit_status', 'checked_in');

        config([
            'wwork.onesignal_app_id' => 'app',
            'wwork.onesignal_rest_key' => 'key',
        ]);
        Http::fake([
            'https://api.onesignal.com/*' => Http::response('down', 500),
        ]);

        $this->postJson('/api/v1/visits/'.$offered.'/events', [
            'type' => 'check_out',
            'lat' => 51.5034,
            'lng' => -0.1276,
        ])->assertOk()->assertJsonPath('visit_status', 'done');

        $this->assertDatabaseHas('payouts', [
            'visit_id' => $offered,
            'user_id' => $partner->id,
            'amount_pence' => 4800,
            'paid' => false,
        ]);
        $this->assertDatabaseHas('activities', [
            'action' => 'onesignal.failed',
        ]);
        $this->assertDatabaseCount('payouts', 1);
    }

    public function test_photos_stop_at_three_and_goals_belong_to_the_visit(): void
    {
        $owner = User::query()->where('email', 'owner@wwork.test')->firstOrFail();
        $this->login('owner@wwork.test');
        $client = $this->house($owner);
        $visit = $this->postJson('/api/v1/visits', [
            ...$this->body($client, $owner->id),
            'goals' => [
                ['text' => 'Clean kitchen'],
            ],
        ])->assertCreated()->json('id');

        $photo = UploadedFile::fake()->image('room.jpg');
        $this->post('/api/v1/visits/'.$visit.'/photos', ['photo' => $photo])
            ->assertCreated()
            ->assertJsonPath('path', 'visits/'.$visit.'/photo_1.jpg');
        $this->post('/api/v1/visits/'.$visit.'/photos', ['photo' => UploadedFile::fake()->image('two.jpg')])->assertCreated();
        $this->post('/api/v1/visits/'.$visit.'/photos', ['photo' => UploadedFile::fake()->image('three.jpg')])->assertCreated();
        $this->post('/api/v1/visits/'.$visit.'/photos', ['photo' => UploadedFile::fake()->image('four.jpg')])
            ->assertStatus(422)
            ->assertExactJson(['error' => 'visit.photo_limit']);

        $goal = VisitGoal::query()->where('visit_id', $visit)->firstOrFail();
        $this->patchJson('/api/v1/visits/'.$visit.'/goals', [
            'goals' => [
                ['id' => $goal->id, 'completed' => true],
            ],
        ])->assertOk()->assertJsonPath('goals.0.completed', true);

        $this->patchJson('/api/v1/visits/'.$visit.'/goals', [
            'goals' => [
                ['id' => $goal->id + 99, 'completed' => false],
            ],
        ])->assertNotFound()->assertExactJson(['error' => 'visit.goal_not_found']);
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

    /**
     * @return array<string, mixed>
     */
    private function body(int $client, int $assignee): array
    {
        return [
            'client_id' => $client,
            'date' => '2026-09-25',
            'time' => '09:00',
            'description' => 'Deep clean',
            'price_pence' => 8000,
            'assignee_id' => $assignee,
            'lat' => 51.5034,
            'lng' => -0.1276,
        ];
    }
}
