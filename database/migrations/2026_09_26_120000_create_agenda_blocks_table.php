<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agenda_blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained();
            $table->foreignId('user_id')->constrained();
            $table->foreignId('created_by')->constrained('users');
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('note', 120)->nullable();
            $table->uuid('sync_uuid');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agenda_blocks');
    }
};
