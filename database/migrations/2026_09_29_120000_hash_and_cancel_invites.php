<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invites', function (Blueprint $table) {
            $table->string('token', 64)->nullable()->change();
            $table->char('token_hash', 64)->nullable()->unique()->after('token');
            $table->dateTime('cancelled_at')->nullable()->after('accepted_at');
        });
    }

    public function down(): void
    {
        Schema::table('invites', function (Blueprint $table) {
            $table->dropUnique(['token_hash']);
            $table->dropColumn(['token_hash', 'cancelled_at']);
        });
    }
};
