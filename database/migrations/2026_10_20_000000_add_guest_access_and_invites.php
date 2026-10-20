<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('visits', function (Blueprint $table) {
            $table->string('guest_token')->nullable()->index()->after('sync_uuid');
            $table->timestamp('guest_token_expires_at')->nullable()->after('guest_token');
        });

        Schema::table('clients', function (Blueprint $table) {
            $table->unsignedTinyInteger('profile_invite_stage')->default(0)->after('agenda_token');
            $table->timestamp('profile_invite_last_at')->nullable()->after('profile_invite_stage');
            $table->timestamp('profile_invite_declined_at')->nullable()->after('profile_invite_last_at');
        });

        Schema::create('client_access_codes', function (Blueprint $table) {
            $table->id();
            $table->string('email')->index();
            $table->string('code_hash');
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_access_codes');

        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn(['profile_invite_stage', 'profile_invite_last_at', 'profile_invite_declined_at']);
        });

        Schema::table('visits', function (Blueprint $table) {
            $table->dropColumn(['guest_token', 'guest_token_expires_at']);
        });
    }
};
