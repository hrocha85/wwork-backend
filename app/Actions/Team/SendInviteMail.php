<?php

namespace App\Actions\Team;

use App\Mail\PartnerInvited;
use App\Models\Invite;
use App\Models\User;
use App\Support\RecordActivity;
use Illuminate\Support\Facades\Mail;

class SendInviteMail
{
    /**
     * Envio síncrono: a hospedagem compartilhada não mantém `queue:work`.
     * Falha de SMTP não desfaz o convite; fica registrada como `mail.failed`.
     */
    public function __invoke(Invite $invite, string $plainToken, User $inviter): void
    {
        try {
            Mail::to($invite->email)
                ->locale($inviter->locale->value)
                ->send(new PartnerInvited($invite, $invite->url($plainToken)));
        } catch (\Throwable $exception) {
            report($exception);
            RecordActivity::add($invite->agency_id, $inviter->id, 'mail.failed');
        }
    }
}
