<?php

namespace App\Actions\Agenda;

use App\Models\Client;
use App\Policies\ClientPolicy;
use App\Support\AgencyContext;
use App\Support\ApiException;
use App\Support\ErrorCodes;
use App\Support\RecordActivity;
use Illuminate\Support\Str;

class IssueAgendaLink
{
    /**
     * @return array{url: string, whatsapp: string}
     */
    public function __invoke(Client $client): array
    {
        $actor = AgencyContext::user();

        if (! app(ClientPolicy::class)->view($actor, $client)) {
            throw new ApiException(ErrorCodes::CLIENT_FORBIDDEN, 403);
        }

        if (! filled($client->agenda_token)) {
            $client->agenda_token = Str::random(64);
            $client->save();
        }

        RecordActivity::add($client->agency_id, $actor->id, 'agenda.link');

        return [
            'url' => config('wwork.frontend_url').'/agenda/'.$client->agenda_token,
            'whatsapp' => (string) $client->whatsapp,
        ];
    }
}
