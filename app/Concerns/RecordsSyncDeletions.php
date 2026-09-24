<?php

namespace App\Concerns;

use App\Support\SyncDeletionTombstone;

/**
 * Grava o tombstone quando a linha é apagada.
 *
 * Sem isso, o app nativo que já baixou a linha nunca fica sabendo que ela
 * sumiu: o pull incremental só enxerga o que tem updated_at novo. É o mesmo
 * papel do SyncDeletionTombstone da Samaúma, só que preso ao evento do model
 * em vez de ser chamado à mão em cada controller.
 *
 * Quem usa este trait precisa definir syncEntityType() e deve sobrescrever
 * syncAgencyId() e syncAffectedUserIds() quando houver recorte a guardar.
 */
trait RecordsSyncDeletions
{
    public static function bootRecordsSyncDeletions(): void
    {
        static::deleted(function (self $model): void {
            SyncDeletionTombstone::record(
                $model->syncEntityType(),
                $model->sync_uuid,
                $model->getKey(),
                $model->syncAgencyId(),
                $model->syncAffectedUserIds(),
            );
        });
    }

    abstract public function syncEntityType(): string;

    public function syncAgencyId(): ?int
    {
        return null;
    }

    /** @return list<int> */
    public function syncAffectedUserIds(): array
    {
        return [];
    }
}
