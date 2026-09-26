<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class InvoiceProfileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
        $this->withHeader('referer', config('app.url'));
        $this->withHeader('Accept', 'application/json');
        Storage::fake('local');
    }

    public function test_owner_saves_invoice_details_and_logo_and_invited_cannot(): void
    {
        $this->postJson('/api/v1/login', [
            'email' => 'invited@wwork.test',
            'password' => 'demo-seed-test',
        ])->assertOk();

        $this->patchJson('/api/v1/agency', [
            'phone' => '+44 20 7946 0000',
        ])->assertForbidden()->assertExactJson(['error' => 'agency.not_owner']);

        $this->postJson('/api/v1/logout')->assertNoContent();
        $this->postJson('/api/v1/login', [
            'email' => 'owner@wwork.test',
            'password' => 'demo-seed-test',
        ])->assertOk();

        $this->patchJson('/api/v1/agency', [
            'legal_address' => '12 Example Street, London',
            'phone' => '+44 20 7946 0000',
            'payment_method' => 'bank_transfer',
            'payment_details' => 'Sort code 00-00-00',
            'vat_registered' => true,
        ])->assertStatus(422)->assertExactJson(['error' => 'agency.tax_id_required']);

        $saved = $this->patchJson('/api/v1/agency', [
            'legal_address' => '12 Example Street, London',
            'phone' => '+44 20 7946 0000',
            'payment_method' => 'bank_transfer',
            'payment_details' => 'Sort code 00-00-00',
            'vat_registered' => true,
            'tax_id' => 'GB123456789',
        ])->assertOk();

        $saved->assertJsonPath('agency.phone', '+44 20 7946 0000')
            ->assertJsonPath('agency.payment_method', 'bank_transfer')
            ->assertJsonPath('agency.has_logo', false);

        $this->post('/api/v1/agency/logo', [
            'logo' => UploadedFile::fake()->image('logo.png'),
        ])->assertOk()->assertJsonPath('agency.has_logo', true);

        $owner = User::query()->where('email', 'owner@wwork.test')->firstOrFail();
        Storage::disk('local')->assertExists('agencies/'.$owner->membership->agency_id.'/logo.png');
        $this->get('/api/v1/agency/logo')->assertOk();

        $this->postJson('/api/v1/logout')->assertNoContent();
        $hidden = $this->postJson('/api/v1/login', [
            'email' => 'invited@wwork.test',
            'password' => 'demo-seed-test',
        ])->assertOk();
        $hidden->assertJsonMissingPath('agency.payment_details');
        $this->get('/api/v1/agency/logo')->assertForbidden();
    }
}
