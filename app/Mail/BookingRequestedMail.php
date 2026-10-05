<?php

namespace App\Mail;

use App\Models\BookingRequest;

/**
 * Pedido novo no link público: horário (`kind` vazio) ou orçamento (`kind = quote`).
 */
class BookingRequestedMail extends NoticeMail
{
    public function __construct(public BookingRequest $request) {}

    private function isQuote(): bool
    {
        return $this->request->kind === 'quote';
    }

    protected function subjectLine(): string
    {
        return __($this->isQuote() ? 'mail.booking.quote_subject' : 'mail.booking.slot_subject', [
            'name' => $this->request->client_name,
        ]);
    }

    protected function notice(): array
    {
        $this->request->loadMissing('service');
        $request = $this->request;

        $details = $this->isQuote()
            ? [
                __('mail.common.client') => $request->client_name,
                __('mail.common.phone') => $request->client_phone,
                __('mail.common.address') => $request->address,
                __('mail.common.description') => $request->description,
            ]
            : [
                __('mail.common.client') => $request->client_name,
                __('mail.common.phone') => $request->client_phone,
                __('mail.common.service') => $request->service?->name,
                __('mail.common.date') => self::date($request->requested_date),
                __('mail.common.time') => self::time($request->requested_time),
            ];

        return [
            'heading' => __($this->isQuote() ? 'mail.booking.quote_heading' : 'mail.booking.slot_heading'),
            'lines' => [__($this->isQuote() ? 'mail.booking.quote_body' : 'mail.booking.slot_body')],
            'details' => $details,
            'button' => ['label' => __('mail.booking.button'), 'url' => self::app('/booking')],
        ];
    }
}
