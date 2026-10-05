<?php

namespace App\Support;

use App\Enums\Locale;
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
    /**
     * @return bool true só quando o envio de fato aconteceu; falso quando o SMTP falhou
     *              ou quando o destinatário não tem e-mail. Quem decide retry (um comando
     *              diário, por exemplo) lê este retorno.
     */
    public function toUser(string $type, User $user, Mailable $mail, ?int $agencyId = null, ?int $actorId = null): bool
    {
        return $this->send($type, $user->email, self::localeOf($user), $mail, $agencyId, $actorId);
    }

    public function toOwner(string $type, Agency $agency, Mailable $mail, ?int $actorId = null): bool
    {
        $owner = self::owner($agency);
        if ($owner === null) {
            return false;
        }

        return $this->toUser($type, $owner, $mail, $agency->id, $actorId);
    }

    /**
     * Idioma do destinatário, lido como valor bruto no momento do disparo
     * (não depende do contexto da request nem de fila).
     *
     * Conta sem idioma devolve `null`; a validação do valor fica em `deliver()`.
     * Ler o atributo bruto evita o cast para o enum, que lançaria exceção
     * derrubando a ação de negócio caso o banco tenha um valor inválido.
     */
    private static function localeOf(User $user): ?string
    {
        $stored = $user->getAttributes()['locale'] ?? null;

        return is_string($stored) ? $stored : null;
    }

    /**
     * Idioma que este destinatário vai receber, já validado.
     *
     * Serve para formatar algo que entra no e-mail (uma data em Carbon, por exemplo)
     * exatamente no idioma em que o texto vai sair.
     */
    public static function localeFor(User $user): string
    {
        return self::supported(self::localeOf($user));
    }

    public function send(string $type, string $email, ?string $locale, Mailable $mail, ?int $agencyId = null, ?int $actorId = null): bool
    {
        if (! filled($email)) {
            return false;
        }

        // Fora de transação (comando, cron) o callback roda aqui mesmo e o retorno
        // é o resultado real do envio. Dentro de transação ele fica adiado para o
        // commit, e o retorno é false: nada ainda foi entregue.
        $delivered = null;
        DB::afterCommit(function () use (&$delivered, $type, $email, $locale, $mail, $agencyId, $actorId): void {
            $delivered = $this->deliver($type, $email, $locale, $mail, $agencyId, $actorId);
        });

        return $delivered ?? false;
    }

    public static function owner(Agency $agency): ?User
    {
        return $agency->ownerMembership()->with('user')->first()?->user;
    }

    private function deliver(string $type, string $email, ?string $locale, Mailable $mail, ?int $agencyId, ?int $actorId): bool
    {
        $context = ['type' => $type, 'to' => $email, 'agency_id' => $agencyId];

        try {
            Mail::to($email)->locale(self::supported($locale))->send($mail);
            Log::channel('mail')->info('mail.sent', $context);

            return true;
        } catch (\Throwable $exception) {
            report($exception);
            Log::channel('mail')->error('mail.failed', $context + ['error' => $exception->getMessage()]);
            RecordActivity::add($agencyId, $actorId, 'mail.failed', ['type' => $type]);

            return false;
        }
    }

    /**
     * Única porta de entrada do idioma: só sai e-mail num idioma suportado.
     *
     * Idioma ausente ou fora do enum `Locale` cai no idioma padrão do sistema,
     * e a tradução completa daquele idioma é garantida pelo catálogo `lang/{locale}`.
     */
    private static function supported(?string $locale): string
    {
        return Locale::tryFrom((string) $locale)?->value ?? (string) config('app.locale');
    }
}
