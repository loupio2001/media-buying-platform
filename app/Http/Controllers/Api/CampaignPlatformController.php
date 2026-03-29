<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\CampaignPlatformUpsertRequest;
use App\Models\CampaignPlatform;
use App\Services\Api\CampaignPlatformApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CampaignPlatformController extends ApiController
{
    public function __construct(private CampaignPlatformApiService $service)
    {
    }

    public function index(Request $request): JsonResponse
    {
        return $this->respondPaginated($this->service->index((int) $request->integer('per_page', 15)));
    }

    public function store(CampaignPlatformUpsertRequest $request): JsonResponse
    {
        $campaignPlatform = $this->service->store($request->validated());

        return $this->respond($campaignPlatform, ['status' => 'created'], 201);
    }

    public function show(CampaignPlatform $campaignPlatform): JsonResponse
    {
        $campaignPlatform->load(['campaign', 'platform', 'connection']);

        return $this->respond($campaignPlatform, ['total' => 1]);
    }

    public function update(CampaignPlatformUpsertRequest $request, CampaignPlatform $campaignPlatform): JsonResponse
    {
        return $this->respond($this->service->update($campaignPlatform, $request->validated()), ['status' => 'updated']);
    }

    public function destroy(CampaignPlatform $campaignPlatform): JsonResponse
    {
        $this->service->delete($campaignPlatform);

        return $this->respond(['id' => $campaignPlatform->id], ['status' => 'deleted']);
    }
}
