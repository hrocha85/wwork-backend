<?php

namespace App\Actions\OneSignal;

use App\Enums\MembershipRole;
use App\Enums\Trade;
use App\Models\User;
use App\Models\Visit;
use App\Services\OneSignalPush;
use App\Support\RecordActivity;
use Ramsey\Uuid\Uuid;

/**
 * Gatilho 4 da fachada — o serviço foi concluído (check-out).
 * Reescrita sobre OneSignalPush: o dono (empregador) é avisado pelo external id
 * (alias user-{id}), o cliente sem portal continua no player id legado.
 * A chave de idempotência é determinística por visita, então o mesmo evento
 * reprocessado não duplica a notificação.
 */
class NotifyJobFinished
{
    public function __construct(private OneSignalPush $push) {}

    public function __invoke(Visit $visit, ?int $durationSeconds): void
    {
        $visit->loadMissing('client', 'agency', 'assignee.membership');

        if (! $this->push->configured()) {
            return;
        }

        $assigneeIsOwner = $visit->assignee?->membership?->role === MembershipRole::Owner;
        $duration = $durationSeconds === null ? '' : ' ('.$durationSeconds.'s)';
        $title = $this->houseTitle($visit->agency->trade);

        // O dono só é avisado quando o serviço foi executado por outra pessoa.
        if (! $assigneeIsOwner) {
            $owner = $visit->agency->ownerMembership()->with('user')->first()?->user;

            if ($owner !== null && $owner->id !== $visit->assignee_id) {
                $this->record($visit, $this->push->toUsers(
                    [$owner],
                    'Job finished',
                    trim(($visit->assignee?->name ?? 'Someone').' finished the job at '.$visit->client->address.$duration),
                    '/calendar',
                    self::key($visit, 'owner'),
                ));
            }
        }

        // Cliente: conta no app → external id; opt-in da agenda pública → player id.
        $client = $visit->client;

        if ($client->user_id !== null) {
            $user = User::query()->find($client->user_id);

            if ($user !== null) {
                $this->record($visit, $this->push->toUsers(
                    [$user],
                    $title,
                    (string) $client->address,
                    '/calendar',
                    self::key($visit, 'client'),
                ));

                return;
            }
        }

        if (filled($client->onesignal_player_id)) {
            $this->record($visit, $this->push->toPlayerIds(
                [(string) $client->onesignal_player_id],
                $title,
                (string) $client->address,
                '/calendar',
            ));
        }
    }

    /**
     * @param  array{sent: bool, status: int|null, id: string|null, errors: mixed}  $result
     */
    private function record(Visit $visit, array $result): void
    {
        if (! $result['sent']) {
            RecordActivity::add($visit->agency_id, $visit->assignee_id, 'onesignal.failed');
        }
    }

    /**
     * UUID determinístico por visita+evento: o OneSignal descarta repetições
     * da mesma chave por 30 dias, cobrindo reprocessamentos do mesmo check-out.
     */
    private static function key(Visit $visit, string $event): string
    {
        return Uuid::uuid5(Uuid::NAMESPACE_URL, 'wwork.visit.'.$visit->id.'.'.$event)->toString();
    }

    private function houseTitle(Trade $trade): string
    {
        return match ($trade) {
            Trade::Lawn => 'Your lawn visit is done',
            default => 'Your visit is done',
        };
    }
}
