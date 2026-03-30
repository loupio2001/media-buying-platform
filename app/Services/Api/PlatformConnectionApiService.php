<?php

namespace App\Services\Api;

use App\Models\PlatformConnection;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class PlatformConnectionApiService
{
    public function index(int $perPage = 15): LengthAwarePaginator
    {
        return PlatformConnection::query()
            ->with(['platform', 'creator'])
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function store(array $data, int $actorId): PlatformConnection
    {
        $data['created_by'] = $actorId;

        return PlatformConnection::create($data);
    }

    public function update(PlatformConnection $platformConnection, array $data): PlatformConnection
    {
        unset($data['created_by']);

        $platformConnection->update($data);

        return $platformConnection->refresh();
    }

    public function delete(PlatformConnection $platformConnection): void
    {
        $platformConnection->delete();
    }
}