<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agencies', function (Blueprint $table) {
            $table->id();
            $table->uuid('sync_uuid')->unique();
            $table->string('name');
            $table->string('timezone', 64)->default('Europe/London');
            $table->char('currency', 3)->default('GBP');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agencies');
    }
};
