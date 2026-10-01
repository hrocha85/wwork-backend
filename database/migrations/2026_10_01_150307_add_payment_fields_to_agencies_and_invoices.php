<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agencies', function (Blueprint $table) {
            $table->string('payment_link')->nullable()->after('payment_details');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->string('pay_by', 16)->nullable()->after('paid_note');
            $table->string('pay_link')->nullable()->after('pay_by');
        });
    }

    public function down(): void
    {
        Schema::table('agencies', function (Blueprint $table) {
            $table->dropColumn('payment_link');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['pay_by', 'pay_link']);
        });
    }
};