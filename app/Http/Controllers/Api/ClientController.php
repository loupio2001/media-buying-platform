<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\ClientUpsertRequest;
use App\Models\Client;
use App\Services\Api\ClientApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClientController extends ApiController
{
    public function __construct(private ClientApiService $service)
    {
    }

    public function index(Request $request): JsonResponse
    {
        return $this->respondPaginated($this->service->index((int) $request->integer('per_page', 15)));
    }

    public function store(ClientUpsertRequest $request): JsonResponse
    {
        $client = $this->service->store($request->validated());

        return $this->respond($client, ['status' => 'created'], 201);
    }

    public function show(Client $client): JsonResponse
    {
        $client->load('category');

        return $this->respond($client, ['total' => 1]);
    }

    public function update(ClientUpsertRequest $request, Client $client): JsonResponse
    {
        return $this->respond($this->service->update($client, $request->validated()), ['status' => 'updated']);
    }

    public function destroy(Client $client): JsonResponse
    {
        $this->service->delete($client);

        return $this->respond(['id' => $client->id], ['status' => 'deleted']);
    }
}
