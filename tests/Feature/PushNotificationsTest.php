<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Gatilhos de push das pontas do agendamento:
 * aceite de solicitação (empregador) e conclusão do serviço (check-out).
 */
class PushNotificationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
        $this->withHeader('referer', config('app.url'));
        $this->withHeader('Accept', 'application/json');
        Carbon::setTestNow('2026-09-25 12:00:00');

        config([
            'wwork.onesignal_app_id' => 'app-test-id',
            'wwork.onesignal_rest_key' => 'rest-test-key',
            'wwork.frontend_url' => 'https://app.test',
        ]);

        Http::fake(['https://api.onesignal.com/*' => Http::response(['id' => 'notif-1'])]);
    }

    public function test_accepting_a_work_request_pushes_only_the_correct_employer_exactly_once(): void
    {
        $owner = User::query()->where('email', 'owner@wwork.test')->firstOrFail();
        $partner = User::query()->where('email', 'invited@wwork.test')->firstOrFail();

        $this->login('owner@wwork.test');
        $client = $this->house($owner);
        $visit = $this->postJson('/api/v1/visits', $this->body($client, $partner->id))
            ->assertCreated()
            ->json('id');

        // Antes do aceite o empregador não é notificado.
        $this->assertSame(0, $this->pushesWithHeading('Job accepted'));

        $this->postJson('/api/v1/logout')->assertNoContent();
        $this->login('invited@wwork.test');
        $this->postJson('/api/v1/visits/'.$visit.'/accept')->assertOk();

        $this->assertSame(1, $this->pushesWithHeading('Job accepted'));
        Http::assertSent(fn (Request $request): bool => $request['headings']['en'] === 'Job accepted'
            && ($request['include_aliases']['external_id'] ?? null) === ['user-'.$owner->id]
            && str_contains((string) $request['contents']['en'], ' accepted the job for John Smith')
            && str_contains((string) $request['url'], 'https://app.test/calendar'));

        // Aceitar de novo não é possível (sai de offered uma única vez) e não duplica.
        $this->postJson('/api/v1/visits/'.$visit.'/accept')
            ->assertStatus(409)
            ->assertExactJson(['error' => 'visit.not_offered']);
        $this->assertSame(1, $this->pushesWithHeading('Job accepted'));

        // O parceiro (profissional) nunca recebe o push do empregador.
        $this->assertSame(0, Http::recorded(fn (Request $request): bool => ($request['headings']['en'] ?? null) === 'Job accepted'
            && ($request['include_aliases']['external_id'] ?? null) === ['user-'.$partner->id])->count());
    }

    public function test_completing_a_service_pushes_the_employer_exactly_once(): void
    {
        $owner = User::query()->where('email', 'owner@wwork.test')->firstOrFail();
        $partner = User::query()->where('email', 'invited@wwork.test')->firstOrFail();

        $this->login('owner@wwork.test');
        $client = $this->house($owner);
        $visit = $this->postJson('/api/v1/visits', $this->body($client, $partner->id))
            ->assertCreated()
            ->json('id');

        // Editar o agendamento não gera notificação de conclusão.
        $this->patchJson('/api/v1/visits/'.$visit, ['description' => 'Deep clean plus windows'])->assertOk();
        $this->assertSame(0, $this->pushesWithHeading('Job finished'));

        $this->postJson('/api/v1/logout')->assertNoContent();
        $this->login('invited@wwork.test');
        $this->postJson('/api/v1/visits/'.$visit.'/accept')->assertOk();
        $this->postJson('/api/v1/visits/'.$visit.'/events', [
            'type' => 'check_in',
            'lat' => 51.5034,
            'lng' => -0.1276,
        ])->assertOk();

        Carbon::setTestNow('2026-09-25 12:30:00');
        $this->postJson('/api/v1/visits/'.$visit.'/events', [
            'type' => 'check_out',
            'payment_method' => 'cash',
            'lat' => 51.5034,
            'lng' => -0.1276,
        ])->assertOk()->assertJsonPath('visit_status', 'done');

        // Conclusão: só no check-out, para o empregador, com a chave determinística.
        $this->assertSame(1, $this->pushesWithHeading('Job finished'));
        Http::assertSent(fn (Request $request): bool => $request['headings']['en'] === 'Job finished'
            && ($request['include_aliases']['external_id'] ?? null) === ['user-'.$owner->id]
            && str_contains((string) $request['contents']['en'], 'finished the job at 10 Downing Street, London')
            && str_contains((string) $request['url'], 'https://app.test/calendar'));

        // O mesmo evento reprocessado cai no estado inválido e não duplica.
        $this->postJson('/api/v1/visits/'.$visit.'/events', [
            'type' => 'check_out',
            'payment_method' => 'cash',
            'lat' => 51.5034,
            'lng' => -0.1276,
        ])->assertStatus(409);
        $this->assertSame(1, $this->pushesWithHeading('Job finished'));
    }

    public function test_owner_completing_own_job_does_not_push_the_finish_to_the_employer(): void
    {
        $owner = User::query()->where('email', 'owner@wwork.test')->firstOrFail();

        $this->login('owner@wwork.test');
        $client = $this->house($owner);
        $visit = $this->postJson('/api/v1/visits', $this->body($client, $owner->id))
            ->assertCreated()
            ->json('id');

        $this->postJson('/api/v1/visits/'.$visit.'/events', [
            'type' => 'check_in',
            'lat' => 51.5034,
            'lng' => -0.1276,
        ])->assertOk();

        Carbon::setTestNow('2026-09-25 12:30:00');
        $this->postJson('/api/v1/visits/'.$visit.'/events', [
            'type' => 'check_out',
            'payment_method' => 'cash',
            'lat' => 51.5034,
            'lng' => -0.1276,
        ])->assertOk()->assertJsonPath('visit_status', 'done');

        // Quem executou foi o próprio dono: a regra de destinatário não avisa ele mesmo.
        $this->assertSame(0, $this->pushesWithHeading('Job finished'));
    }

    private function pushesWithHeading(string $heading): int
    {
        return Http::recorded(fn (Request $request): bool => ($request['headings']['en'] ?? null) === $heading)
            ->count();
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
            'phone' => '+447911123456',
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
            'time' => '15:00',
            'description' => 'Deep clean',
            'price_pence' => 8000,
            'assignee_id' => $assignee,
            'lat' => 51.5034,
            'lng' => -0.1276,
        ];
    }
}
