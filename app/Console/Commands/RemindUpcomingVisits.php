<?php

namespace App\Console\Commands;

use App\Actions\OneSignal\PushNotificationService;
use App\Enums\VisitStatus;
use App\Models\Visit;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Lembrete de proximidade: agenda um push para quem tem visita nas próximas 2 a 24h.
 * Roda a cada 15 minutos; a coluna `reminded_at` garante um aviso por visita.
 */
class RemindUpcomingVisits extends Command
{
    protected $signature = 'visits:remind-upcoming';

    protected $description = 'Envia push de lembrete para visitas que começam em 2 a 24 horas';

    public function handle(PushNotificationService $push): int
    {
        $now = Carbon::now();
        $sent = 0;

        Visit::query()
            ->with(['client', 'agency', 'assignee'])
            ->whereNull('reminded_at')
            ->whereIn('status', [VisitStatus::Todo->value, VisitStatus::EnRoute->value])
            ->whereNotNull('service_date')
            ->whereNotNull('service_time')
            ->chunkById(200, function ($visits) use ($push, $now, &$sent) {
                foreach ($visits as $visit) {
                    if (! $this->insideWindow($visit, $now)) {
                        continue;
                    }

                    $push->remind($visit);
                    $visit->forceFill(['reminded_at' => $now])->saveQuietly();
                    $sent++;
                }
            });

        $this->info('Lembretes enviados: '.$sent);

        return self::SUCCESS;
    }

    private function insideWindow(Visit $visit, Carbon $now): bool
    {
        $timezone = $visit->agency?->timezone ?: 'UTC';

        try {
            $start = Carbon::parse(
                $visit->service_date->toDateString().' '.substr((string) $visit->service_time, 0, 5),
                $timezone,
            );
        } catch (\Throwable) {
            return false;
        }

        if ($start->lessThan($now)) {
            return false;
        }

        $hours = $now->diffInHours($start);

        return $hours >= 2 && $hours <= 24;
    }
}
