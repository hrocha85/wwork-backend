<?php

namespace Tests\Feature;

use App\Enums\SubscriptionStatus;
use App\Enums\VisitStatus;
use App\Mail\MonthSummaryMail;
use App\Mail\VisitFinishedMail;
use App\Mail\VisitReminderMail;
use App\Models\Client;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PaidPlanMailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
        Carbon::setTestNow(Carbon::parse('2026-10-20 11:00:00', 'America/Sao_Paulo'));
        $this->withHeader('referer', config('app.url'));
        $this->withHeader('Accept', 'application/json');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_a_paying_agency_emails_the_client_before_and_after_the_visit(): void
    {
        Mail::fake();
        $owner = User::query()->where('email', 'owner@wwork.test')->firstOrFail();
        $client = Client::query()->create([
            'agency_id' => $owner->membership->agency_id,
            'created_by' => $owner->id,
            'name' => 'Ana Costa',
            'phone' => '+447700900111',
            'email' => 'ana@example.com',
            'address' => '10 Downing Street, London',
            'lat' => 51.5034,
            'lng' => -0.1276,
        ]);
        Visit::query()->create([
            'agency_id' => $owner->membership->agency_id,
            'client_id' => $client->id,
            'assignee_id' => $owner->id,
            'service_date' => '2026-10-20',
            'service_time' => '18:00',
            'description' => 'Limpeza',
            'price_pence' => 8000,
            'lat' => 51.5034,
            'lng' => -0.1276,
            'status' => VisitStatus::Todo,
        ]);

        $this->artisan('visits:remind-upcoming')->assertSuccessful();
        Mail::assertSent(VisitReminderMail::class, fn (VisitReminderMail $mail): bool => $mail->hasTo('ana@example.com'));

        $owner->membership->agency->subscription->forceFill(['status' => SubscriptionStatus::Trial])->save();
        $other = Client::query()->create([
            'agency_id' => $owner->membership->agency_id,
            'created_by' => $owner->id,
            'name' => 'Sem plano',
            'phone' => '+447700900112',
            'email' => 'trial@example.com',
            'address' => '11 Downing Street, London',
            'lat' => 51.5,
            'lng' => -0.12,
        ]);
        Visit::query()->create([
            'agency_id' => $owner->membership->agency_id,
            'client_id' => $other->id,
            'assignee_id' => $owner->id,
            'service_date' => '2026-10-20',
            'service_time' => '20:00',
            'description' => 'Limpeza',
            'price_pence' => 4000,
            'lat' => 51.5,
            'lng' => -0.12,
            'status' => VisitStatus::Todo,
        ]);
        $this->artisan('visits:remind-upcoming')->assertSuccessful();
        Mail::assertNotSent(VisitReminderMail::class, fn (VisitReminderMail $mail): bool => $mail->hasTo('trial@example.com'));

        $owner->membership->agency->subscription->forceFill(['status' => SubscriptionStatus::Active])->save();
        $this->postJson('/api/v1/login', [
            'email' => 'owner@wwork.test',
            'password' => 'demo-seed-test',
        ])->assertOk();
        $visit = $this->postJson('/api/v1/visits', [
            'client_id' => $client->id,
            'date' => '2026-10-21',
            'time' => '09:00',
            'description' => 'Limpeza',
            'price_pence' => 4900,
            'assignee_id' => $owner->id,
            'lat' => 51.5034,
            'lng' => -0.1276,
        ])->assertCreated()->json('id');
        $this->postJson('/api/v1/visits/'.$visit.'/events', [
            'type' => 'check_in',
            'lat' => 51.5,
            'lng' => -0.1,
        ])->assertOk();
        $this->post('/api/v1/visits/'.$visit.'/photos', ['photo' => UploadedFile::fake()->image('done.jpg')])->assertCreated();
        $this->postJson('/api/v1/visits/'.$visit.'/events', [
            'type' => 'check_out',
            'payment_method' => 'cash',
            'lat' => 51.5,
            'lng' => -0.1,
        ])->assertOk();

        Mail::assertSent(VisitFinishedMail::class, function (VisitFinishedMail $mail): bool {
            return $mail->hasTo('ana@example.com') && str_contains($mail->render(), '49.00');
        });
    }

    public function test_the_owner_of_a_paying_agency_receives_last_months_summary(): void
    {
        Mail::fake();
        $owner = User::query()->where('email', 'owner@wwork.test')->firstOrFail();
        $client = Client::query()->create([
            'agency_id' => $owner->membership->agency_id,
            'created_by' => $owner->id,
            'name' => 'Ana Costa',
            'phone' => '+447700900113',
            'email' => 'ana@example.com',
            'address' => '10 Downing Street, London',
            'lat' => 51.5,
            'lng' => -0.12,
        ]);
        Visit::query()->create([
            'agency_id' => $owner->membership->agency_id,
            'client_id' => $client->id,
            'assignee_id' => $owner->id,
            'service_date' => '2026-09-15',
            'service_time' => '09:00',
            'description' => 'Limpeza',
            'price_pence' => 8000,
            'payment_method' => 'cash',
            'payment_status' => 'paid',
            'lat' => 51.5,
            'lng' => -0.12,
            'status' => VisitStatus::Done,
        ]);

        $this->artisan('billing:email-month')->assertSuccessful();
        Mail::assertSent(MonthSummaryMail::class, fn (MonthSummaryMail $mail): bool => $mail->hasTo('owner@wwork.test') && $mail->services === 1 && $mail->cashPence === 8000);
    }

    public function test_a_paying_agency_keeps_a_weekly_series_going(): void
    {
        $owner = User::query()->where('email', 'owner@wwork.test')->firstOrFail();
        $client = Client::query()->create([
            'agency_id' => $owner->membership->agency_id,
            'created_by' => $owner->id,
            'name' => 'Ana Costa',
            'phone' => '+447700900114',
            'address' => '10 Downing Street, London',
            'lat' => 51.5,
            'lng' => -0.12,
        ]);
        Visit::query()->create([
            'agency_id' => $owner->membership->agency_id,
            'client_id' => $client->id,
            'assignee_id' => $owner->id,
            'service_date' => '2026-05-04',
            'service_time' => '09:00',
            'description' => 'Limpeza',
            'price_pence' => 8000,
            'is_recurring' => true,
            'recurring_days' => ['mon'],
            'lat' => 51.5,
            'lng' => -0.12,
            'status' => VisitStatus::Done,
        ]);

        $this->artisan('visits:extend-recurrence')->assertSuccessful();

        $this->assertTrue(Visit::query()->where('client_id', $client->id)->where('service_date', '>', '2026-10-20')->exists());
    }
}
