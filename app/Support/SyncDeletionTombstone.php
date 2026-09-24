<?php

namespace App\Support;

use App\Models\SyncDeletion;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Ponto único que grava a lápide de um registro apagado.
 *
 * Copiado do app/Support/SyncDeletionTombstone.php da Samaúma, inclusive o
 * guard de Schema::hasTable e o catch largo: apagar um registro nunca pode
 * virar 500 porque o tombstone falhou.
 *
 * Os entity_type abaixo cobrem a fatia 1. As fatias seguintes acrescentam
 * client, visit, visit_goal, check_event, visit_photo, invoice, invoice_line
 * e payout, cada um pelo model que usa o trait RecordsSyncDeletions.
 */
class SyncDeletionTombstone
{
    public const ENTITY_AGENCY = 'agency';

    public const ENTITY_USER = 'user';

    public const ENTITY_MEMBERSHIP = 'membership';

    /**
     * @param  list<int>  $affectedUserIds
     */
    public static function record(
        string $entityType,
        ?string $syncUuid,
        int|string|null $serverId = null,
        ?int $agencyId = null,
        array $affectedUserIds = [],
    ): void {
        $normalizedUuid = trim((string) $syncUuid);
        if ($normalizedUuid === '') {
            return;
        }

        if (! Schema::hasTable('sync_deletions')) {
            return;
        }

        try {
            SyncDeletion::query()->create([
                'entity_type' => $entityType,
                'sync_uuid' => $normalizedUuid,
                'server_id' => $serverId !== null ? (int) $serverId : null,
                'agency_id' => $agencyId,
                'affected_user_ids' => array_values(array_unique(array_map('intval', $affectedUserIds))),
                'deleted_at' => Carbon::now(),
            ]);
        } catch (\Throwable $exception) {
            Log::warning('sync.tombstone.record_failed', [
                'entity_type' => $entityType,
                'sync_uuid' => $normalizedUuid,
                'server_id' => $serverId,
                'message' => $exception->getMessage(),
            ]);
        }
    }
}
