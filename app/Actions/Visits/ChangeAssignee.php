<?php

namespace App\Actions\Visits;

use App\Enums\MembershipRole;
use App\Enums\VisitStatus;
use App\Mail\VisitOfferedMail;
use App\Models\Membership;
use App\Models\Visit;
use App\Support\ApiException;
use App\Support\ErrorCodes;
use App\Support\MailNotifier;

class ChangeAssignee
{
    public function __invoke(Visit $visit, mixed $assigneeId): Visit
    {
        if (! is_numeric($assigneeId)) {
            throw new ApiException(ErrorCodes::VISIT_MISSING_ASSIGNEE, 422);
        }

        $membership = Membership::query()
            ->where('agency_id', $visit->agency_id)
            ->where('user_id', (int) $assigneeId)
            ->first();

        if ($membership === null) {
            throw new ApiException(ErrorCodes::VISIT_INVALID_ASSIGNEE, 422);
        }

        $previous = $visit->assignee_id;
        $visit->assignee_id = $membership->user_id;

        if ($membership->role === MembershipRole::Owner) {
            $visit->status = VisitStatus::Todo;
            $visit->rate = null;
            $visit->partner_earning_pence = null;
        } else {
            $rate = (int) $membership->rate;
            $visit->status = VisitStatus::Offered;
            $visit->rate = $rate;
            $visit->partner_earning_pence = self::earning((int) $visit->price_pence, $rate);
        }

        $visit->save();

        if ($previous !== $membership->user_id && $membership->role === MembershipRole::Invited) {
            $membership->loadMissing('user');
            app(MailNotifier::class)->toUser('visit.offered', $membership->user, new VisitOfferedMail($visit), $visit->agency_id);
        }

        return $visit;
    }

    public static function earning(int $pricePence, int $rate): int
    {
        return (int) round($pricePence * $rate / 100);
    }
}
