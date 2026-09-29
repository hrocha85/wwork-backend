<?php

namespace App\Mail;

class PasswordChangedMail extends NoticeMail
{
    public function __construct(public string $name) {}

    protected function subjectLine(): string
    {
        return __('mail.password_changed.subject');
    }

    protected function notice(): array
    {
        return [
            'heading' => __('mail.password_changed.heading', ['name' => $this->name]),
            'lines' => [__('mail.password_changed.body')],
            'button' => ['label' => __('mail.password_changed.button'), 'url' => self::app('/forgot-password')],
            'note' => __('mail.password_changed.note'),
        ];
    }
}
