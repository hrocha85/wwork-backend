<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained();
            $table->foreignId('created_by')->constrained('users');
            $table->string('name');
            $table->string('whatsapp', 32);
            $table->text('address');
            $table->decimal('lat', 10, 7);
            $table->decimal('lng', 10, 7);
            $table->string('onesignal_player_id')->nullable();
            $table->string('agenda_token', 64)->nullable()->unique();
            $table->uuid('sync_uuid')->unique();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['agency_id', 'updated_at']);
        });

        Schema::create('visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained();
            $table->foreignId('client_id')->constrained();
            $table->foreignId('assignee_id')->constrained('users');
            $table->date('service_date');
            $table->time('service_time');
            $table->text('description')->nullable();
            $table->unsignedInteger('price_pence');
            $table->unsignedInteger('partner_earning_pence')->nullable();
            $table->unsignedTinyInteger('rate')->nullable();
            $table->decimal('lat', 10, 7);
            $table->decimal('lng', 10, 7);
            $table->string('status', 32);
            $table->dateTime('check_in_at')->nullable();
            $table->uuid('sync_uuid')->unique();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['agency_id', 'service_date']);
            $table->index(['assignee_id', 'service_date']);
            $table->index('updated_at');
        });

        Schema::create('visit_goals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visit_id')->constrained();
            $table->string('text');
            $table->boolean('completed')->nullable();
            $table->uuid('sync_uuid')->unique();
            $table->timestamps();
            $table->softDeletes();
            $table->index('updated_at');
        });

        Schema::create('check_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visit_id')->constrained();
            $table->foreignId('user_id')->constrained();
            $table->string('type', 32);
            $table->decimal('lat', 10, 7);
            $table->decimal('lng', 10, 7);
            $table->dateTime('occurred_at');
            $table->uuid('sync_uuid')->unique();
            $table->timestamps();
            $table->softDeletes();
            $table->index('updated_at');
        });

        Schema::create('visit_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visit_id')->constrained();
            $table->string('path');
            $table->uuid('sync_uuid')->unique();
            $table->timestamps();
            $table->softDeletes();
            $table->index('updated_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visit_photos');
        Schema::dropIfExists('check_events');
        Schema::dropIfExists('visit_goals');
        Schema::dropIfExists('visits');
        Schema::dropIfExists('clients');
    }
};
