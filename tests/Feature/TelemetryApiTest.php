<?php

namespace Tests\Feature;

use App\Enums\Locale;
use App\Enums\MembershipRole;
use App\Enums\StaffPermissionCode;
use App\Enums\StaffProfileCode;
use App\Enums\Trade;
use App\Filament\Widgets\AppInstallsStats;
use App\Models\Agency;
use App\Models\InstallClick;
use App\Models\Membership;
use App\Models\StaffPermission;
use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TelemetryApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_anonymous_user_can_record_install_click(): void
    {
        $response = $this->postJson('/api/v1/telemetry/install-click', [
            'platform' => 'pwa',
            'timestamp' => '2026-10-22T11:45:00.000Z',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertDatabaseHas('install_clicks', [
            'platform' => 'pwa',
            'user_id' => null,
            'agency_id' => null,
        ]);

        $click = InstallClick::first();
        $this->assertNotNull($click);
        $this->assertSame('pwa', $click->platform);
        $this->assertSame('2026-10-22 11:45:00', $click->clicked_at->format('Y-m-d H:i:s'));
    }

    public function test_authenticated_user_records_user_and_agency_on_install_click(): void
    {
        $user = User::factory()->create();
        $agency = Agency::create([
            'name' => 'Agency Test',
            'trade' => Trade::Cleaning,
            'country' => 'GB',
            'currency' => 'GBP',
            'timezone' => 'Europe/London',
            'locale' => Locale::En,
        ]);
        Membership::create([
            'agency_id' => $agency->id,
            'user_id' => $user->id,
            'role' => MembershipRole::Owner,
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/telemetry/install-click', [
            'platform' => 'apple',
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('install_clicks', [
            'platform' => 'apple',
            'user_id' => $user->id,
            'agency_id' => $agency->id,
        ]);
    }

    public function test_validates_platform(): void
    {
        $response = $this->postJson('/api/v1/telemetry/install-click', [
            'platform' => 'invalid_platform',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['platform']);
    }

    public function test_app_installs_stats_widget_counts_clicks_correctly(): void
    {
        Carbon::setTestNow('2026-10-22 12:00:00');

        InstallClick::create([
            'platform' => 'pwa',
            'clicked_at' => now()->subDays(2),
        ]);
        InstallClick::create([
            'platform' => 'apple',
            'clicked_at' => now()->subDays(3),
        ]);
        InstallClick::create([
            'platform' => 'android',
            'clicked_at' => now()->subDays(1),
        ]);
        InstallClick::create([
            'platform' => 'android',
            'clicked_at' => now()->subDays(40), // Out of 30-day window
        ]);

        $widget = new class extends AppInstallsStats
        {
            public function setTestFilters(array $filters): void
            {
                $this->pageFilters = $filters;
            }

            public function getTestStats(): array
            {
                return $this->getStats();
            }
        };

        $widget->setTestFilters(['period' => '30']);
        $stats = $widget->getTestStats();

        // 4 stats: Total, PWA, iOS, Android
        $this->assertCount(4, $stats);
        $this->assertSame('3', $stats[0]->getValue()); // Total inside 30 days
        $this->assertSame('1', $stats[1]->getValue()); // PWA
        $this->assertSame('1', $stats[2]->getValue()); // Apple iOS
        $this->assertSame('1', $stats[3]->getValue()); // Android
    }

    public function test_can_view_respects_staff_permission(): void
    {
        $this->assertFalse(AppInstallsStats::canView());

        $user = User::factory()->create();
        auth('staff')->login($user);
        $this->assertFalse(AppInstallsStats::canView());

        // Now with permission
        $profile = StaffProfile::create([
            'code' => StaffProfileCode::Founder,
            'name' => 'Founder',
        ]);
        $permission = StaffPermission::create([
            'code' => StaffPermissionCode::ViewMetrics,
        ]);
        $profile->permissions()->attach($permission);

        $user->staff_profile_id = $profile->id;
        $user->save();
        $user->unsetRelation('staffProfile');
        auth('staff')->setUser($user->fresh());

        $this->assertTrue(AppInstallsStats::canView());
    }
}
