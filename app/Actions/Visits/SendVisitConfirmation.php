<?php

namespace App\Actions\Visits;

use App\Mail\VisitConfirmedMail;
use App\Models\Visit;
use App\Policies\VisitPolicy;
use App\Support\AgencyContext;
use App\Support\ApiException;
use App\Support\ErrorCodes;
use App\Support\MailNotifier;
use App\Support\RecordActivity;

/**
 * Reenvia a confirmação do agendamento para o e-mail cadastrado do cliente.
 * Acionado pelo botão da tela de resumo pós-agendamento.
 */
class SendVisitConfirmation
{
    public function __invoke(Visit $visit): void
    {
        $actor = AgencyContext::user();
        $membership = AgencyContext::membership();

        $denial = app(VisitPolicy::class)->denial($actor, $visit);

        if ($denial !== null || $membership->agency_id !== $visit->agency_id) {
            throw new ApiException(ErrorCodes::VISIT_FORBIDDEN, 403);
        }

        $visit->loadMissing(['client', 'agency']);

        if (! filled($visit->client?->email)) {
            throw new ApiException(ErrorCodes::VISIT_CLIENT_NO_EMAIL, 409);
        }

        app(MailNotifier::class)->send(
            'visit.confirmed',
            (string) $visit->client->email,
            $visit->client->user?->getAttributes()['locale'] ?? null,
            new VisitConfirmedMail($visit),
            $visit->agency_id,
            $actor->id,
        );

        RecordActivity::add($visit->agency_id, $actor->id, 'visit.confirmed');
    }
}
