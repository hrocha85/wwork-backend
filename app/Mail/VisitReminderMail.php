<?php

namespace App\Mail;

use App\Models\Visit;

/**
 * Lembrete ao cliente, no dia anterior, só para agência com assinatura paga.
 */
class VisitReminderMail extends NoticeMail
{
    public function __construct(public Visit $visit) {}

    protected function subjectLine(): string
    {
        return __('mail.visit.reminder_subject', [
            'agency' => $this->visit->agency?->name ?? '',
        ]);
    }

    protected function notice(): array
    {
        $this->visit->loadMissing(['client', 'agency']);

        return [
            'heading' => __('mail.visit.reminder_heading'),
            'lines' => [__('mail.visit.reminder_body', [
                'agency' => $this->visit->agency?->name ?? '',
            ])],
            'details' => [
                __('mail.common.description') => $this->visit->description,
                __('mail.common.date') => self::date($this->visit->service_date),
                __('mail.common.time') => self::time($this->visit->service_time),
                __('mail.common.address') => $this->visit->client?->address,
            ],
        ];
    }
}
