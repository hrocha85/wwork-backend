<?php

namespace App\Actions\Team;

use App\Mail\PartnerInvited;
use App\Models\Invite;
use App\Models\User;
use App\Support\MailNotifier;

class SendInviteMail
{
    public function __construct(private MailNotifier $mail) {}

    public function __invoke(Invite $invite, string $plainToken, User $inviter): void
    {
        $this->mail->send(
            'team.invite',
            $invite->email,
            $inviter->locale->value,
            new PartnerInvited($invite, $invite->url($plainToken)),
            $invite->agency_id,
            $inviter->id,
        );
    }
}
