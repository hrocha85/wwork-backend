<?php

namespace Tests\Feature;

use App\Filament\Pages\ChangePassword;
use App\Filament\Resources\Agencies\AgencyResource;
use App\Models\Agency;
use App\Models\Client;
use App\Models\User;
use Filament\Auth\Pages\Login;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Tests\TestCase;

class AdminPanelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();

        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_health_endpoint_is_public(): void
    {
        $this->get('/up')->assertOk();
    }

    public function test_founder_logs_in_and_owner_does_not(): void
    {
        $this->get('/')->assertOk();
        $this->get('/admin/login')->assertNotFound();

        Livewire::test(Login::class)
            ->fillForm([
                'email' => 'founder@wwork.app',
                'password' => 'staff-seed-test',
            ])
            ->call('authenticate')
            ->assertHasNoFormErrors();

        $this->assertTrue(Auth::guard('staff')->check());
        $this->assertFalse(Auth::guard('web')->check());

        Auth::guard('staff')->logout();

        Livewire::test(Login::class)
            ->fillForm([
                'email' => 'owner@wwork.test',
                'password' => 'demo-seed-test',
            ])
            ->call('authenticate')
            ->assertHasFormErrors(['email']);

        $this->assertFalse(Auth::guard('staff')->check());
    }

    public function test_web_session_does_not_open_the_panel(): void
    {
        $founder = User::query()->where('email', 'founder@wwork.app')->firstOrFail();

        $this->actingAs($founder, 'web');

        $this->get('/dashboard')->assertRedirect('/');
        $this->assertFalse(Auth::guard('staff')->check());
    }

    public function test_staff_must_change_password_before_the_dashboard(): void
    {
        $founder = User::query()->where('email', 'founder@wwork.app')->firstOrFail();

        $this->actingAs($founder, 'staff');

        $this->get('/dashboard')->assertRedirect(ChangePassword::getUrl());

        Livewire::test(ChangePassword::class)
            ->fillForm([
                'password' => 'new-staff-pass',
                'password_confirmation' => 'new-staff-pass',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $founder->refresh();
        $this->assertFalse($founder->must_change_password);

        session()->forget('password_hash_staff');
        session()->forget('password_hash_web');
        $this->actingAs($founder, 'staff');

        $this->get('/dashboard')
            ->assertOk()
            ->assertSee('Cadastros')
            ->assertSee('Atividade')
            ->assertDontSee('Visitas');
    }

    public function test_agency_list_hides_houses_and_has_no_wallet_resources(): void
    {
        $founder = User::query()->where('email', 'founder@wwork.app')->firstOrFail();
        $founder->forceFill(['must_change_password' => false])->save();

        $agency = Agency::query()->firstOrFail();
        $owner = User::query()->where('email', 'owner@wwork.test')->firstOrFail();

        Client::query()->create([
            'agency_id' => $agency->id,
            'created_by' => $owner->id,
            'name' => 'Hidden House',
            'whatsapp' => '+447700900999',
            'address' => 'SECRET-HOUSE-10-DOWNING',
            'lat' => 51.5,
            'lng' => -0.12,
        ]);

        $this->actingAs($founder, 'staff');

        $uris = collect(Route::getRoutes())->map->uri()->all();
        $this->assertNotContains('admin/visits', $uris);
        $this->assertNotContains('visits', $uris);
        $this->assertNotContains('clients', $uris);
        $this->assertNotContains('invoices', $uris);
        $this->assertNotContains('payouts', $uris);

        $this->get(AgencyResource::getUrl('index'))
            ->assertOk()
            ->assertSee('Demo Cleaning')
            ->assertDontSee('SECRET-HOUSE-10-DOWNING')
            ->assertDontSee('Hidden House');

        $this->get(AgencyResource::getUrl('view', ['record' => $agency]))
            ->assertOk()
            ->assertSee('Demo Cleaning')
            ->assertSee('Visitas')
            ->assertDontSee('SECRET-HOUSE-10-DOWNING');

        $this->get('/people')
            ->assertOk()
            ->assertSee('owner@wwork.test')
            ->assertSee('invited@wwork.test')
            ->assertDontSee('founder@wwork.app');
    }
}
