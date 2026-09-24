<?php

namespace App\Concerns;

use Illuminate\Support\Str;

/**
 * Gera o sync_uuid na criação.
 *
 * Na Samaúma o sync_uuid entrou depois, por migration de alinhamento, e o
 * preenchimento ficou remendado em tempo de execução pelo SyncController. Aqui
 * a coluna nasce com a tabela e o valor sai deste evento, então nenhuma linha
 * chega ao banco sem uuid.
 *
 * O valor que vier de fora é respeitado: quando o app nativo mandar o uuid que
 * gerou no aparelho, o mesmo envio repetido não cria uma segunda linha.
 */
trait HasSyncUuid
{
    public static function bootHasSyncUuid(): void
    {
        static::creating(function (self $model): void {
            if (blank($model->sync_uuid)) {
                $model->sync_uuid = (string) Str::uuid();
            }
        });
    }
}
