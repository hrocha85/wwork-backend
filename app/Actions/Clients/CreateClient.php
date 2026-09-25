<?php

namespace App\Actions\Clients;

use App\Models\Client;
use App\Policies\ClientPolicy;
use App\Support\AgencyContext;
use App\Support\ApiException;
use App\Support\ErrorCodes;
use App\Support\RecordActivity;

class CreateClient
{
    /**
     * @param  array{name: string, whatsapp: string, address: string, lat: mixed, lng: mixed}  $input
     */
    public function __invoke(array $input): Client
    {
        $actor = AgencyContext::user();
        $membership = AgencyContext::membership();

        if (! app(ClientPolicy::class)->create($actor)) {
            throw new ApiException(ErrorCodes::CLIENT_FORBIDDEN, 403);
        }

        if (! is_numeric($input['lat'] ?? null) || ! is_numeric($input['lng'] ?? null)) {
            throw new ApiException(ErrorCodes::CLIENT_MISSING_POINT, 422);
        }

        $client = Client::query()->create([
            'agency_id' => $membership->agency_id,
            'created_by' => $actor->id,
            'name' => $input['name'],
            'whatsapp' => $input['whatsapp'],
            'address' => $input['address'],
            'lat' => $input['lat'],
            'lng' => $input['lng'],
        ]);

        RecordActivity::add($membership->agency_id, $actor->id, 'client.created');

        return $client;
    }
}
