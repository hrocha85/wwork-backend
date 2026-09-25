<?php

namespace App\Actions\Agenda;

use App\Models\Client;
use App\Support\ApiException;
use App\Support\ErrorCodes;

class SaveAgendaPlayer
{
    public function __invoke(string $token, mixed $playerId): void
    {
        $client = Client::query()->where('agenda_token', $token)->first();

        if ($client === null) {
            throw new ApiException(ErrorCodes::AGENDA_INVALID_TOKEN, 404);
        }

        if (! is_string($playerId) || trim($playerId) === '') {
            throw new ApiException(ErrorCodes::AGENDA_MISSING_PLAYER, 422);
        }

        $client->onesignal_player_id = $playerId;
        $client->save();
    }
}
