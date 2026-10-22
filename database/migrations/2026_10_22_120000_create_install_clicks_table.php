<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('install_clicks', function (Blueprint $table) {
            $table->id();
            $table->string('platform', 32);
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('agency_id')->nullable()->constrained('agencies')->nullOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('clicked_at')->index();
            $table->timestamps();

            $table->index(['platform', 'clicked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('install_clicks');
    }
};
