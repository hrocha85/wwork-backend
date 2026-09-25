<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_prices', function (Blueprint $table) {
            $table->id();
            $table->char('country', 2);
            $table->string('plan', 64);
            $table->string('billing', 16);
            $table->string('discount_type', 32);
            $table->unsignedInteger('amount_minor');
            $table->unsignedInteger('extra_seat_minor')->nullable();
            $table->char('currency', 3);
            $table->string('stripe_price_id')->nullable();
            $table->timestamps();
            $table->unique(
                ['country', 'plan', 'billing', 'discount_type'],
                'plan_prices_identity',
            );
            $table->index('country');
        });

        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->unique()->constrained();
            $table->string('plan', 64);
            $table->string('status', 32);
            $table->unsignedInteger('seats');
            $table->unsignedInteger('amount_minor');
            $table->char('currency', 3);
            $table->string('billing', 16);
            $table->string('discount_type', 32);
            $table->dateTime('cancel_at')->nullable();
            $table->dateTime('complimentary_until')->nullable();
            $table->string('stripe_id')->nullable()->unique();
            $table->string('stripe_price_id')->nullable();
            $table->string('stripe_status')->nullable();
            $table->timestamps();
        });

        Schema::create('activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 64);
            $table->nullableMorphs('subject');
            $table->json('properties')->nullable();
            $table->timestamps();
            $table->index(['agency_id', 'created_at']);
        });

        Schema::create('sync_deletions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained();
            $table->string('table_name', 64);
            $table->uuid('sync_uuid');
            $table->dateTime('created_at')->useCurrent();
            $table->unique(['table_name', 'sync_uuid']);
            $table->index(['agency_id', 'table_name', 'created_at'], 'sync_deletions_pull');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_deletions');
        Schema::dropIfExists('activities');
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('plan_prices');
    }
};
