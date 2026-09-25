<?php

namespace App\Actions\Visits;

use App\Enums\MembershipRole;
use App\Enums\VisitStatus;
use App\Models\Membership;
use App\Models\Visit;
use App\Support\AgencyContext;
use App\Support\ApiException;
use App\Support\ErrorCodes;
use App\Support\RecordActivity;

class DeclineVisit
{
    public function __invoke(Visit $visit): Visit
    {
        $actor = AgencyContext::user();
        $membership = AgencyContext::membership();

        if ($membership->agency_id !== $visit->agency_id) {
            throw new ApiException(ErrorCodes::VISIT_FORBIDDEN, 403);
        }

        if ($membership->role === MembershipRole::Owner) {
            throw new ApiException(ErrorCodes::VISIT_FORBIDDEN, 403);
        }

        if ($visit->assignee_id !== $actor->id) {
            throw new ApiException(ErrorCodes::VISIT_NOT_ASSIGNEE, 403);
        }

        if ($visit->status !== VisitStatus::Offered) {
            throw new ApiException(ErrorCodes::VISIT_NOT_OFFERED, 409);
        }

        $owner = Membership::query()
            ->where('agency_id', $visit->agency_id)
            ->where('role', MembershipRole::Owner)
            ->first();

        if ($owner === null) {
            throw new ApiException(ErrorCodes::VISIT_FORBIDDEN, 403);
        }

        $visit->assignee_id = $owner->user_id;
        $visit->status = VisitStatus::Todo;
        $visit->rate = null;
        $visit->partner_earning_pence = null;
        $visit->save();

        RecordActivity::add($visit->agency_id, $actor->id, 'visit.declined');

        return $visit;
    }
}
