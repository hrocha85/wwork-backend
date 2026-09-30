<?php

namespace App\Mail;

use App\Models\Visit;

class VisitOfferedMail extends NoticeMail
{
    public function __construct(public Visit $visit) {}

    protected function subjectLine(): string
    {
        $this->visit->loadMissing('agency');

        return __('mail.visit.offered_subject', [
            'agency' => $this->visit->agency->name,
            'date' => self::date($this->visit->service_date),
        ]);
    }

    protected function notice(): array
    {
        $this->visit->loadMissing(['agency', 'client']);
        $visit = $this->visit;

        return [
            'heading' => __('mail.visit.offered_heading'),
            'lines' => [__('mail.visit.offered_body', ['agency' => $visit->agency->name])],
            'details' => [
                __('mail.common.date') => self::date($visit->service_date),
                __('mail.common.time') => self::time($visit->service_time),
                __('mail.common.address') => $visit->client?->address,
                __('mail.common.description') => $visit->description,
                __('mail.visit.earning') => $visit->partner_earning_pence === null ? null : self::money((int) $visit->partner_earning_pence, $visit->agency->currency),
            ],
            'button' => ['label' => __('mail.visit.offered_button'), 'url' => self::app('/calendar')],
        ];
    }
}
