<?php

namespace App\Actions\Billing;

use App\Enums\MembershipRole;
use App\Models\Payout;
use App\Support\AgencyContext;
use App\Support\ApiException;
use App\Support\ErrorCodes;
use App\Support\RecordActivity;

class MarkPayoutPaid
{
    public function __invoke(Payout $payout): Payout
    {
        $actor = AgencyContext::user();
        $membership = AgencyContext::membership();

        if ($membership->role !== MembershipRole::Owner) {
            throw new ApiException(ErrorCodes::INVOICE_FORBIDDEN, 403);
        }

        if ($payout->agency_id !== $membership->agency_id) {
            throw new ApiException(ErrorCodes::NOT_FOUND, 404);
        }

        $payout->paid = true;
        $payout->paid_at = now();
        $payout->save();

        RecordActivity::add($payout->agency_id, $actor->id, 'payout.paid');

        return $payout;
    }
}
