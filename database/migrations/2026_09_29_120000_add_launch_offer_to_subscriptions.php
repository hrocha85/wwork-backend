<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->timestamp('offer_ends_at')->nullable()->after('discount_type');
            $table->timestamp('offer_reminded_at')->nullable()->after('offer_ends_at');
            $table->string('stripe_schedule_id')->nullable()->after('stripe_price_id');
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn(['offer_ends_at', 'offer_reminded_at', 'stripe_schedule_id']);
        });
    }
};
