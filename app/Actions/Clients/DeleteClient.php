<?php

namespace App\Actions\Clients;

use App\Enums\VisitStatus;
use App\Models\Client;
use App\Models\SyncDeletion;
use App\Policies\ClientPolicy;
use App\Support\AgencyContext;
use App\Support\ApiException;
use App\Support\ErrorCodes;
use App\Support\RecordActivity;

class DeleteClient
{
    public function __invoke(Client $client): void
    {
        $actor = AgencyContext::user();

        if (! app(ClientPolicy::class)->delete($actor, $client)) {
            throw new ApiException(ErrorCodes::CLIENT_FORBIDDEN, 403);
        }

        $client->load('visits.invoiceLine');

        $active = $client->visits->contains(
            fn ($visit): bool => $visit->status !== VisitStatus::Done,
        );

        if ($active) {
            throw new ApiException(ErrorCodes::CLIENT_HAS_ACTIVE_VISITS, 409);
        }

        $invoiced = $client->visits->contains(
            fn ($visit): bool => $visit->invoiceLine !== null,
        );

        if ($invoiced) {
            throw new ApiException(ErrorCodes::CLIENT_HAS_INVOICES, 409);
        }

        SyncDeletion::query()->create([
            'agency_id' => $client->agency_id,
            'table_name' => 'clients',
            'sync_uuid' => $client->sync_uuid,
        ]);

        $client->delete();

        RecordActivity::add($client->agency_id, $actor->id, 'client.deleted');
    }
}
