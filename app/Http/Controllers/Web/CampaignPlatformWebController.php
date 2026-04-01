<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\StoreCampaignPlatformWebRequest;
use App\Models\Campaign;
use App\Services\Api\CampaignPlatformApiService;
use Illuminate\Http\RedirectResponse;

class CampaignPlatformWebController extends Controller
{
    public function __construct(private CampaignPlatformApiService $campaignPlatformApiService) {}

    public function store(StoreCampaignPlatformWebRequest $request, Campaign $campaign): RedirectResponse
    {
        $payload = $request->validated();
        $payload['campaign_id'] = $campaign->id;
        $payload['currency'] = $payload['currency'] ?? 'MAD';
        $payload['is_active'] = $request->boolean('is_active', true);

        $this->campaignPlatformApiService->store($payload);

        return redirect()
            ->route('web.campaigns.show', $campaign)
            ->with('status', 'Platform linked to campaign successfully.');
    }
}
