<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('first_access_at')->nullable()->after('terms_accepted_at');
        });

        Schema::table('agencies', function (Blueprint $table) {
            $table->string('trade_detail', 80)->nullable()->after('trade');
        });

        DB::table('users')->whereNull('first_access_at')->update(['first_access_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('first_access_at');
        });

        Schema::table('agencies', function (Blueprint $table) {
            $table->dropColumn('trade_detail');
        });
    }
};
