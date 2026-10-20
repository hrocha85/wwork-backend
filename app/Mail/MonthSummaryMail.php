<?php

namespace App\Mail;

use App\Models\Agency;

/**
 * Fechamento do mês anterior para o dono de uma agência que paga.
 */
class MonthSummaryMail extends NoticeMail
{
    public function __construct(
        public Agency $agency,
        public string $monthLabel,
        public int $services,
        public int $totalPence,
        public int $cashPence,
    ) {}

    protected function subjectLine(): string
    {
        return __('mail.month.subject', [
            'month' => $this->monthLabel,
            'agency' => $this->agency->name,
        ]);
    }

    protected function notice(): array
    {
        $currency = (string) $this->agency->currency;

        return [
            'heading' => __('mail.month.heading'),
            'lines' => [__('mail.month.body', ['month' => $this->monthLabel])],
            'details' => [
                __('mail.month.services') => (string) $this->services,
                __('mail.month.total') => self::money($this->totalPence, $currency),
                __('mail.month.cash') => self::money($this->cashPence, $currency),
            ],
            'button' => ['label' => __('mail.month.button'), 'url' => self::app('/billing')],
        ];
    }
}
