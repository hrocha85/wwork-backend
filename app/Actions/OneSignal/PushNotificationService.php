<?php

namespace App\Actions\OneSignal;

use App\Enums\MembershipRole;
use App\Models\User;
use App\Models\Visit;
use App\Services\OneSignalPush;
use Illuminate\Support\Str;
use Ramsey\Uuid\Uuid;

/**
 * Única fachada de push do app. Cada gatilho decide os destinatários;
 * quem não tem conta (cliente sem portal) cai no caminho legado de player id.
 */
class PushNotificationService
{
    public function __construct(
        private OneSignalPush $push,
        private NotifyJobFinished $finished,
    ) {}

    /**
     * Gatilho 1 — o agendamento acabou de ser criado.
     */
    public function created(Visit $visit): void
    {
        $visit->loadMissing(['client', 'agency', 'assignee.membership']);

        $date = $visit->service_date->locale('en')->isoFormat('LL');
        $this->toClient(
            $visit,
            'Booking confirmed',
            $visit->agency->name.' booked a visit for '.$date.' at '.self::time($visit->service_time),
            '/calendar',
        );

        if ($visit->assignee !== null && $visit->assignee->id !== $visit->agency->ownerMembership?->user_id) {
            $this->toUsers(
                [$visit->assignee],
                'New job',
                $visit->client->name.' — '.$date.' at '.self::time($visit->service_time),
                '/calendar',
            );
        }
    }

    /**
     * Gatilho 2 — visita nas próximas 2 a 24 horas (roda no cron de 15 em 15 min).
     */
    public function remind(Visit $visit): void
    {
        $visit->loadMissing(['client', 'agency', 'assignee']);

        $when = $visit->service_date->locale('en')->isoFormat('LL').' at '.self::time($visit->service_time);

        $this->toClient($visit, 'Coming up', 'Your visit with '.$visit->agency->name.' starts '.$when, '/calendar');

        if ($visit->assignee !== null) {
            $this->toUsers([$visit->assignee], 'Coming up', $visit->client->name.' — '.$when, '/calendar');
        }
    }

    /**
     * Gatilho 3 — o serviço começou (check-in).
     * O contratante é avisado, e o dono da agência também quando foi um colaborador
     * que executou.
     */
    public function checkIn(Visit $visit, int $actorId): void
    {
        $visit->loadMissing(['client', 'agency', 'assignee.membership']);

        $this->toClient(
            $visit,
            'Work started',
            $visit->agency->name.' just started your service.',
            '/calendar',
        );

        $assigneeIsOwner = $visit->assignee?->membership?->role === MembershipRole::Owner;

        if (! $assigneeIsOwner) {
            $owner = $visit->agency->ownerMembership()->with('user')->first()?->user;

            if ($owner !== null && $owner->id !== $actorId) {
                $this->toUsers(
                    [$owner],
                    'Service started',
                    ($visit->assignee?->name ?? 'Someone').' just started the job at '.$visit->client->name.'.',
                    '/calendar',
                );
            }
        }
    }

    /**
     * Gatilho 4 — fim do serviço. Delegate para a ação que notifica
     * o dono (quando outro executou) e o cliente.
     */
    public function finished(Visit $visit, ?int $durationSeconds): void
    {
        ($this->finished)($visit, $durationSeconds);
    }

    /**
     * Gatilho 5 — o profissional aceitou a solicitação de trabalho.
     * O destinatário é o empregador (dono da agência da visita); o e-mail
     * visit.accepted continua saindo pelo MailNotifier no AcceptVisit.
     */
    public function accepted(Visit $visit, User $actor): void
    {
        $visit->loadMissing(['client', 'agency']);

        $owner = $visit->agency->ownerMembership()->with('user')->first()?->user;

        if ($owner === null || $owner->id === $actor->id) {
            return;
        }

        $date = $visit->service_date->locale('en')->isoFormat('LL');

        $this->toUsers(
            [$owner],
            'Job accepted',
            $actor->name.' accepted the job for '.$visit->client->name.' on '.$date.' at '.self::time($visit->service_time),
            '/calendar',
            self::key($visit, 'accepted'),
        );
    }

    /**
     * @param  list<User>  $users
     */
    private function toUsers(array $users, string $title, string $body, ?string $path, ?string $idempotencyKey = null): void
    {
        $this->push->toUsers($users, $title, $body, $path, $idempotencyKey ?? (string) Str::uuid());
    }

    /**
     * UUID determinístico por visita+evento: reprocessar o mesmo evento
     * cai na chave de idempotência do OneSignal e não duplica o push.
     */
    private static function key(Visit $visit, string $event): string
    {
        return Uuid::uuid5(Uuid::NAMESPACE_URL, 'wwork.visit.'.$visit->id.'.'.$event)->toString();
    }

    /**
     * Cliente: tem portal → external id; só opt-in da agenda pública → player id.
     */
    private function toClient(Visit $visit, string $title, string $body, ?string $path): void
    {
        $client = $visit->client;

        if ($client === null) {
            return;
        }

        if ($client->user_id !== null) {
            $user = User::query()->find($client->user_id);

            if ($user !== null) {
                $this->toUsers([$user], $title, $body, $path);

                return;
            }
        }

        if (filled($client->onesignal_player_id)) {
            $this->push->toPlayerIds([(string) $client->onesignal_player_id], $title, $body, $path);
        }
    }

    private static function time(mixed $time): string
    {
        return $time === null || $time === '' ? '' : substr((string) $time, 0, 5);
    }
}
