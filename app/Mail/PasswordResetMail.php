<?php

namespace App\Mail;

class PasswordResetMail extends NoticeMail
{
    public function __construct(
        public string $url,
        public int $minutes,
    ) {}

    protected function subjectLine(): string
    {
        return __('mail.reset.subject');
    }

    protected function notice(): array
    {
        return [
            'heading' => __('mail.reset.heading'),
            'lines' => [__('mail.reset.body'), __('mail.reset.expires', ['minutes' => $this->minutes])],
            'button' => ['label' => __('mail.reset.button'), 'url' => $this->url],
            'note' => __('mail.reset.ignore'),
        ];
    }
}
