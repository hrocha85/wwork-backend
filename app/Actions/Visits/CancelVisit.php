<?php

namespace App\Actions\Visits;

use App\Enums\VisitStatus;
use App\Models\SyncDeletion;
use App\Models\Visit;
use App\Policies\VisitPolicy;
use App\Support\AgencyContext;
use App\Support\ApiException;
use App\Support\ErrorCodes;
use App\Support\RecordActivity;

class CancelVisit
{
    public function __invoke(Visit $visit): void
    {
        $actor = AgencyContext::user();

        if (! app(VisitPolicy::class)->delete($actor, $visit)) {
            throw new ApiException(ErrorCodes::VISIT_FORBIDDEN, 403);
        }

        if ($visit->status === VisitStatus::Done) {
            throw new ApiException(ErrorCodes::VISIT_ALREADY_DONE, 409);
        }

        $visit->load('invoiceLine');

        if ($visit->invoiceLine !== null) {
            throw new ApiException(ErrorCodes::VISIT_INVOICED, 409);
        }

        SyncDeletion::query()->create([
            'agency_id' => $visit->agency_id,
            'table_name' => 'visits',
            'sync_uuid' => $visit->sync_uuid,
        ]);

        $visit->delete();

        RecordActivity::add($visit->agency_id, $actor->id, 'visit.cancelled');
    }
}
