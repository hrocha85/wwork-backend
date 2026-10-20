<?php

namespace App\Console\Commands;

use App\Enums\VisitStatus;
use App\Mail\MonthSummaryMail;
use App\Models\Agency;
use App\Models\Visit;
use App\Support\MailNotifier;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * No dia 1, manda ao dono o fechamento do mês anterior.
 * Só agência com assinatura paga (ativa, cortesia ou paga fora).
 */
class EmailMonthSummary extends Command
{
    protected $signature = 'billing:email-month';

    protected $description = 'Envia ao dono o resumo do mês anterior, só para quem paga';

    public function handle(): int
    {
        $sent = 0;

        Agency::query()->with('subscription')->orderBy('id')->chunkById(100, function ($agencies) use (&$sent) {
            foreach ($agencies as $agency) {
                if (! $agency->hasActiveSubscription()) {
                    continue;
                }

                $timezone = $agency->timezone ?: 'UTC';
                $month = Carbon::now($timezone)->subMonthNoOverflow();
                $from = $month->copy()->startOfMonth()->toDateString();
                $to = $month->copy()->endOfMonth()->toDateString();

                $done = Visit::query()
                    ->where('agency_id', $agency->id)
                    ->where('status', VisitStatus::Done)
                    ->whereDate('service_date', '>=', $from)
                    ->whereDate('service_date', '<=', $to);

                $services = (clone $done)->count();
                if ($services === 0) {
                    continue;
                }

                $total = (int) (clone $done)->sum('price_pence');
                $cash = (int) (clone $done)->where('payment_method', 'cash')->sum('price_pence');
                $owner = MailNotifier::owner($agency);
                $label = $month->locale($owner === null ? app()->getLocale() : MailNotifier::localeFor($owner))->isoFormat('MMMM YYYY');

                if (app(MailNotifier::class)->toOwner(
                    'billing.month',
                    $agency,
                    new MonthSummaryMail($agency, $label, $services, $total, $cash),
                )) {
                    $sent++;
                }
            }
        });

        $this->info('Resumos enviados: '.$sent);

        return self::SUCCESS;
    }
}
