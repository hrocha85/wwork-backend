<?php

namespace App\Mail;

/**
 * Os dados são copiados na hora do cancelamento: a visita já foi apagada quando o e-mail sai.
 */
class VisitCancelledMail extends NoticeMail
{
    public function __construct(
        public string $agencyName,
        public ?string $date,
        public ?string $time,
        public ?string $address,
    ) {}

    protected function subjectLine(): string
    {
        return __('mail.visit.cancelled_subject', ['date' => self::date($this->date)]);
    }

    protected function notice(): array
    {
        return [
            'heading' => __('mail.visit.cancelled_heading'),
            'lines' => [__('mail.visit.cancelled_body', ['agency' => $this->agencyName])],
            'details' => [
                __('mail.common.date') => self::date($this->date),
                __('mail.common.time') => self::time($this->time),
                __('mail.common.address') => $this->address,
            ],
            'button' => ['label' => __('mail.visit.calendar_button'), 'url' => self::app('/calendar')],
        ];
    }
}
