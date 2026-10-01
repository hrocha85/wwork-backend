<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('visits', function (Blueprint $table) {
            $table->time('estimated_end_time')->nullable()->after('service_time');
            $table->boolean('is_recurring')->default(false)->after('estimated_end_time');
            $table->json('recurring_days')->nullable()->after('is_recurring');
            $table->unsignedBigInteger('parent_visit_id')->nullable()->after('recurring_days');
            $table->timestamp('reminded_at')->nullable()->after('check_in_at');
            $table->index('parent_visit_id');
        });

        Schema::table('booking_requests', function (Blueprint $table) {
            $table->time('estimated_end_time')->nullable()->after('requested_time');
            $table->boolean('is_recurring')->default(false)->after('estimated_end_time');
            $table->json('recurring_days')->nullable()->after('is_recurring');
        });
    }

    public function down(): void
    {
        Schema::table('visits', function (Blueprint $table) {
            $table->dropIndex(['parent_visit_id']);
            $table->dropColumn([
                'estimated_end_time',
                'is_recurring',
                'recurring_days',
                'parent_visit_id',
                'reminded_at',
            ]);
        });

        Schema::table('booking_requests', function (Blueprint $table) {
            $table->dropColumn([
                'estimated_end_time',
                'is_recurring',
                'recurring_days',
            ]);
        });
    }
};
