<?php

namespace App\Actions\Field;

use App\Models\Visit;
use App\Models\VisitGoal;
use App\Support\AgencyContext;
use App\Support\ApiException;
use App\Support\ErrorCodes;

class MarkGoals
{
    /**
     * @param  array<int, array{id: int, completed: bool}>  $goals
     * @return array<int, VisitGoal>
     */
    public function __invoke(Visit $visit, array $goals): array
    {
        $actor = AgencyContext::user();
        $membership = AgencyContext::membership();

        if ($membership->agency_id !== $visit->agency_id || $visit->assignee_id !== $actor->id) {
            throw new ApiException(ErrorCodes::VISIT_NOT_ASSIGNEE, 403);
        }

        $saved = [];

        foreach ($goals as $row) {
            $goal = VisitGoal::query()
                ->where('visit_id', $visit->id)
                ->find($row['id']);

            if ($goal === null) {
                throw new ApiException(ErrorCodes::VISIT_GOAL_NOT_FOUND, 404);
            }

            $goal->completed = (bool) $row['completed'];
            $goal->save();
            $saved[] = $goal;
        }

        return $saved;
    }
}
