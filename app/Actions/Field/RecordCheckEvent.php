<?php

namespace App\Actions\Field;

use App\Actions\OneSignal\NotifyJobFinished;
use App\Enums\CheckEventType;
use App\Enums\MembershipRole;
use App\Enums\VisitStatus;
use App\Models\CheckEvent;
use App\Models\Payout;
use App\Models\Visit;
use App\Support\AgencyContext;
use App\Support\ApiException;
use App\Support\ErrorCodes;
use App\Support\RecordActivity;
use Illuminate\Support\Carbon;

class RecordCheckEvent
{
    public function __invoke(Visit $visit, string $type, mixed $lat, mixed $lng): CheckEvent
    {
        $actor = AgencyContext::user();
        $membership = AgencyContext::membership();

        if ($membership->agency_id !== $visit->agency_id) {
            throw new ApiException(ErrorCodes::VISIT_FORBIDDEN, 403);
        }

        if ($visit->assignee_id !== $actor->id) {
            throw new ApiException(ErrorCodes::VISIT_NOT_ASSIGNEE, 403);
        }

        if ($membership->role === MembershipRole::Invited && $visit->status === VisitStatus::Offered) {
            throw new ApiException(ErrorCodes::VISIT_NOT_ACCEPTED, 409);
        }

        if (! is_numeric($lat) || ! is_numeric($lng)) {
            throw new ApiException(ErrorCodes::VISIT_MISSING_GPS, 422);
        }

        $eventType = CheckEventType::tryFrom($type);

        if ($eventType === null || ! $this->allowed($visit->status, $eventType)) {
            throw new ApiException(ErrorCodes::VISIT_INVALID_EVENT, 409);
        }

        $at = Carbon::now();
        $event = CheckEvent::query()->create([
            'visit_id' => $visit->id,
            'user_id' => $actor->id,
            'type' => $eventType,
            'lat' => $lat,
            'lng' => $lng,
            'occurred_at' => $at,
        ]);

        $duration = null;

        if ($eventType === CheckEventType::EnRoute) {
            $visit->status = VisitStatus::EnRoute;
        }

        if ($eventType === CheckEventType::CheckIn) {
            $visit->status = VisitStatus::CheckedIn;
            $visit->check_in_at = $at;
        }

        if ($eventType === CheckEventType::CheckOut) {
            $visit->status = VisitStatus::Done;
            $duration = $visit->check_in_at === null ? 0 : (int) $visit->check_in_at->diffInSeconds($at);
            if ($membership->role === MembershipRole::Invited) {
                Payout::query()->create([
                    'agency_id' => $visit->agency_id,
                    'visit_id' => $visit->id,
                    'user_id' => $actor->id,
                    'amount_pence' => (int) ($visit->partner_earning_pence ?? 0),
                    'paid' => false,
                ]);
            }
        }

        $visit->save();
        $actor->forceFill(['last_seen_at' => $at])->save();
        RecordActivity::add($visit->agency_id, $actor->id, 'visit.'.$eventType->value);

        if ($eventType === CheckEventType::CheckOut) {
            app(NotifyJobFinished::class)($visit, $duration);
        }

        $event->setAttribute('duration_seconds', $duration);

        return $event;
    }

    private function allowed(VisitStatus $status, CheckEventType $type): bool
    {
        return match ($type) {
            CheckEventType::EnRoute => $status === VisitStatus::Todo,
            CheckEventType::CheckIn => $status === VisitStatus::Todo || $status === VisitStatus::EnRoute,
            CheckEventType::CheckOut => $status === VisitStatus::CheckedIn,
        };
    }
}
