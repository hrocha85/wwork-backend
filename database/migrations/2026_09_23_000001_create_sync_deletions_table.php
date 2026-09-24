<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tombstones, no formato do sync_deletions da Samaúma.
 *
 * Apagar não pode sumir no silêncio: o pull incremental do app nativo só
 * enxerga updated_at novo, então a exclusão precisa deixar rastro próprio.
 *
 * A única coluna que a Samaúma não tem é agency_id. Lá o recorte do pull é por
 * projeto e sai do JSON de affected_user_ids; aqui o recorte é a agência, e o
 * id direto evita varrer JSON a cada pull.
 *
 * Sem chave estrangeira de propósito: a lápide precisa sobreviver ao sumiço
 * definitivo da linha que ela descreve.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sync_deletions', function (Blueprint $table) {
            $table->id();
            $table->string('entity_type', 64);
            $table->string('sync_uuid', 64);
            $table->unsignedBigInteger('server_id')->nullable();
            $table->unsignedBigInteger('agency_id')->nullable();
            $table->json('affected_user_ids')->nullable();
            $table->timestamp('deleted_at')->useCurrent();

            $table->index(['entity_type', 'deleted_at']);
            $table->index(['agency_id', 'deleted_at']);
            $table->index('sync_uuid');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_deletions');
    }
};
