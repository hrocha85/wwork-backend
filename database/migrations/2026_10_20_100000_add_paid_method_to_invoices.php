<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('invoices', 'paid_note')) {
            Schema::table('invoices', function (Blueprint $table) {
                if (Schema::hasColumn('invoices', 'paid_at')) {
                    $table->string('paid_note', 255)->nullable()->after('paid_at');
                } else {
                    $table->string('paid_note', 255)->nullable();
                }
            });
        }

        if (! Schema::hasColumn('invoices', 'pay_by')) {
            Schema::table('invoices', function (Blueprint $table) {
                if (Schema::hasColumn('invoices', 'paid_note')) {
                    $table->string('pay_by', 16)->nullable()->after('paid_note');
                } else {
                    $table->string('pay_by', 16)->nullable();
                }
            });
        }

        if (! Schema::hasColumn('invoices', 'pay_link')) {
            Schema::table('invoices', function (Blueprint $table) {
                if (Schema::hasColumn('invoices', 'pay_by')) {
                    $table->string('pay_link')->nullable()->after('pay_by');
                } else {
                    $table->string('pay_link')->nullable();
                }
            });
        }

        Schema::table('invoices', function (Blueprint $table) {
            if (! Schema::hasColumn('invoices', 'paid_method')) {
                if (Schema::hasColumn('invoices', 'paid_note')) {
                    $table->string('paid_method', 16)->nullable()->after('paid_note');
                } else {
                    $table->string('paid_method', 16)->nullable();
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            if (Schema::hasColumn('invoices', 'paid_method')) {
                $table->dropColumn('paid_method');
            }
        });
    }
};
