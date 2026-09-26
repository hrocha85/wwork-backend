<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Agenda\CreateAgendaBlock;
use App\Actions\Agenda\DeleteAgendaBlock;
use App\Actions\Agenda\IssueAgendaLink;
use App\Actions\Agenda\SaveAgendaPlayer;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\AgendaResource;
use App\Models\AgendaBlock;
use App\Models\Client;
use App\Support\AgencyContext;
use App\Support\ApiException;
use App\Support\ErrorCodes;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

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

    public function blocks(Request $request): JsonResponse
    {
        $from = $request->string('from')->toString();
        $to = $request->string('to')->toString();

        if ($from === '' || $to === '') {
            throw new ApiException(ErrorCodes::AGENDA_ENDS_BEFORE_START, 422);
        }

        return response()->json(AgendaResource::blocks($from, $to));
    }

    public function storeBlock(Request $request, CreateAgendaBlock $create): JsonResponse
    {
        $block = $create($request->only(['starts_at', 'ends_at', 'note']));

        return response()->json(AgendaResource::block($block), 201);
    }

    public function destroyBlock(int $block, DeleteAgendaBlock $delete): Response
    {
        $model = AgendaBlock::query()
            ->where('agency_id', AgencyContext::membership()->agency_id)
            ->find($block);

        if ($model === null) {
            throw new ApiException(ErrorCodes::AGENDA_BLOCK_FORBIDDEN, 403);
        }

        $delete($model);

        return response()->noContent();
    }
}
