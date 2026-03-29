<?php

namespace App\Services\Api;

use App\Models\Client;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ClientApiService
{
    public function index(int $perPage = 15): LengthAwarePaginator
    {
        return Client::query()->with('category')->paginate($perPage);
    }

    public function store(array $data): Client
    {
        return Client::create($data);
    }

    public function update(Client $client, array $data): Client
    {
        $client->update($data);

        return $client->refresh();
    }

    public function delete(Client $client): void
    {
        $client->delete();
    }
}
