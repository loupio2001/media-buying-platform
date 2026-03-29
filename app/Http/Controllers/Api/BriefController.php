<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\BriefUpsertRequest;
use App\Models\Brief;
use App\Services\Api\BriefApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BriefController extends ApiController
{
    public function __construct(private BriefApiService $service)
    {
    }

    public function index(Request $request): JsonResponse
    {
        return $this->respondPaginated($this->service->index((int) $request->integer('per_page', 15)));
    }

    public function store(BriefUpsertRequest $request): JsonResponse
    {
        $brief = $this->service->store($request->validated());

        return $this->respond($brief, ['status' => 'created'], 201);
    }

    public function show(Brief $brief): JsonResponse
    {
        $brief->load(['campaign', 'reviewer']);

        return $this->respond($brief, ['total' => 1]);
    }

    public function update(BriefUpsertRequest $request, Brief $brief): JsonResponse
    {
        return $this->respond($this->service->update($brief, $request->validated()), ['status' => 'updated']);
    }

    public function destroy(Brief $brief): JsonResponse
    {
        $this->service->delete($brief);

        return $this->respond(['id' => $brief->id], ['status' => 'deleted']);
    }
}
