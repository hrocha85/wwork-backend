<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('visit_goals')) {
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
        }

        if (! Schema::hasTable('check_events')) {
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
        }

        if (! Schema::hasTable('visit_photos')) {
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
    }

    public function down(): void
    {
        Schema::dropIfExists('visit_photos');
        Schema::dropIfExists('check_events');
        Schema::dropIfExists('visit_goals');
    }
};
