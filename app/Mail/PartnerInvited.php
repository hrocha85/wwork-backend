<?php

namespace App\Mail;

use App\Models\Invite;

class PartnerInvited extends NoticeMail
{
    public function __construct(
        public Invite $invite,
        public string $url,
    ) {}

    protected function subjectLine(): string
    {
        $this->invite->loadMissing('agency');

        return __('mail.invite.subject', ['agency' => $this->invite->agency->name]);
    }

    protected function notice(): array
    {
        $this->invite->loadMissing(['agency', 'inviter']);
        $agency = $this->invite->agency->name;
        $inviter = $this->invite->inviter?->name;

        return [
            'heading' => __('mail.invite.heading'),
            'lines' => [
                $inviter
                    ? __('mail.invite.body_by', ['inviter' => $inviter, 'agency' => $agency])
                    : __('mail.invite.body', ['agency' => $agency]),
                __('mail.invite.steps'),
                __('mail.invite.expires', ['days' => Invite::TTL_DAYS]),
            ],
            'button' => ['label' => __('mail.invite.button'), 'url' => $this->url],
            'note' => __('mail.invite.ignore'),
        ];
    }
}
