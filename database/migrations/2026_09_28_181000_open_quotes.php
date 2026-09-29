<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('booking_requests', function (Blueprint $table) {
            $table->dropForeign(['booking_service_id']);
        });

        Schema::table('booking_requests', function (Blueprint $table) {
            $table->unsignedBigInteger('booking_service_id')->nullable()->change();
            $table->date('requested_date')->nullable()->change();
            $table->time('requested_time')->nullable()->change();
            $table->string('kind', 16)->default('slot');
            $table->string('address')->nullable();
            $table->text('description')->nullable();
            $table->string('photo_path')->nullable();
            $table->unsignedInteger('quote_pence')->nullable();
            $table->string('quote_note', 500)->nullable();
            $table->date('proposed_date')->nullable();
            $table->time('proposed_time')->nullable();
            $table->string('public_token', 64)->nullable();
            $table->foreign('booking_service_id')->references('id')->on('booking_services')->nullOnDelete();
        });

        foreach (DB::table('booking_requests')->whereNull('public_token')->pluck('id') as $id) {
            DB::table('booking_requests')->where('id', $id)->update([
                'public_token' => Str::random(40),
            ]);
        }

        Schema::table('booking_requests', function (Blueprint $table) {
            $table->unique('public_token');
        });

        Schema::table('clients', function (Blueprint $table) {
            $table->decimal('lat', 10, 7)->nullable()->change();
            $table->decimal('lng', 10, 7)->nullable()->change();
            $table->text('note')->nullable();
        });

        Schema::table('visits', function (Blueprint $table) {
            $table->decimal('lat', 10, 7)->nullable()->change();
            $table->decimal('lng', 10, 7)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('visits', function (Blueprint $table) {
            $table->decimal('lat', 10, 7)->nullable(false)->change();
            $table->decimal('lng', 10, 7)->nullable(false)->change();
        });

        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn('note');
            $table->decimal('lat', 10, 7)->nullable(false)->change();
            $table->decimal('lng', 10, 7)->nullable(false)->change();
        });

        Schema::table('booking_requests', function (Blueprint $table) {
            $table->dropUnique(['public_token']);
            $table->dropForeign(['booking_service_id']);
            $table->dropColumn([
                'kind',
                'address',
                'description',
                'photo_path',
                'quote_pence',
                'quote_note',
                'proposed_date',
                'proposed_time',
                'public_token',
            ]);
            $table->unsignedBigInteger('booking_service_id')->nullable(false)->change();
            $table->foreign('booking_service_id')->references('id')->on('booking_services')->cascadeOnDelete();
        });
    }
};
