<?php

namespace App\Mail;

use App\Models\Payout;

class PayoutPaidMail extends NoticeMail
{
    public function __construct(public Payout $payout) {}

    protected function subjectLine(): string
    {
        $this->payout->loadMissing('agency');

        return __('mail.payout.subject', ['agency' => $this->payout->agency->name]);
    }

    protected function notice(): array
    {
        $this->payout->loadMissing(['agency', 'visit.client']);
        $payout = $this->payout;

        return [
            'heading' => __('mail.payout.heading'),
            'lines' => [__('mail.payout.body', ['agency' => $payout->agency->name])],
            'details' => [
                __('mail.payout.amount') => self::money((int) $payout->amount_pence, $payout->agency->currency),
                __('mail.common.date') => self::date($payout->visit?->service_date),
                __('mail.common.address') => $payout->visit?->client?->address,
            ],
            'button' => ['label' => __('mail.payout.button'), 'url' => self::app('/billing')],
        ];
    }
}
