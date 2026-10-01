<?php

namespace Tests\Feature;

use App\Enums\SubscriptionStatus;
use App\Enums\VisitStatus;
use App\Mail\BookingRequestedMail;
use App\Mail\InviteAcceptedMail;
use App\Mail\NoticeMail;
use App\Mail\OwnerWelcomeMail;
use App\Mail\PartnerInvited;
use App\Mail\PasswordChangedMail;
use App\Mail\PasswordResetMail;
use App\Mail\PayoutPaidMail;
use App\Mail\QuoteAnsweredMail;
use App\Mail\SubscriptionStatusMail;
use App\Mail\VisitAnsweredMail;
use App\Mail\VisitCancelledMail;
use App\Mail\VisitOfferedMail;
use App\Models\Agency;
use App\Models\BookingRequest;
use App\Models\BookingService;
use App\Models\Client;
use App\Models\Invite;
use App\Models\Payout;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Visit;
use App\Services\Stripe\FakeStripeBilling;
use App\Services\Stripe\StripeBilling;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class NotificationMailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
        Carbon::setTestNow(Carbon::parse('2026-09-28 08:00:00', 'Europe/London'));
        $this->withHeader('referer', config('app.url'));
        $this->withHeader('Accept', 'application/json');
        $this->app->instance(StripeBilling::class, new FakeStripeBilling);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_public_booking_and_quote_answers_mail_only_the_owner(): void
    {
        Mail::fake();
        $this->login('owner@wwork.test');
        $saved = $this->putJson('/api/v1/booking', [
            'services' => [['name' => 'Regular clean', 'duration_minutes' => 60, 'price_pence' => 8000]],
            'hours' => [['weekday' => 'mon', 'starts' => '09:00', 'ends' => '11:00']],
        ])->assertOk();
        $token = basename((string) $saved->json('url'));
        $this->postJson('/api/v1/logout')->assertNoContent();

        $this->postJson('/api/v1/book/'.$token, [
            'service_id' => $saved->json('services.0.id'),
            'date' => '2026-09-28',
            'time' => '09:00',
            'name' => 'Ana Costa',
            'phone' => '+447700900999',
        ])->assertCreated();

        Mail::assertSent(BookingRequestedMail::class, 1);
        Mail::assertSent(BookingRequestedMail::class, function (BookingRequestedMail $mail): bool {
            $html = $mail->render();

            return $mail->hasTo('owner@wwork.test')
                && str_contains($html, 'Ana Costa')
                && str_contains($html, 'Regular clean')
                && str_contains($html, config('wwork.frontend_url').'/booking');
        });

        $opened = $this->postJson('/api/v1/book/'.$token.'/quotes', [
            'name' => 'Neide',
            'phone' => '+447700900998',
            'address' => 'Av pinheiro machado 535',
            'description' => 'A torneira não fecha.',
        ])->assertCreated();
        Mail::assertSent(BookingRequestedMail::class, 2);

        $this->login('owner@wwork.test');
        $id = BookingRequest::query()->where('public_token', $opened->json('public_token'))->value('id');
        $this->postJson('/api/v1/booking/requests/'.$id.'/reply', [
            'price_pence' => 12000,
            'note' => 'Troco o reparo da torneira.',
            'date' => '2026-09-29',
            'time' => '10:00',
        ])->assertOk();

        $this->postJson('/api/v1/book/'.$token.'/quotes/'.$opened->json('public_token'), ['accept' => true])->assertOk();

        Mail::assertSent(QuoteAnsweredMail::class, function (QuoteAnsweredMail $mail): bool {
            return $mail->accepted
                && $mail->hasTo('owner@wwork.test')
                && str_contains($mail->render(), '120.00');
        });
        Mail::assertNotSent(BookingRequestedMail::class, fn (BookingRequestedMail $mail): bool => ! $mail->hasTo('owner@wwork.test'));
    }

    public function test_offered_visit_mails_the_partner_and_each_answer_mails_the_owner(): void
    {
        Mail::fake();
        $owner = User::query()->where('email', 'owner@wwork.test')->firstOrFail();
        $partner = User::query()->where('email', 'invited@wwork.test')->firstOrFail();
        $client = $this->house($owner);
        $this->login('owner@wwork.test');

        $this->postJson('/api/v1/visits', $this->visitBody($client, $owner->id, '08:00'))->assertCreated();
        Mail::assertNotSent(VisitOfferedMail::class);

        $offered = $this->postJson('/api/v1/visits', $this->visitBody($client, $partner->id, '09:00'))->assertCreated();
        Mail::assertSent(VisitOfferedMail::class, function (VisitOfferedMail $mail): bool {
            $html = $mail->render();

            return $mail->hasTo('invited@wwork.test')
                && str_contains($html, '10 Downing Street')
                && str_contains($html, '48.00')
                && ! str_contains($html, '80.00');
        });

        $this->postJson('/api/v1/logout')->assertNoContent();
        $this->login('invited@wwork.test');
        $this->postJson('/api/v1/visits/'.$offered->json('id').'/accept')->assertOk();
        Mail::assertSent(VisitAnsweredMail::class, fn (VisitAnsweredMail $mail): bool => $mail->accepted && $mail->hasTo('owner@wwork.test'));

        $second = $this->offeredVisit($owner, $partner, $client);
        $this->postJson('/api/v1/visits/'.$second->id.'/decline')->assertOk();
        Mail::assertSent(VisitAnsweredMail::class, fn (VisitAnsweredMail $mail): bool => ! $mail->accepted && $mail->hasTo('owner@wwork.test'));

        $this->postJson('/api/v1/logout')->assertNoContent();
        $this->login('owner@wwork.test');
        $this->deleteJson('/api/v1/visits/'.$offered->json('id'))->assertNoContent();
        Mail::assertSent(VisitCancelledMail::class, function (VisitCancelledMail $mail): bool {
            return $mail->hasTo('invited@wwork.test') && str_contains($mail->render(), '10 Downing Street');
        });

        $this->deleteJson('/api/v1/visits/'.$second->id)->assertNoContent();
        Mail::assertSent(VisitCancelledMail::class, 1);
    }

    public function test_reassigning_a_visit_to_the_partner_offers_it_once(): void
    {
        Mail::fake();
        $owner = User::query()->where('email', 'owner@wwork.test')->firstOrFail();
        $partner = User::query()->where('email', 'invited@wwork.test')->firstOrFail();
        $client = $this->house($owner);
        $this->login('owner@wwork.test');

        $visit = $this->postJson('/api/v1/visits', $this->visitBody($client, $owner->id, '08:00'))->assertCreated();
        $this->patchJson('/api/v1/visits/'.$visit->json('id'), ['assignee_id' => $partner->id])->assertOk();
        $this->patchJson('/api/v1/visits/'.$visit->json('id'), ['assignee_id' => $partner->id])->assertOk();

        Mail::assertSent(VisitOfferedMail::class, 1);
    }

    public function test_stripe_webhooks_mail_the_owner_only_when_the_status_changes(): void
    {
        Mail::fake();
        $subscription = Subscription::query()->firstOrFail();
        $subscription->forceFill(['stripe_id' => 'sub_demo', 'status' => SubscriptionStatus::Active])->save();

        foreach (['invoice.payment_succeeded', 'invoice.payment_failed', 'invoice.payment_failed', 'invoice.payment_succeeded', 'invoice.payment_succeeded'] as $type) {
            $this->postJson('/api/v1/stripe/webhook', ['type' => $type, 'data' => ['subscription' => 'sub_demo']])->assertOk();
        }

        Mail::assertSent(SubscriptionStatusMail::class, 2);
        Mail::assertSent(SubscriptionStatusMail::class, fn (SubscriptionStatusMail $mail): bool => $mail->status === SubscriptionStatus::PastDue && $mail->hasTo('owner@wwork.test'));
        Mail::assertSent(SubscriptionStatusMail::class, fn (SubscriptionStatusMail $mail): bool => $mail->status === SubscriptionStatus::Active);

        $this->postJson('/api/v1/stripe/webhook', ['type' => 'customer.subscription.deleted', 'data' => ['subscription' => 'sub_demo']])->assertOk();
        Mail::assertSent(SubscriptionStatusMail::class, fn (SubscriptionStatusMail $mail): bool => $mail->status === SubscriptionStatus::Cancelled);
    }

    public function test_accepted_invite_mails_the_owner(): void
    {
        Mail::fake();
        $this->login('owner@wwork.test');
        $this->postJson('/api/v1/partners', ['email' => 'new.partner@wwork.test', 'rate' => 50])->assertCreated();

        $plain = null;
        Mail::assertSent(PartnerInvited::class, function (PartnerInvited $mail) use (&$plain): bool {
            $plain = basename($mail->url);

            return true;
        });
        $this->postJson('/api/v1/logout')->assertNoContent();

        $this->postJson('/api/v1/invites/'.$plain.'/accept', [
            'name' => 'New Partner',
            'password' => 'secret-123',
            'locale' => 'pt',
            'terms_accepted' => true,
        ])->assertSuccessful();

        Mail::assertSent(InviteAcceptedMail::class, function (InviteAcceptedMail $mail): bool {
            return $mail->hasTo('owner@wwork.test') && str_contains($mail->render(), 'New Partner');
        });
    }

    public function test_marking_a_payout_paid_mails_the_partner_once(): void
    {
        Mail::fake();
        $owner = User::query()->where('email', 'owner@wwork.test')->firstOrFail();
        $partner = User::query()->where('email', 'invited@wwork.test')->firstOrFail();
        $visit = $this->offeredVisit($owner, $partner, $this->house($owner));
        $payout = Payout::query()->create([
            'agency_id' => $owner->membership->agency_id,
            'visit_id' => $visit->id,
            'user_id' => $partner->id,
            'amount_pence' => 2400,
            'paid' => false,
        ]);
        $this->login('owner@wwork.test');

        $this->postJson('/api/v1/payouts/'.$payout->id.'/paid')->assertOk();
        $this->postJson('/api/v1/payouts/'.$payout->id.'/paid')->assertOk();

        Mail::assertSent(PayoutPaidMail::class, 1);
        Mail::assertSent(PayoutPaidMail::class, fn (PayoutPaidMail $mail): bool => $mail->hasTo('invited@wwork.test') && str_contains($mail->render(), '24.00'));
    }

    public function test_register_welcomes_the_owner_without_a_password_and_change_password_warns(): void
    {
        Mail::fake();
        $this->postJson('/api/v1/register', [
            'agency_name' => 'Maya Cleaning Ltd',
            'name' => 'Maya Silva',
            'email' => 'maya@example.com',
            'password' => 'secret123',
            'locale' => 'pt',
            'trade' => 'cleaning',
            'payment_method' => 'pm_card_visa',
            'terms_accepted' => true,
        ])->assertCreated();

        Mail::assertSent(OwnerWelcomeMail::class, function (OwnerWelcomeMail $mail): bool {
            return $mail->hasTo('maya@example.com') && $mail->temporaryPassword === null && $mail->locale === 'pt';
        });

        $this->postJson('/api/v1/password/change', [
            'current_password' => 'secret123',
            'password' => 'secret456',
            'password_confirmation' => 'secret456',
        ])->assertOk();
        Mail::assertSent(PasswordChangedMail::class, fn (PasswordChangedMail $mail): bool => $mail->hasTo('maya@example.com'));
    }

    public function test_smtp_failure_keeps_the_request_and_records_mail_failed(): void
    {
        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => '127.0.0.1',
            'mail.mailers.smtp.port' => 1,
            'mail.mailers.smtp.timeout' => 2,
        ]);
        $this->login('owner@wwork.test');

        $this->postJson('/api/v1/partners', ['email' => 'smtp.down@wwork.test', 'rate' => 50])->assertCreated();

        $this->assertDatabaseHas('invites', ['email' => 'smtp.down@wwork.test']);
        $this->assertDatabaseHas('activities', ['action' => 'mail.failed']);
    }

    public function test_every_notice_renders_in_every_locale_without_raw_keys(): void
    {
        $agency = Agency::query()->firstOrFail();
        $owner = User::query()->where('email', 'owner@wwork.test')->firstOrFail();
        $partner = User::query()->where('email', 'invited@wwork.test')->firstOrFail();
        $client = Client::query()->findOrFail($this->house($owner));
        $visit = $this->offeredVisit($owner, $partner, $client);
        $service = BookingService::query()->create(['agency_id' => $agency->id, 'name' => 'Regular clean', 'duration_minutes' => 60, 'price_pence' => 8000]);
        $slot = BookingRequest::query()->create([
            'agency_id' => $agency->id, 'booking_service_id' => $service->id, 'client_name' => 'Ana', 'client_phone' => '+447700900999',
            'requested_date' => '2026-09-28', 'requested_time' => '09:00', 'status' => 'pending',
        ]);
        $quote = BookingRequest::query()->create([
            'agency_id' => $agency->id, 'client_name' => 'Neide', 'client_phone' => '+447700900998', 'kind' => 'quote',
            'address' => '1 Road', 'description' => 'Tap', 'status' => 'quoted', 'quote_pence' => 12000,
            'proposed_date' => '2026-09-29', 'proposed_time' => '10:00',
        ]);
        $invite = Invite::query()->create([
            'agency_id' => $agency->id, 'email' => 'x@wwork.test', 'rate' => 50, 'invited_by' => $owner->id,
            'expires_at' => now()->addDays(Invite::TTL_DAYS), 'sent_at' => now(),
        ]);
        $payout = Payout::query()->create(['agency_id' => $agency->id, 'visit_id' => $visit->id, 'user_id' => $partner->id, 'amount_pence' => 2400, 'paid' => true]);

        /** @var list<NoticeMail> $mails */
        $mails = [
            new PartnerInvited($invite, 'https://app.test/invites/abc'),
            new InviteAcceptedMail('New Partner', 'new@wwork.test'),
            new OwnerWelcomeMail('Maya', 'Maya Ltd', 'maya@example.com'),
            new OwnerWelcomeMail('Maya', 'Maya Ltd', 'maya@example.com', 'temp-pass'),
            new PasswordResetMail('https://app.test/reset-password?token=t', 60),
            new PasswordChangedMail('Maya'),
            new BookingRequestedMail($slot),
            new BookingRequestedMail($quote),
            new QuoteAnsweredMail($quote, true),
            new QuoteAnsweredMail($quote, false),
            new VisitOfferedMail($visit),
            new VisitAnsweredMail($visit, 'Partner', true),
            new VisitAnsweredMail($visit, 'Partner', false),
            new VisitCancelledMail('Agency', '2026-09-28', '15:00:00', '1 Road'),
            new SubscriptionStatusMail(SubscriptionStatus::PastDue, 'Agency'),
            new SubscriptionStatusMail(SubscriptionStatus::Active, 'Agency'),
            new SubscriptionStatusMail(SubscriptionStatus::Cancelled, 'Agency'),
            new PayoutPaidMail($payout),
        ];

        foreach (['en', 'pt', 'es', 'pl', 'ro'] as $locale) {
            foreach ($mails as $mail) {
                $mail->locale($locale);
                $html = $mail->render();
                app()->setLocale($locale);
                $subject = $mail->envelope()->subject;

                $this->assertDoesNotMatchRegularExpression('/\bmail\.[a-z_]+\.[a-z_]+/', $html, $mail::class.' '.$locale);
                $this->assertDoesNotMatchRegularExpression('/\bmail\.[a-z_]+\.[a-z_]+/', (string) $subject, $mail::class.' '.$locale);
                $this->assertStringContainsString('WWork', $html);
            }
        }
        app()->setLocale('en');
    }

    private function login(string $email): void
    {
        $this->postJson('/api/v1/login', ['email' => $email, 'password' => 'demo-seed-test'])->assertOk();
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
    private function visitBody(int $client, int $assignee, string $time): array
    {
        return [
            'client_id' => $client,
            'date' => '2026-09-28',
            'time' => $time,
            'price_pence' => 8000,
            'assignee_id' => $assignee,
            'lat' => 51.5,
            'lng' => -0.1,
            'goals' => [['text' => 'Clean kitchen']],
        ];
    }

    private function offeredVisit(User $owner, User $partner, int|Client $client): Visit
    {
        return Visit::query()->create([
            'agency_id' => $owner->membership->agency_id,
            'client_id' => $client instanceof Client ? $client->id : $client,
            'assignee_id' => $partner->id,
            'service_date' => '2026-09-28',
            'service_time' => '15:00:00',
            'price_pence' => 4000,
            'rate' => 60,
            'partner_earning_pence' => 2400,
            'lat' => 51.5,
            'lng' => -0.1,
            'status' => VisitStatus::Offered,
        ]);
    }
}
