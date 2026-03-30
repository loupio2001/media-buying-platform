<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\PlatformConnectionUpsertRequest;
use App\Models\PlatformConnection;
use App\Services\Api\PlatformConnectionApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlatformConnectionController extends ApiController
{
    public function __construct(private PlatformConnectionApiService $service)
    {
    }

    public function index(Request $request): JsonResponse
    {
        return $this->respondPaginated($this->service->index((int) $request->integer('per_page', 15)));
    }

    public function store(PlatformConnectionUpsertRequest $request): JsonResponse
    {
        $platformConnection = $this->service->store(
            $request->validated(),
            (int) $request->user()->id,
        );

        return $this->respond($platformConnection, ['status' => 'created'], 201);
    }

    public function show(PlatformConnection $platformConnection): JsonResponse
    {
        $platformConnection->load(['platform', 'creator', 'campaignPlatforms']);

        return $this->respond($platformConnection, ['total' => 1]);
    }

    public function update(PlatformConnectionUpsertRequest $request, PlatformConnection $platformConnection): JsonResponse
    {
        return $this->respond(
            $this->service->update($platformConnection, $request->validated()),
            ['status' => 'updated']
        );
    }

    public function destroy(PlatformConnection $platformConnection): JsonResponse
    {
        $this->service->delete($platformConnection);

        return $this->respond(['id' => $platformConnection->id], ['status' => 'deleted']);
    }
}