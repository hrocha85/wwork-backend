<?php

namespace Tests\Feature;

use App\Filament\Widgets\EmptyDashboardStats;
use App\Models\Subscription;
use App\Models\User;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SchemaIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_subscriptions_table_contains_all_filament_critical_columns(): void
    {
        $this->assertTrue(Schema::hasTable('subscriptions'), 'Table subscriptions does not exist.');

        $expectedColumns = [
            'id',
            'agency_id',
            'plan',
            'status',
            'seats',
            'amount_minor',
            'currency',
            'billing',
            'discount_type',
            'cancel_at',
            'complimentary_until',
            'stripe_id',
            'stripe_price_id',
            'stripe_status',
            'paid_offline_until',
            'offer_ends_at',
            'offer_reminded_at',
            'stripe_schedule_id',
        ];

        foreach ($expectedColumns as $column) {
            $this->assertTrue(
                Schema::hasColumn('subscriptions', $column),
                "Expected column '{$column}' is missing from 'subscriptions' table."
            );
        }
    }

    public function test_filament_dashboard_queries_execute_without_missing_column_errors(): void
    {
        // 1. Scope needsAttention used by SubscriptionAttention widget
        $queryResult = Subscription::query()->needsAttention()->get();
        $this->assertNotNull($queryResult);

        // 2. Query used by EmptyDashboardStats endingSoon
        $soon = Carbon::now()->addDays(7);
        $count = Subscription::query()
            ->where('stripe_status', 'active')
            ->where(function ($query) use ($soon) {
                $query->whereNotNull('ends_at')
                    ->where('ends_at', '<=', $soon)
                    ->orWhere(function ($q) use ($soon) {
                        $q->whereNotNull('paid_offline_until')
                            ->where('paid_offline_until', '<=', $soon);
                    });
            })
            ->count();

        $this->assertIsInt($count);
    }

    public function test_migrations_are_idempotent_and_can_be_reapplied_safely(): void
    {
        // Calling migrate again must succeed with 0 exit code
        $exitCode = Artisan::call('migrate', ['--force' => true]);
        $this->assertSame(0, $exitCode);
    }

    public function test_empty_dashboard_stats_ending_soon_renders_cleanly(): void
    {
        $widget = new EmptyDashboardStats;
        $reflector = new \ReflectionClass(EmptyDashboardStats::class);
        $method = $reflector->getMethod('endingSoon');
        $method->setAccessible(true);

        $result = $method->invoke($widget);
        $this->assertIsString($result);
        $this->assertNotSame('⚠️ Erro de schema', $result);
    }
}
