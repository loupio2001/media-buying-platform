<?php

namespace App\Services\Api;

use App\Models\Brief;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class BriefApiService
{
    public function index(int $perPage = 15): LengthAwarePaginator
    {
        return Brief::query()->with(['campaign', 'reviewer'])->paginate($perPage);
    }

    public function store(array $data): Brief
    {
        return Brief::create($data);
    }

    public function update(Brief $brief, array $data): Brief
    {
        $brief->update($data);

        return $brief->refresh();
    }

    public function delete(Brief $brief): void
    {
        $brief->delete();
    }
}
