<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agencies', function (Blueprint $table) {
            $table->string('phone', 20)->nullable()->after('legal_address');
            $table->string('payment_method', 32)->nullable()->after('phone');
            $table->string('payment_details', 300)->nullable()->after('payment_method');
            $table->string('logo_path')->nullable()->after('payment_details');
        });
    }

    public function down(): void
    {
        Schema::table('agencies', function (Blueprint $table) {
            $table->dropColumn(['phone', 'payment_method', 'payment_details', 'logo_path']);
        });
    }
};
