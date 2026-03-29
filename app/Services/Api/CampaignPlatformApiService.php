<?php

namespace App\Services\Api;

use App\Models\CampaignPlatform;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class CampaignPlatformApiService
{
    public function index(int $perPage = 15): LengthAwarePaginator
    {
        return CampaignPlatform::query()
            ->with(['campaign', 'platform', 'connection'])
            ->paginate($perPage);
    }

    public function store(array $data): CampaignPlatform
    {
        return CampaignPlatform::create($data);
    }

    public function update(CampaignPlatform $campaignPlatform, array $data): CampaignPlatform
    {
        $campaignPlatform->update($data);

        return $campaignPlatform->refresh();
    }

    public function delete(CampaignPlatform $campaignPlatform): void
    {
        $campaignPlatform->delete();
    }
}
