<?php

namespace App\Mail;

use App\Models\Client;

class ProfileInviteMail extends NoticeMail
{
    public function __construct(public Client $client, public string $url) {}

    protected function subjectLine(): string
    {
        return __('mail.invite_profile.subject', ['agency' => $this->client->agency?->name ?? '']);
    }

    protected function notice(): array
    {
        $this->client->loadMissing('agency');

        return [
            'heading' => __('mail.invite_profile.heading'),
            'lines' => [__('mail.invite_profile.body', ['agency' => $this->client->agency?->name ?? ''])],
            'button' => ['label' => __('mail.invite_profile.button'), 'url' => $this->url],
            'note' => __('mail.invite_profile.ignore'),
        ];
    }
}
