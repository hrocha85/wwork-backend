<?php

namespace App\Actions\Visits;

use App\Actions\Agenda\AssertOwnerAvailable;
use App\Actions\OneSignal\PushNotificationService;
use App\Enums\MembershipRole;
use App\Enums\VisitStatus;
use App\Mail\VisitOfferedMail;
use App\Models\Client;
use App\Models\Membership;
use App\Models\Visit;
use App\Models\VisitGoal;
use App\Policies\VisitPolicy;
use App\Support\AgencyContext;
use App\Support\ApiException;
use App\Support\ErrorCodes;
use App\Support\MailNotifier;
use App\Support\RecordActivity;
use Illuminate\Support\Facades\DB;

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

        $recurring = (bool) ($input['is_recurring'] ?? false);

        if ($recurring && array_filter(
            is_array($input['recurring_days'] ?? null) ? $input['recurring_days'] : [],
            fn ($day) => is_string($day) && in_array($day, ExpandRecurrence::DAYS, true),
        ) === []) {
            throw new ApiException(ErrorCodes::VISIT_RECURRING_DAYS_REQUIRED, 422);
        }

        if (filled($input['estimated_end_time'] ?? null)
            && (string) $input['estimated_end_time'] <= (string) $input['time']) {
            throw new ApiException(ErrorCodes::VISIT_INVALID_END_TIME, 422);
        }

        if ($assignee->role === MembershipRole::Owner) {
            app(AssertOwnerAvailable::class)(
                $membership->agency_id,
                $membership->agency->timezone,
                (string) $input['date'],
                (string) $input['time'],
                $assignee->user_id,
            );
        }

        $offered = $assignee->role === MembershipRole::Invited;
        $rate = $offered ? (int) $assignee->rate : null;

        $visit = DB::transaction(function () use ($input, $membership, $client, $assignee, $recurring, $rate, $offered, $goals) {
            $visit = Visit::query()->create([
                'agency_id' => $membership->agency_id,
                'client_id' => $client->id,
                'assignee_id' => $assignee->user_id,
                'service_date' => $input['date'],
                'service_time' => $input['time'],
                'estimated_end_time' => $input['estimated_end_time'] ?? null,
                'is_recurring' => $recurring,
                'recurring_days' => $recurring ? array_values($input['recurring_days']) : null,
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

            if ($recurring) {
                app(ExpandRecurrence::class)($visit->fresh(['goals', 'agency']), $input);
            }

            return $visit;
        });

        RecordActivity::add($membership->agency_id, $actor->id, 'visit.created');

        if ($offered) {
            $assignee->loadMissing('user');
            app(MailNotifier::class)->toUser('visit.offered', $assignee->user, new VisitOfferedMail($visit), $membership->agency_id, $actor->id);
        }

        app(PushNotificationService::class)->created($visit);

        return $visit->fresh(['assignee']);
    }
}
