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
        // O convidado ainda não tem conta nem idioma preferido em lugar nenhum do
        // sistema: `null` manda o e-mail no idioma padrão, nunca no idioma de quem
        // convidou. Ao aceitar, o convidado escolhe o próprio idioma.
        $this->mail->send(
            'team.invite',
            $invite->email,
            null,
            new PartnerInvited($invite, $invite->url($plainToken)),
            $invite->agency_id,
            $inviter->id,
        );
    }
}
