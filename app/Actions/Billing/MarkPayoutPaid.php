<?php

namespace App\Actions\Billing;

use App\Enums\MembershipRole;
use App\Mail\PayoutPaidMail;
use App\Models\Payout;
use App\Support\AgencyContext;
use App\Support\ApiException;
use App\Support\ErrorCodes;
use App\Support\MailNotifier;
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

        $wasPaid = (bool) $payout->paid;

        $payout->paid = true;
        $payout->paid_at = now();
        $payout->save();

        RecordActivity::add($payout->agency_id, $actor->id, 'payout.paid');

        $payout->loadMissing('user');
        if (! $wasPaid && $payout->user !== null && $payout->user_id !== $actor->id) {
            app(MailNotifier::class)->toUser('payout.paid', $payout->user, new PayoutPaidMail($payout), $payout->agency_id, $actor->id);
        }

        return $payout;
    }
}
