<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Agenda\IssueAgendaLink;
use App\Actions\Agenda\SaveAgendaPlayer;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\AgendaResource;
use App\Models\Client;
use App\Support\ApiException;
use App\Support\ErrorCodes;
use Illuminate\Http\JsonResponse;

class AgendaController extends Controller
{
    public function show(string $token): JsonResponse
    {
        return response()->json(AgendaResource::show($token));
    }

    public function notify(string $token, SaveAgendaPlayer $save): JsonResponse
    {
        $save($token, request()->input('player_id'));

        return response()->json(['ok' => true]);
    }

    public function link(int $client, IssueAgendaLink $issue): JsonResponse
    {
        $model = Client::query()->find($client);

        if ($model === null) {
            throw new ApiException(ErrorCodes::CLIENT_NOT_FOUND, 404);
        }

        return response()->json($issue($model));
    }
}
