<?php

namespace App\Actions\Visits;

use App\Enums\MembershipRole;
use App\Mail\VisitCancelledMail;
use App\Models\SyncDeletion;
use App\Models\Visit;
use App\Policies\VisitPolicy;
use App\Support\AgencyContext;
use App\Support\ApiException;
use App\Support\ErrorCodes;
use App\Support\MailNotifier;
use App\Support\RecordActivity;

class CancelVisit
{
    public function __invoke(Visit $visit): void
    {
        $actor = AgencyContext::user();

        if (! app(VisitPolicy::class)->delete($actor, $visit)) {
            throw new ApiException(ErrorCodes::VISIT_FORBIDDEN, 403);
        }

        $visit->load(['invoiceLine', 'payout']);

        if ($visit->invoiceLine !== null) {
            throw new ApiException(ErrorCodes::VISIT_INVOICED, 409);
        }

        if ($visit->payout?->paid === true) {
            throw new ApiException(ErrorCodes::VISIT_PAID, 409);
        }

        $visit->payout?->delete();

        SyncDeletion::query()->create([
            'agency_id' => $visit->agency_id,
            'table_name' => 'visits',
            'sync_uuid' => $visit->sync_uuid,
        ]);

        $visit->loadMissing(['agency', 'client', 'assignee.membership']);
        $assignee = $visit->assignee;

        $visit->delete();

        RecordActivity::add($visit->agency_id, $actor->id, 'visit.cancelled');

        if ($assignee !== null && $assignee->id !== $actor->id && $assignee->membership?->role === MembershipRole::Invited) {
            app(MailNotifier::class)->toUser('visit.cancelled', $assignee, new VisitCancelledMail(
                agencyName: $visit->agency->name,
                date: $visit->service_date?->toDateString(),
                time: $visit->service_time,
                address: $visit->client?->address,
            ), $visit->agency_id, $actor->id);
        }
    }
}
