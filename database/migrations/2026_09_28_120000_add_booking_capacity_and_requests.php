<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('booking_hours', function (Blueprint $table) {
            $table->unsignedTinyInteger('concurrent_slots')->default(1)->after('ends_at');
        });

        Schema::create('booking_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $table->foreignId('booking_service_id')->constrained()->cascadeOnDelete();
            $table->string('client_name', 80);
            $table->string('client_phone', 32);
            $table->date('requested_date');
            $table->time('requested_time');
            $table->string('status', 16);
            $table->timestamps();
            $table->index(['agency_id', 'requested_date', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_requests');
        Schema::table('booking_hours', function (Blueprint $table) {
            $table->dropColumn('concurrent_slots');
        });
    }
};
