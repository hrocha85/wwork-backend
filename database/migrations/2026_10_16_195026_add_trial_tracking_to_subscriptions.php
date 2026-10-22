<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('subscriptions', 'offer_ends_at')) {
            Schema::table('subscriptions', function (Blueprint $table) {
                if (Schema::hasColumn('subscriptions', 'discount_type')) {
                    $table->timestamp('offer_ends_at')->nullable()->after('discount_type');
                } else {
                    $table->timestamp('offer_ends_at')->nullable();
                }
            });
        }

        if (! Schema::hasColumn('subscriptions', 'offer_reminded_at')) {
            Schema::table('subscriptions', function (Blueprint $table) {
                if (Schema::hasColumn('subscriptions', 'offer_ends_at')) {
                    $table->timestamp('offer_reminded_at')->nullable()->after('offer_ends_at');
                } else {
                    $table->timestamp('offer_reminded_at')->nullable();
                }
            });
        }

        if (! Schema::hasColumn('subscriptions', 'stripe_schedule_id')) {
            Schema::table('subscriptions', function (Blueprint $table) {
                if (Schema::hasColumn('subscriptions', 'stripe_price_id')) {
                    $table->string('stripe_schedule_id')->nullable()->after('stripe_price_id');
                } else {
                    $table->string('stripe_schedule_id')->nullable();
                }
            });
        }

        Schema::table('subscriptions', function (Blueprint $table) {
            if (! Schema::hasColumn('subscriptions', 'trial_plan')) {
                if (Schema::hasColumn('subscriptions', 'offer_reminded_at')) {
                    $table->string('trial_plan', 64)->nullable()->after('offer_reminded_at');
                } else {
                    $table->string('trial_plan', 64)->nullable();
                }
            }

            if (! Schema::hasColumn('subscriptions', 'trial_consumed_at')) {
                if (Schema::hasColumn('subscriptions', 'trial_plan')) {
                    $table->timestamp('trial_consumed_at')->nullable()->after('trial_plan');
                } else {
                    $table->timestamp('trial_consumed_at')->nullable();
                }
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $columns = array_values(array_filter([
                Schema::hasColumn('subscriptions', 'trial_consumed_at') ? 'trial_consumed_at' : null,
                Schema::hasColumn('subscriptions', 'trial_plan') ? 'trial_plan' : null,
            ]));

            if (! empty($columns)) {
                $table->dropColumn($columns);
            }
        });
    }
};
