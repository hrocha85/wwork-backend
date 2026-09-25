<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained();
            $table->foreignId('client_id')->constrained();
            $table->foreignId('created_by')->constrained('users');
            $table->unsignedInteger('number');
            $table->string('status', 32);
            $table->unsignedInteger('total_pence');
            $table->string('locale', 5);
            $table->char('invoice_region', 2);
            $table->string('pdf_path')->nullable();
            $table->dateTime('sent_at')->nullable();
            $table->dateTime('paid_at')->nullable();
            $table->uuid('sync_uuid')->unique();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['agency_id', 'number']);
            $table->index(['agency_id', 'updated_at']);
        });

        Schema::create('invoice_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('visit_id')->unique()->constrained();
            $table->date('service_date');
            $table->text('description');
            $table->unsignedInteger('price_pence');
            $table->timestamps();
        });

        Schema::create('payouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained();
            $table->foreignId('visit_id')->unique()->constrained();
            $table->foreignId('user_id')->constrained();
            $table->unsignedInteger('amount_pence');
            $table->boolean('paid')->default(false);
            $table->dateTime('paid_at')->nullable();
            $table->uuid('sync_uuid')->unique();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['agency_id', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payouts');
        Schema::dropIfExists('invoice_lines');
        Schema::dropIfExists('invoices');
    }
};
