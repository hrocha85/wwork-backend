<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Clients\CreateClient;
use App\Actions\Clients\DeleteClient;
use App\Actions\Clients\ImportClients;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ListClientsRequest;
use App\Http\Requests\Api\V1\StoreClientRequest;
use App\Http\Resources\Api\V1\ClientResource;
use App\Models\Client;
use App\Policies\ClientPolicy;
use App\Support\AgencyContext;
use App\Support\ApiException;
use App\Support\ErrorCodes;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

    public function update(int $client, Request $request): JsonResponse
    {
        $model = $this->client($client);
        $actor = AgencyContext::user();
        if (! app(ClientPolicy::class)->view($actor, $model)) {
            throw new ApiException(ErrorCodes::CLIENT_FORBIDDEN, 403);
        }
        if ($request->exists('name')) {
            $name = trim($request->string('name')->toString());
            if (mb_strlen($name) < 2 || mb_strlen($name) > 80) {
                throw new ApiException(ErrorCodes::CLIENT_INVALID, 422);
            }
            $model->name = $name;
        }
        if ($request->exists('whatsapp')) {
            $phone = trim($request->string('whatsapp')->toString());
            if ($phone === '' || mb_strlen($phone) > 32) {
                throw new ApiException(ErrorCodes::CLIENT_INVALID, 422);
            }
            $model->whatsapp = $phone;
        }
        if ($request->exists('email')) {
            $email = trim($request->string('email')->toString());
            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                throw new ApiException(ErrorCodes::CLIENT_INVALID, 422);
            }
            $model->email = $email === '' ? null : $email;
        }
        if ($request->exists('note')) {
            $model->note = $request->string('note')->toString();
        }
        if ($request->exists('address')) {
            $address = trim($request->string('address')->toString());
            if (mb_strlen($address) > 255) {
                throw new ApiException(ErrorCodes::CLIENT_INVALID, 422);
            }
            $model->address = $address;
        }
        if ($request->exists('lat') && $request->exists('lng')) {
            if ($request->filled('lat') && $request->filled('lng')) {
                $model->lat = $request->input('lat');
                $model->lng = $request->input('lng');
            } else {
                $model->lat = null;
                $model->lng = null;
            }
        }
        $model->save();

        return response()->json(ClientResource::show($model));
    }

    public function import(Request $request, ImportClients $import): JsonResponse
    {
        $rows = $request->input('rows');

        return response()->json($import->sheet(is_array($rows) ? $rows : [], $request->boolean('commit')));
    }

    public function importCalendar(Request $request, ImportClients $import): JsonResponse
    {
        return response()->json($import->calendar($request->string('ics')->toString(), $request->boolean('commit')));
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
