<?php

namespace App\Mail;

use App\Models\Visit;

/**
 * Parceiro respondeu ao serviço oferecido. Recusado volta para o dono.
 */
class VisitAnsweredMail extends NoticeMail
{
    public function __construct(
        public Visit $visit,
        public string $partnerName,
        public bool $accepted,
    ) {}

    private function key(string $suffix): string
    {
        return 'mail.visit.'.($this->accepted ? 'accepted_' : 'declined_').$suffix;
    }

    protected function subjectLine(): string
    {
        return __($this->key('subject'), [
            'name' => $this->partnerName,
            'date' => self::date($this->visit->service_date),
        ]);
    }

    protected function notice(): array
    {
        $this->visit->loadMissing('client');
        $visit = $this->visit;

        return [
            'heading' => __($this->key('heading')),
            'lines' => [__($this->key('body'), ['name' => $this->partnerName])],
            'details' => [
                __('mail.common.client') => $visit->client?->name,
                __('mail.common.address') => $visit->client?->address,
                __('mail.common.date') => self::date($visit->service_date),
                __('mail.common.time') => self::time($visit->service_time),
            ],
            'button' => ['label' => __('mail.visit.calendar_button'), 'url' => self::app('/calendar')],
        ];
    }
}
