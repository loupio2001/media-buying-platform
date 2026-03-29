<?php

namespace App\Services\Api;

use App\Models\Platform;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class PlatformApiService
{
    public function index(int $perPage = 15): LengthAwarePaginator
    {
        return Platform::query()->ordered()->paginate($perPage);
    }

    public function store(array $data): Platform
    {
        return Platform::create($data);
    }

    public function update(Platform $platform, array $data): Platform
    {
        $platform->update($data);

        return $platform->refresh();
    }

    public function delete(Platform $platform): void
    {
        $platform->delete();
    }
}
