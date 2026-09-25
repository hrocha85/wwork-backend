<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Clients\CreateClient;
use App\Actions\Clients\DeleteClient;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ListClientsRequest;
use App\Http\Requests\Api\V1\StoreClientRequest;
use App\Http\Resources\Api\V1\ClientResource;
use App\Models\Client;
use App\Support\ApiException;
use App\Support\ErrorCodes;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class ClientController extends Controller
{
    public function index(ListClientsRequest $request): JsonResponse
    {
        $day = $request->string('day')->toString();

        return response()->json(ClientResource::index($day === '' ? 'all' : $day));
    }

    public function store(StoreClientRequest $request, CreateClient $create): JsonResponse
    {
        $client = $create($request->validated());

        return response()->json(ClientResource::created($client), 201);
    }

    public function show(int $client): JsonResponse
    {
        return response()->json(ClientResource::show($this->client($client)));
    }

    public function destroy(int $client, DeleteClient $delete): Response
    {
        $delete($this->client($client));

        return response()->noContent();
    }

    private function client(int $id): Client
    {
        $client = Client::query()->find($id);

        if ($client === null) {
            throw new ApiException(ErrorCodes::CLIENT_NOT_FOUND, 404);
        }

        return $client;
    }
}
