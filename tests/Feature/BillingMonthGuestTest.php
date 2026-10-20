<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Enums\VisitStatus;
use App\Mail\AccessCodeMail;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class BillingMonthGuestTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
        $this->withHeader('referer', config('app.url'));
        $this->withHeader('Accept', 'application/json');
        Carbon::setTestNow('2026-10-20 12:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_billing_sums_only_the_lines_of_the_requested_month(): void
    {
        $owner = $this->owner();
        $client = $this->client($owner);
        $invoice = Invoice::query()->create([
            'agency_id' => $owner->membership->agency_id,
            'client_id' => $client->id,
            'created_by' => $owner->id,
            'number' => 1,
            'status' => InvoiceStatus::Paid,
            'total_pence' => 13000,
            'locale' => 'en',
            'invoice_region' => 'GB',
            'paid_at' => '2026-10-05 10:00:00',
        ]);
        $invoice->lines()->create(['visit_id' => $this->visit($owner, $client, '2026-09-10', 8000)->id, 'service_date' => '2026-09-10', 'description' => 'Sep', 'price_pence' => 8000]);
        $invoice->lines()->create(['visit_id' => $this->visit($owner, $client, '2026-10-10', 5000)->id, 'service_date' => '2026-10-10', 'description' => 'Oct', 'price_pence' => 5000]);

        $this->login();
        $this->getJson('/api/v1/billing?period=month&month=2026-09')
            ->assertOk()
            ->assertJsonPath('totals.invoiced_pence', 8000)
            ->assertJsonPath('totals.received_pence', 8000)
            ->assertJsonPath('invoices.0.total_pence', 8000)
            ->assertJsonPath('invoices.0.service_date', '2026-09-10');

        $this->getJson('/api/v1/billing?period=month')
            ->assertOk()
            ->assertJsonPath('totals.invoiced_pence', 5000);

        $this->getJson('/api/v1/billing?period=month&month=2026-11')
            ->assertStatus(422)
            ->assertExactJson(['error' => 'billing.month_future']);
    }

    public function test_pending_invoices_follow_the_month_window(): void
    {
        $owner = $this->owner();
        $client = $this->client($owner);
        $this->visit($owner, $client, '2026-09-10', 8000);
        $this->visit($owner, $client, '2026-10-10', 5000);

        $this->login();
        $september = $this->getJson('/api/v1/invoices/pending?period=month&month=2026-09')->assertOk();
        $this->assertSame([8000], collect($september->json())->pluck('total_pence')->all());

        $all = $this->getJson('/api/v1/invoices/pending')->assertOk();
        $this->assertSame(13000, (int) collect($all->json())->sum('total_pence'));
    }

    public function test_currency_change_moves_the_invoice_region_and_payment_link_is_saved(): void
    {
        $this->login();
        $response = $this->patchJson('/api/v1/agency', [
            'currency' => 'BRL',
            'payment_link' => 'https://pay.example.com/wwork',
        ])->assertOk();

        $this->assertDatabaseHas('agencies', ['currency' => 'BRL', 'invoice_region' => 'BR', 'payment_link' => 'https://pay.example.com/wwork']);
        $response->assertJsonPath('agency.payment_link', 'https://pay.example.com/wwork');
        $response->assertJsonPath('agency.invoice_region', 'BR');
    }

    public function test_guest_link_shows_the_visit_reviews_once_and_code_opens_history(): void
    {
        Mail::fake();
        $owner = $this->owner();
        $client = $this->client($owner, 'guest@example.com');
        $visit = $this->visit($owner, $client, '2026-10-10', 8000);
        $invoice = Invoice::query()->create([
            'agency_id' => $owner->membership->agency_id,
            'client_id' => $client->id,
            'created_by' => $owner->id,
            'number' => 1,
            'status' => InvoiceStatus::Paid,
            'total_pence' => 8000,
            'locale' => 'en',
            'invoice_region' => 'GB',
            'paid_at' => '2026-10-10 10:00:00',
        ]);
        $invoice->lines()->create(['visit_id' => $visit->id, 'service_date' => '2026-10-10', 'description' => 'Clean', 'price_pence' => 8000]);
        $token = app(\App\Actions\Marketplace\GuestVisitAccess::class)->issue($visit);

        $this->getJson('/api/v1/guest/visits/'.$token)
            ->assertOk()
            ->assertJsonPath('status', 'done')
            ->assertJsonPath('invoice.status', 'paid')
            ->assertJsonPath('reviewed', false);

        $this->postJson('/api/v1/guest/visits/'.$token.'/reviews', ['rating' => 5, 'comment' => 'Great'])->assertCreated();
        $this->postJson('/api/v1/guest/visits/'.$token.'/reviews', ['rating' => 4])->assertStatus(422);
        $this->assertDatabaseCount('reviews', 1);

        $this->postJson('/api/v1/guest/access-code', ['email' => 'guest@example.com'])->assertOk();
        $code = null;
        Mail::assertSent(AccessCodeMail::class, function (AccessCodeMail $mail) use (&$code): bool {
            $code = $mail->code;

            return true;
        });
        $this->postJson('/api/v1/guest/access-code/verify', ['email' => 'guest@example.com', 'code' => $code])
            ->assertOk()
            ->assertJsonPath('visits.0.visit_id', $visit->id)
            ->assertJsonPath('invoices.0.id', $invoice->id)
            ->assertJsonPath('invoices.0.currency', 'GBP');
    }

    public function test_mark_paid_records_how_it_was_paid_and_pix_is_brazil_only(): void
    {
        $owner = $this->owner();
        $client = $this->client($owner);
        $make = function (int $number) use ($owner, $client): Invoice {
            $invoice = Invoice::query()->create([
                'agency_id' => $owner->membership->agency_id,
                'client_id' => $client->id,
                'created_by' => $owner->id,
                'number' => $number,
                'status' => InvoiceStatus::Sent,
                'total_pence' => 5000,
                'locale' => 'en',
                'invoice_region' => 'GB',
            ]);
            $invoice->lines()->create(['visit_id' => $this->visit($owner, $client, '2026-10-10', 5000)->id, 'service_date' => '2026-10-10', 'description' => 'Clean', 'price_pence' => 5000]);

            return $invoice;
        };
        $invoice = $make(1);
        $brazil = $make(2);

        $this->login();
        // Agência GB (seed): Pix não existe fora do Brasil.
        $this->postJson('/api/v1/invoices/'.$invoice->id.'/paid', ['paid_method' => 'pix'])
            ->assertStatus(422)
            ->assertExactJson(['error' => 'invoice.pix_only_brazil']);
        $this->postJson('/api/v1/invoices/'.$invoice->id.'/paid', ['paid_method' => 'cheque'])
            ->assertStatus(422)
            ->assertExactJson(['error' => 'invoice.paid_method_invalid']);

        $this->postJson('/api/v1/invoices/'.$invoice->id.'/paid', ['paid_method' => 'card'])
            ->assertOk()
            ->assertJsonPath('paid_method', 'card');
        $this->assertDatabaseHas('invoices', ['id' => $invoice->id, 'status' => 'paid', 'paid_method' => 'card']);
        $this->getJson('/api/v1/invoices/'.$invoice->id)->assertOk()->assertJsonPath('paid_method', 'card');
        $this->getJson('/api/v1/billing?period=month&month=2026-10')
            ->assertOk()
            ->assertJsonPath('invoices.0.paid_method', 'card');

        // Mesma agência operando em reais: Pix passa a ser aceito.
        $owner->membership->agency->forceFill(['currency' => 'BRL', 'invoice_region' => 'BR'])->save();
        $this->postJson('/api/v1/invoices/'.$brazil->id.'/paid', ['paid_method' => 'pix'])
            ->assertOk()
            ->assertJsonPath('paid_method', 'pix');
    }

    public function test_backfill_creates_paid_invoices_for_old_cash_visits(): void
    {
        $owner = $this->owner();
        $client = $this->client($owner);
        $visit = $this->visit($owner, $client, '2026-09-10', 8000);
        $visit->forceFill(['payment_method' => 'cash', 'payment_status' => 'paid'])->save();

        $this->artisan('billing:backfill-cash-invoices')->assertSuccessful();

        $this->assertDatabaseHas('invoices', ['client_id' => $client->id, 'status' => 'paid', 'total_pence' => 8000, 'paid_method' => 'cash']);
        $this->assertDatabaseHas('invoice_lines', ['visit_id' => $visit->id, 'price_pence' => 8000]);

        $this->artisan('billing:backfill-cash-invoices')->assertSuccessful();
        $this->assertDatabaseCount('invoices', 1);
    }

    private function owner(): User
    {
        return User::query()->where('email', 'owner@wwork.test')->firstOrFail();
    }

    private function login(): void
    {
        $this->postJson('/api/v1/login', ['email' => 'owner@wwork.test', 'password' => 'demo-seed-test'])->assertOk();
    }

    private function client(User $owner, ?string $email = null): Client
    {
        return Client::query()->create([
            'agency_id' => $owner->membership->agency_id,
            'created_by' => $owner->id,
            'name' => 'Ana Costa',
            'phone' => '+447700900111',
            'email' => $email,
            'address' => '10 Downing Street, London',
            'lat' => 51.5034,
            'lng' => -0.1276,
        ]);
    }

    private function visit(User $owner, Client $client, string $date, int $price): Visit
    {
        return Visit::query()->create([
            'agency_id' => $owner->membership->agency_id,
            'client_id' => $client->id,
            'assignee_id' => $owner->id,
            'service_date' => $date,
            'service_time' => '09:00',
            'description' => 'Clean',
            'price_pence' => $price,
            'lat' => 51.5034,
            'lng' => -0.1276,
            'status' => VisitStatus::Done,
        ]);
    }
}
