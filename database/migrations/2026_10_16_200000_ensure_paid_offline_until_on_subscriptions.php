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
        if (! Schema::hasColumn('subscriptions', 'paid_offline_until')) {
            Schema::table('subscriptions', function (Blueprint $table) {
                if (Schema::hasColumn('subscriptions', 'complimentary_until')) {
                    $table->dateTime('paid_offline_until')->nullable()->after('complimentary_until');
                } else {
                    $table->dateTime('paid_offline_until')->nullable();
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('subscriptions', 'paid_offline_until')) {
            Schema::table('subscriptions', function (Blueprint $table) {
                $table->dropColumn('paid_offline_until');
            });
        }
    }
};
