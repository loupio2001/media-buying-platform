<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\CampaignUpsertRequest;
use App\Models\Campaign;
use App\Services\Api\CampaignApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CampaignController extends ApiController
{
    public function __construct(private CampaignApiService $service)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['nullable', 'string'],
            'client_id' => ['nullable', 'integer'],
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $filters = array_filter([
            'status' => $validated['status'] ?? null,
            'client_id' => $validated['client_id'] ?? null,
            'start_date' => $validated['start_date'] ?? null,
            'end_date' => $validated['end_date'] ?? null,
        ], fn ($value) => $value !== null && $value !== '');

        $perPage = (int) ($validated['per_page'] ?? 15);

        return $this->respondPaginated($this->service->index($filters, $perPage));
    }

    public function store(CampaignUpsertRequest $request): JsonResponse
    {
        $campaign = $this->service->store($request->validated(), (int) $request->user()->id);

        return $this->respond($campaign, ['status' => 'created'], 201);
    }

    public function show(Campaign $campaign): JsonResponse
    {
        $campaign->load(['client', 'campaignPlatforms.platform', 'brief']);

        return $this->respond($campaign, ['total' => 1]);
    }

    public function update(CampaignUpsertRequest $request, Campaign $campaign): JsonResponse
    {
        return $this->respond($this->service->update($campaign, $request->validated()), ['status' => 'updated']);
    }

    public function destroy(Campaign $campaign): JsonResponse
    {
        $this->service->delete($campaign);

        return $this->respond(['id' => $campaign->id], ['status' => 'deleted']);
    }

    public function dashboard(int $id): JsonResponse
    {
        return $this->respondCollection($this->service->dashboard($id));
    }

    public function ads(int $id, Request $request): JsonResponse
    {
        $filters = $request->only(['platform_id', 'ad_set_id', 'start_date', 'end_date']);

        return $this->respondCollection($this->service->ads($id, $filters));
    }

    public function adSets(int $id, Request $request): JsonResponse
    {
        $platformId = $request->integer('platform_id') ?: null;

        return $this->respondCollection($this->service->adSets($id, $platformId));
    }
}
