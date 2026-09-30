<?php

namespace App\Mail;

class InviteAcceptedMail extends NoticeMail
{
    public function __construct(
        public string $partnerName,
        public string $partnerEmail,
    ) {}

    protected function subjectLine(): string
    {
        return __('mail.invite_accepted.subject', ['name' => $this->partnerName]);
    }

    protected function notice(): array
    {
        return [
            'heading' => __('mail.invite_accepted.heading'),
            'lines' => [__('mail.invite_accepted.body', ['name' => $this->partnerName])],
            'details' => [
                __('mail.common.name') => $this->partnerName,
                __('mail.common.email') => $this->partnerEmail,
            ],
            'button' => ['label' => __('mail.invite_accepted.button'), 'url' => self::app('/invites')],
        ];
    }
}
