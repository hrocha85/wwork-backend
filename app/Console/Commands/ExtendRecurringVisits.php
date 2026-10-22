<?php

namespace App\Console\Commands;

use App\Actions\Visits\ExpandRecurrence;
use App\Models\Visit;
use Illuminate\Console\Command;

/**
 * Alonga a série semanal das agências que pagam, depois do primeiro lote de semanas.
 */
class ExtendRecurringVisits extends Command
{
    protected $signature = 'visits:extend-recurrence';

    protected $description = 'Cria as próximas ocorrências das visitas recorrentes de quem paga';

    public function handle(ExpandRecurrence $expand): int
    {
        $created = 0;

        Visit::query()
            ->where('is_recurring', true)
            ->whereNull('parent_visit_id')
            ->with(['agency.subscription', 'goals'])
            ->orderBy('id')
            ->chunkById(100, function ($visits) use ($expand, &$created) {
                foreach ($visits as $visit) {
                    $created += count($expand->extend($visit));
                }
            });

        $this->info('Ocorrências criadas: '.$created);

        return self::SUCCESS;
    }
}
