<?php

namespace App\Actions\Visits;

use App\Enums\VisitStatus;
use App\Models\Visit;
use App\Policies\VisitPolicy;
use App\Support\AgencyContext;
use App\Support\ApiException;
use App\Support\ErrorCodes;
use App\Support\RecordActivity;

class UpdateVisit
{
    /**
     * @param  array<string, mixed>  $input
     */
    public function __invoke(Visit $visit, array $input): Visit
    {
        $actor = AgencyContext::user();

        if (! app(VisitPolicy::class)->update($actor, $visit)) {
            throw new ApiException(ErrorCodes::VISIT_FORBIDDEN, 403);
        }

        if ($visit->status === VisitStatus::Done) {
            throw new ApiException(ErrorCodes::VISIT_ALREADY_DONE, 409);
        }

        if (array_key_exists('date', $input)) {
            $visit->service_date = $input['date'];
        }

        if (array_key_exists('time', $input)) {
            $visit->service_time = $input['time'];
        }

        if (array_key_exists('description', $input)) {
            $visit->description = $input['description'];
        }

        if (array_key_exists('price_pence', $input)) {
            $visit->price_pence = $input['price_pence'];

            if ($visit->rate !== null) {
                $visit->partner_earning_pence = ChangeAssignee::earning(
                    (int) $visit->price_pence,
                    (int) $visit->rate,
                );
            }
        }

        $visit->save();

        if (array_key_exists('assignee_id', $input)) {
            app(ChangeAssignee::class)($visit, $input['assignee_id']);
        }

        RecordActivity::add($visit->agency_id, $actor->id, 'visit.updated');

        return $visit->fresh(['assignee', 'client', 'goals', 'photos', 'events']);
    }
}
