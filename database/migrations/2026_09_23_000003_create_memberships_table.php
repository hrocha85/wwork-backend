<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('memberships', function (Blueprint $table) {
            $table->id();
            $table->uuid('sync_uuid')->unique();
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // owner ou invited dentro daquela agência. Ver App\Enums\MembershipRole.
            $table->string('role', 16);
            // Porcentagem inteira de 0 a 100, só do convidado. A conta da
            // comissão é fatia 5; o campo nasce agora para o schema não mudar.
            $table->unsignedTinyInteger('commission_rate')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // deleted_at entra na chave para que a mesma pessoa só tenha um
            // vínculo vivo na agência e ainda possa ser reconvidada depois.
            $table->unique(['agency_id', 'user_id', 'deleted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('memberships');
    }
};
