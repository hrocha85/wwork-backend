<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('locale', 5)->default('en')->after('password');
            $table->timestamp('last_seen_at')->nullable()->after('locale');
            $table->boolean('must_change_password')->default(false)->after('last_seen_at');
            $table->string('onesignal_player_id')->nullable()->after('must_change_password');
            $table->timestamp('terms_accepted_at')->nullable()->after('onesignal_player_id');
            $table->decimal('last_lat', 10, 7)->nullable()->after('terms_accepted_at');
            $table->decimal('last_lng', 10, 7)->nullable()->after('last_lat');
            $table->timestamp('last_located_at')->nullable()->after('last_lng');
            $table->foreignId('staff_profile_id')->nullable()->after('last_located_at')->constrained('staff_profiles')->nullOnDelete();
            $table->index('last_seen_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['last_seen_at']);
            $table->dropConstrainedForeignId('staff_profile_id');
            $table->dropColumn([
                'locale',
                'last_seen_at',
                'must_change_password',
                'onesignal_player_id',
                'terms_accepted_at',
                'last_lat',
                'last_lng',
                'last_located_at',
            ]);
        });
    }
};
