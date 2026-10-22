<?php

namespace App\Mail;

class AccessCodeMail extends NoticeMail
{
    public function __construct(public string $code) {}

    protected function subjectLine(): string
    {
        return __('mail.access.subject');
    }

    protected function notice(): array
    {
        return [
            'heading' => __('mail.access.heading'),
            'lines' => [__('mail.access.body')],
            'details' => [
                __('mail.access.code') => $this->code,
            ],
            'note' => __('mail.access.expires'),
        ];
    }
}
