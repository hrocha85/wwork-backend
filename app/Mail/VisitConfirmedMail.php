<?php

namespace App\Mail;

use App\Models\Visit;

/**
 * Confirmação de um agendamento enviada ao cliente a partir da tela de resumo.
 */
class VisitConfirmedMail extends NoticeMail
{
    public function __construct(public Visit $visit) {}

    protected function subjectLine(): string
    {
        return __('mail.visit.confirmed_subject', [
            'agency' => $this->visit->agency?->name ?? '',
        ]);
    }

    protected function notice(): array
    {
        $this->visit->loadMissing(['client', 'agency']);

        return [
            'heading' => __('mail.visit.confirmed_heading'),
            'lines' => [__('mail.visit.confirmed_body', [
                'agency' => $this->visit->agency?->name ?? '',
            ])],
            'details' => [
                __('mail.common.client') => $this->visit->client?->name,
                __('mail.common.description') => $this->visit->description,
                __('mail.common.date') => self::date($this->visit->service_date),
                __('mail.common.time') => self::time($this->visit->service_time),
                __('mail.visit.end_time') => self::time($this->visit->estimated_end_time),
                __('mail.common.price') => $this->visit->price_pence === null
                    ? null
                    : self::money((int) $this->visit->price_pence, $this->visit->agency?->currency ?? ''),
            ],
            'button' => ['label' => __('mail.visit.calendar_button'), 'url' => self::app('/calendar')],
        ];
    }
}
