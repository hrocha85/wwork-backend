<?php

namespace Tests\Feature;

use App\Filament\Pages\ChangePassword;
use App\Filament\Resources\Agencies\AgencyResource;
use App\Filament\Resources\Subscriptions\SubscriptionResource;
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

    public function test_login_is_light_and_the_sign_in_button_is_orange(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('--default-theme-mode: light', false)
            ->assertSee('wwork-sign-in', false)
            ->assertSee('#FB7E00', false)
            ->assertSee('#D1DFD2', false)
            ->assertSee('#1F2937', false)
            ->assertSee('#2C3848', false)
            ->assertSee('Staff only. The business signs in on the app, not here.', false)
            ->assertDontSee('MRR');
    }

    public function test_account_menu_can_switch_theme(): void
    {
        $founder = User::query()->where('email', 'founder@wwork.app')->firstOrFail();
        $founder->forceFill(['must_change_password' => false])->save();

        $this->actingAs($founder, 'staff');

        $this->get('/dashboard')
            ->assertOk()
            ->assertSee('fi-theme-switcher', false)
            ->assertSee('MRR')
            ->assertSee('Period')
            ->assertSee('New agencies')
            ->assertSee('Agencies created in the period.');
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

    public function test_staff_reaches_the_dashboard_without_a_new_password(): void
    {
        $founder = User::query()->where('email', 'founder@wwork.app')->firstOrFail();
        $this->assertTrue($founder->must_change_password);

        $this->actingAs($founder, 'staff');

        $this->get('/dashboard')
            ->assertOk()
            ->assertSee('New agencies')
            ->assertSee('Activity')
            ->assertDontSee('Visits');

        Livewire::test(ChangePassword::class)
            ->fillForm([
                'password' => 'new-staff-pass',
                'password_confirmation' => 'new-staff-pass',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $founder->refresh();
        $this->assertFalse($founder->must_change_password);
    }

    public function test_panel_copy_follows_portuguese(): void
    {
        $founder = User::query()->where('email', 'founder@wwork.app')->firstOrFail();
        $founder->forceFill(['must_change_password' => false, 'locale' => 'pt'])->save();

        $this->actingAs($founder, 'staff');

        $this->withHeader('Accept-Language', 'pt-BR')
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Período')
            ->assertSee('Agências criadas no período.')
            ->assertSee('Agências')
            ->assertSee('Trocar senha')
            ->assertSee('MRR em risco')
            ->assertSee('Assinaturas que pedem decisão');

        $this->withHeader('Accept-Language', 'pt-BR')
            ->get('/change-password')
            ->assertOk()
            ->assertSee('O painel não pede senha nova')
            ->assertSee('Voltar ao painel')
            ->assertSee('No mínimo 8 caracteres');
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
            ->assertSee('Visits')
            ->assertDontSee('SECRET-HOUSE-10-DOWNING');

        $this->get('/people')
            ->assertOk()
            ->assertSee('owner@wwork.test')
            ->assertSee('invited@wwork.test')
            ->assertDontSee('founder@wwork.app');
    }

    public function test_subscription_book_is_for_revenue_and_hides_houses(): void
    {
        $founder = User::query()->where('email', 'founder@wwork.app')->firstOrFail();
        $founder->forceFill(['must_change_password' => false])->save();
        $agency = Agency::query()->firstOrFail();

        Client::query()->create([
            'agency_id' => $agency->id,
            'created_by' => User::query()->where('email', 'owner@wwork.test')->firstOrFail()->id,
            'name' => 'Hidden House',
            'whatsapp' => '+447700900999',
            'address' => 'SECRET-HOUSE-10-DOWNING',
            'lat' => 51.5,
            'lng' => -0.12,
        ]);

        $this->actingAs($founder, 'staff');

        $this->get('/dashboard')
            ->assertOk()
            ->assertSee('MRR at risk')
            ->assertSee('Ending in 30 days')
            ->assertSee('Plans at the ceiling')
            ->assertSee('Subscriptions that need you');

        $this->get(SubscriptionResource::getUrl('index'))
            ->assertOk()
            ->assertSee('Demo Cleaning')
            ->assertSee('49.90 GBP')
            ->assertSee('Monthly')
            ->assertSee('2 / 3')
            ->assertDontSee('SECRET-HOUSE-10-DOWNING')
            ->assertDontSee('Hidden House');

        $this->get(SubscriptionResource::getUrl('view', ['record' => $agency->subscription]))
            ->assertOk()
            ->assertSee('Nothing due')
            ->assertSee('49.90 GBP')
            ->assertDontSee('SECRET-HOUSE-10-DOWNING');
    }

    public function test_support_does_not_see_subscription_money(): void
    {
        $support = User::query()->where('email', 'support@wwork.app')->firstOrFail();
        $support->forceFill(['must_change_password' => false])->save();

        $this->actingAs($support, 'staff');

        $this->get(SubscriptionResource::getUrl('index'))->assertForbidden();
        $this->get('/dashboard')
            ->assertOk()
            ->assertDontSee('MRR at risk')
            ->assertDontSee('49.90 GBP');
    }
}
