<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\PlatformUpsertRequest;
use App\Models\Platform;
use App\Services\Api\PlatformApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlatformController extends ApiController
{
    public function __construct(private PlatformApiService $service)
    {
    }

    public function index(Request $request): JsonResponse
    {
        return $this->respondPaginated($this->service->index((int) $request->integer('per_page', 15)));
    }

    public function store(PlatformUpsertRequest $request): JsonResponse
    {
        $platform = $this->service->store($request->validated());

        return $this->respond($platform, ['status' => 'created'], 201);
    }

    public function show(Platform $platform): JsonResponse
    {
        return $this->respond($platform, ['total' => 1]);
    }

    public function update(PlatformUpsertRequest $request, Platform $platform): JsonResponse
    {
        return $this->respond($this->service->update($platform, $request->validated()), ['status' => 'updated']);
    }

    public function destroy(Platform $platform): JsonResponse
    {
        $this->service->delete($platform);

        return $this->respond(['id' => $platform->id], ['status' => 'deleted']);
    }
}
