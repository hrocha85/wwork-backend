<?php

namespace App\Actions\OneSignal;

use App\Enums\MembershipRole;
use App\Models\User;
use App\Models\Visit;
use App\Services\OneSignalPush;
use Illuminate\Support\Str;

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
     * Gatilho 4 — fim do serviço. Delegate para a ação legada que já trata
     * o check-out (owner + cliente via player id).
     */
    public function finished(Visit $visit, ?int $durationSeconds): void
    {
        ($this->finished)($visit, $durationSeconds);
    }

    /**
     * @param  list<User>  $users
     */
    private function toUsers(array $users, string $title, string $body, ?string $path): void
    {
        $this->push->toUsers($users, $title, $body, $path, (string) Str::uuid());
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
