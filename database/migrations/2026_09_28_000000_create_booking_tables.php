<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agencies', function (Blueprint $table) {
            $table->string('booking_token', 64)->nullable()->unique()->after('tax_id');
        });

        Schema::create('booking_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->unsignedSmallInteger('duration_minutes');
            $table->unsignedInteger('price_pence');
            $table->timestamps();
        });

        Schema::create('booking_hours', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $table->string('weekday', 3);
            $table->time('starts_at');
            $table->time('ends_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_hours');
        Schema::dropIfExists('booking_services');
        Schema::table('agencies', function (Blueprint $table) {
            $table->dropUnique(['booking_token']);
            $table->dropColumn('booking_token');
        });
    }
};
