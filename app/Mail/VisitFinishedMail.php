<?php

namespace App\Mail;

use App\Models\Visit;

/**
 * Aviso de serviço concluído, com a fatura quando ela existe.
 * Só é disparado para agência com assinatura paga.
 */
class VisitFinishedMail extends NoticeMail
{
    public function __construct(public Visit $visit, public ?string $invoiceUrl = null) {}

    protected function subjectLine(): string
    {
        return __('mail.visit.finished_subject', [
            'agency' => $this->visit->agency?->name ?? '',
        ]);
    }

    protected function notice(): array
    {
        $this->visit->loadMissing(['client', 'agency']);
        $cash = $this->visit->payment_method === 'cash';

        return [
            'heading' => __('mail.visit.finished_heading'),
            'lines' => [
                __('mail.visit.finished_body', [
                    'agency' => $this->visit->agency?->name ?? '',
                ]),
                __('mail.visit.finished_review', [
                    'agency' => $this->visit->agency?->name ?? '',
                ]),
            ],
            'details' => [
                __('mail.common.description') => $this->visit->description,
                __('mail.common.date') => self::date($this->visit->service_date),
                __('mail.common.price') => $this->visit->price_pence === null
                    ? null
                    : self::money((int) $this->visit->price_pence, $this->visit->agency?->currency ?? ''),
                __('mail.visit.payment') => $cash
                    ? __('mail.visit.paid_cash')
                    : __('mail.visit.invoice_pending'),
            ],
            'button' => filled($this->invoiceUrl)
                ? ['label' => __('mail.visit.finished_button'), 'url' => $this->invoiceUrl]
                : null,
        ];
    }
}
