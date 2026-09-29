<?php

namespace App\Mail;

use App\Models\BookingRequest;

class QuoteAnsweredMail extends NoticeMail
{
    public function __construct(
        public BookingRequest $request,
        public bool $accepted,
    ) {}

    private function key(string $suffix): string
    {
        return 'mail.quote.'.($this->accepted ? 'accepted_' : 'rejected_').$suffix;
    }

    protected function subjectLine(): string
    {
        return __($this->key('subject'), ['name' => $this->request->client_name]);
    }

    protected function notice(): array
    {
        $this->request->loadMissing('agency');
        $request = $this->request;

        return [
            'heading' => __($this->key('heading')),
            'lines' => [__($this->key('body'), ['name' => $request->client_name])],
            'details' => [
                __('mail.common.client') => $request->client_name,
                __('mail.common.address') => $request->address,
                __('mail.common.price') => $request->quote_pence === null ? null : self::money((int) $request->quote_pence, $request->agency->currency),
                __('mail.common.date') => $this->accepted ? self::date($request->proposed_date) : null,
                __('mail.common.time') => $this->accepted ? self::time($request->proposed_time) : null,
            ],
            'button' => [
                'label' => __($this->accepted ? 'mail.quote.accepted_button' : 'mail.booking.button'),
                'url' => self::app($this->accepted ? '/calendar' : '/booking'),
            ],
        ];
    }
}
