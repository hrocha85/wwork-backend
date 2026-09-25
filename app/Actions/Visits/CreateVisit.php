<?php

namespace App\Actions\Visits;

use App\Enums\MembershipRole;
use App\Enums\VisitStatus;
use App\Models\Client;
use App\Models\Membership;
use App\Models\Visit;
use App\Models\VisitGoal;
use App\Policies\VisitPolicy;
use App\Support\AgencyContext;
use App\Support\ApiException;
use App\Support\ErrorCodes;
use App\Support\RecordActivity;

class CreateVisit
{
    /**
     * @param  array<string, mixed>  $input
     */
    public function __invoke(array $input): Visit
    {
        $actor = AgencyContext::user();
        $membership = AgencyContext::membership();

        if (! app(VisitPolicy::class)->create($actor)) {
            throw new ApiException(ErrorCodes::VISIT_FORBIDDEN, 403);
        }

        if (! is_numeric($input['assignee_id'] ?? null)) {
            throw new ApiException(ErrorCodes::VISIT_MISSING_ASSIGNEE, 422);
        }

        if (! is_numeric($input['lat'] ?? null) || ! is_numeric($input['lng'] ?? null)) {
            throw new ApiException(ErrorCodes::VISIT_MISSING_POINT, 422);
        }

        $client = Client::query()
            ->where('agency_id', $membership->agency_id)
            ->find($input['client_id'] ?? 0);

        if ($client === null) {
            throw new ApiException(ErrorCodes::VISIT_FORBIDDEN, 403);
        }

        $assignee = Membership::query()
            ->where('agency_id', $membership->agency_id)
            ->where('user_id', (int) $input['assignee_id'])
            ->first();

        if ($assignee === null) {
            throw new ApiException(ErrorCodes::VISIT_INVALID_ASSIGNEE, 422);
        }

        $invited = Membership::query()
            ->where('agency_id', $membership->agency_id)
            ->where('role', MembershipRole::Invited)
            ->count();

        $goals = is_array($input['goals'] ?? null) ? $input['goals'] : [];

        if ($invited > 1 && $goals === []) {
            throw new ApiException(ErrorCodes::VISIT_GOALS_REQUIRED, 422);
        }

        $offered = $assignee->role === MembershipRole::Invited;
        $rate = $offered ? (int) $assignee->rate : null;

        $visit = Visit::query()->create([
            'agency_id' => $membership->agency_id,
            'client_id' => $client->id,
            'assignee_id' => $assignee->user_id,
            'service_date' => $input['date'],
            'service_time' => $input['time'],
            'description' => $input['description'] ?? null,
            'price_pence' => $input['price_pence'],
            'partner_earning_pence' => $rate === null ? null : ChangeAssignee::earning((int) $input['price_pence'], $rate),
            'rate' => $rate,
            'lat' => $input['lat'],
            'lng' => $input['lng'],
            'status' => $offered ? VisitStatus::Offered : VisitStatus::Todo,
        ]);

        foreach ($goals as $goal) {
            VisitGoal::query()->create([
                'visit_id' => $visit->id,
                'text' => $goal['text'],
                'completed' => null,
            ]);
        }

        RecordActivity::add($membership->agency_id, $actor->id, 'visit.created');

        return $visit->fresh(['assignee']);
    }
}
