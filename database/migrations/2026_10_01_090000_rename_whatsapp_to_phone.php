<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `clients.whatsapp` vira `phone` (o número nunca foi só WhatsApp) e ganha
     * `contact_channel`, que diz por onde o cliente quer ser falado. Todo dado
     * existente já veio do WhatsApp, então o default é `whatsapp`.
     */
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->renameColumn('whatsapp', 'phone');
        });

        Schema::table('clients', function (Blueprint $table) {
            $table->string('contact_channel', 16)->default('whatsapp')->after('phone');
        });

        Schema::table('booking_requests', function (Blueprint $table) {
            $table->string('client_contact_channel', 16)->default('whatsapp')->after('client_phone');
        });
    }

    public function down(): void
    {
        Schema::table('booking_requests', function (Blueprint $table) {
            $table->dropColumn('client_contact_channel');
        });

        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn('contact_channel');
        });

        Schema::table('clients', function (Blueprint $table) {
            $table->renameColumn('phone', 'whatsapp');
        });
    }
};
