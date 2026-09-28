<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
            $table->string('phone', 32)->nullable()->unique()->after('email');
        });

        Schema::table('agencies', function (Blueprint $table) {
            $table->text('bio')->nullable();
            $table->string('website')->nullable();
            $table->string('public_slug')->nullable()->unique();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->decimal('average_rating', 3, 2)->default(0);
        });

        Schema::table('clients', function (Blueprint $table) {
            $table->string('email')->nullable()->after('whatsapp');
            $table->foreignId('user_id')->nullable()->after('email')->constrained()->nullOnDelete();
            $table->string('avatar_path')->nullable();
            $table->string('join_token', 64)->nullable()->unique();
        });

        Schema::create('portfolio_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->string('caption')->nullable();
            $table->timestamps();
        });

        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('visit_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('rating');
            $table->text('comment')->nullable();
            $table->timestamps();
            $table->unique('visit_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
        Schema::dropIfExists('portfolio_photos');

        Schema::table('clients', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
            $table->dropUnique(['join_token']);
            $table->dropColumn(['email', 'avatar_path', 'join_token']);
        });

        Schema::table('agencies', function (Blueprint $table) {
            $table->dropUnique(['public_slug']);
            $table->dropColumn(['bio', 'website', 'public_slug', 'latitude', 'longitude', 'average_rating']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['phone']);
            $table->dropColumn('phone');
            $table->string('email')->nullable(false)->change();
        });
    }
};
