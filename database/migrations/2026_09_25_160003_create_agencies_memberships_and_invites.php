<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agencies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('timezone');
            $table->char('currency', 3);
            $table->char('country', 2);
            $table->char('invoice_region', 2);
            $table->string('trade', 32);
            $table->string('utm_source')->nullable();
            $table->string('utm_campaign')->nullable();
            $table->text('legal_address')->nullable();
            $table->boolean('vat_registered')->default(false);
            $table->string('tax_id')->nullable();
            $table->timestamps();
            $table->index(['country', 'trade']);
        });

        Schema::create('memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained();
            $table->foreignId('user_id')->constrained();
            $table->string('role', 16);
            $table->unsignedTinyInteger('rate')->nullable();
            $table->uuid('sync_uuid')->unique();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['agency_id', 'user_id']);
        });

        Schema::create('invites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained();
            $table->foreignId('invited_by')->constrained('users');
            $table->string('email');
            $table->string('token', 64)->unique();
            $table->unsignedTinyInteger('rate');
            $table->dateTime('expires_at');
            $table->dateTime('sent_at');
            $table->dateTime('accepted_at')->nullable();
            $table->timestamps();
            $table->index(['agency_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invites');
        Schema::dropIfExists('memberships');
        Schema::dropIfExists('agencies');
    }
};
