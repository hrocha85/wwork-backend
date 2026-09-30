<?php

namespace App\Support;

use App\Models\Agency;
use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Único ponto de envio de e-mail do app.
 *
 * Envio síncrono: a hospedagem compartilhada não mantém `queue:work`.
 * Dentro de uma transação, o envio espera o commit; se a transação desfizer, nada sai.
 * Falha de SMTP não desfaz a ação de negócio: vai para `report()`, `storage/logs/mail.log`
 * e para a atividade `mail.failed`.
 */
class MailNotifier
{
    public function toUser(string $type, User $user, Mailable $mail, ?int $agencyId = null, ?int $actorId = null): void
    {
        $this->send($type, $user->email, $user->locale->value, $mail, $agencyId, $actorId);
    }

    public function toOwner(string $type, Agency $agency, Mailable $mail, ?int $actorId = null): void
    {
        $owner = self::owner($agency);
        if ($owner === null) {
            return;
        }

        $this->toUser($type, $owner, $mail, $agency->id, $actorId);
    }

    public function send(string $type, string $email, ?string $locale, Mailable $mail, ?int $agencyId = null, ?int $actorId = null): void
    {
        if (! filled($email)) {
            return;
        }

        DB::afterCommit(fn () => $this->deliver($type, $email, $locale, $mail, $agencyId, $actorId));
    }

    public static function owner(Agency $agency): ?User
    {
        return $agency->ownerMembership()->with('user')->first()?->user;
    }

    private function deliver(string $type, string $email, ?string $locale, Mailable $mail, ?int $agencyId, ?int $actorId): void
    {
        $context = ['type' => $type, 'to' => $email, 'agency_id' => $agencyId];

        try {
            Mail::to($email)->locale($locale ?? (string) config('app.locale'))->send($mail);
            Log::channel('mail')->info('mail.sent', $context);
        } catch (\Throwable $exception) {
            report($exception);
            Log::channel('mail')->error('mail.failed', $context + ['error' => $exception->getMessage()]);
            RecordActivity::add($agencyId, $actorId, 'mail.failed', ['type' => $type]);
        }
    }
}
