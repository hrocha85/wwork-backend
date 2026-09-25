<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Client;
use App\Support\ApiException;
use App\Support\ErrorCodes;

class AgendaResource
{
    /**
     * @return array<string, mixed>
     */
    public static function show(string $token): array
    {
        $client = Client::query()->where('agenda_token', $token)->first();

        if ($client === null) {
            throw new ApiException(ErrorCodes::AGENDA_INVALID_TOKEN, 404);
        }

        $visits = $client->visits()->orderBy('service_date')->orderBy('service_time')->get();

        return [
            'client_name' => $client->name,
            'visits' => $visits->map(fn ($visit): array => [
                'date' => $visit->service_date->toDateString(),
                'time' => substr((string) $visit->service_time, 0, 5),
                'done' => $visit->status->value === 'done',
            ])->values()->all(),
        ];
    }
}
