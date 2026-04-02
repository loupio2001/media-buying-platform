<?php

namespace App\Http\Controllers\Web;

use App\Enums\CampaignStatus;
use App\Enums\PacingStrategy;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\StoreCampaignWebRequest;
use App\Http\Requests\Web\UpdateCampaignWebRequest;
use App\Http\Requests\Web\UpdateCampaignStatusWebRequest;
use App\Models\Campaign;
use App\Models\CampaignPlatform;
use App\Models\Client;
use App\Models\Platform;
use App\Models\PlatformConnection;
use App\Services\Api\CampaignApiService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;

class CampaignWebController extends Controller
{
    public function __construct(private CampaignApiService $campaignApiService) {}

    public function create(): View
    {
        $clients = Client::query()->orderBy('name')->get(['id', 'name']);

        return view('campaigns.create', compact('clients'));
    }

    public function edit(Campaign $campaign): View
    {
        $this->unlinkCampaignPlatformsWithoutConnector($campaign);

        $campaign->load(['campaignPlatforms.platform', 'campaignPlatforms.connection']);
        $clients = Client::query()->orderBy('name')->get(['id', 'name']);
        $availablePlatforms = Platform::query()
            ->active()
            ->ordered()
            ->get(['id', 'name']);
        $platformConnections = PlatformConnection::query()
            ->connected()
            ->with('platform:id,name')
            ->orderBy('account_name')
            ->get(['id', 'platform_id', 'account_id', 'account_name']);
        $linkedPlatformIds = $campaign->campaignPlatforms
            ->pluck('platform_id')
            ->map(static fn ($value) => (int) $value)
            ->all();

        return view('campaigns.edit', [
            'campaign' => $campaign,
            'clients' => $clients,
            'availablePlatforms' => $availablePlatforms,
            'platformConnections' => $platformConnections,
            'linkedPlatformIds' => $linkedPlatformIds,
        ]);
    }

    private function unlinkCampaignPlatformsWithoutConnector(Campaign $campaign): void
    {
        CampaignPlatform::query()
            ->where('campaign_id', $campaign->id)
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('platform_connections')
                    ->whereColumn('platform_connections.platform_id', 'campaign_platforms.platform_id');
            })
            ->delete();
    }

    public function store(StoreCampaignWebRequest $request): RedirectResponse
    {
        $payload = $request->validated();
        $payload['status'] = CampaignStatus::Draft->value;
        $payload['pacing_strategy'] = PacingStrategy::Even->value;

        $currency = strtoupper(trim((string) ($payload['currency'] ?? '')));
        if ($currency === '') {
            $currency = (string) (Client::query()->find($payload['client_id'])?->currency ?? 'MAD');
        }
        $payload['currency'] = strtoupper(trim($currency));

        $campaign = $this->campaignApiService->store($payload, (int) $request->user()->id);

        return redirect()
            ->route('web.campaigns.show', $campaign)
            ->with('status', 'Campaign created successfully.');
    }

    public function updateStatus(UpdateCampaignStatusWebRequest $request, Campaign $campaign): RedirectResponse
    {
        $this->campaignApiService->update($campaign, [
            'status' => (string) $request->validated('status'),
        ]);

        return redirect()
            ->route('web.campaigns.show', $campaign)
            ->with('status', 'Campaign status updated successfully.');
    }

    public function update(UpdateCampaignWebRequest $request, Campaign $campaign): RedirectResponse
    {
        $payload = $request->validated();

        $currency = strtoupper(trim((string) ($payload['currency'] ?? '')));
        if ($currency === '') {
            $currency = (string) (Client::query()->find($payload['client_id'])?->currency ?? 'MAD');
        }
        $payload['currency'] = strtoupper(trim($currency));

        $this->campaignApiService->update($campaign, $payload);

        return redirect()
            ->route('web.campaigns.edit', $campaign)
            ->with('status', 'Campaign updated successfully.');
    }

    public function destroy(Campaign $campaign): RedirectResponse
    {
        try {
            $this->campaignApiService->delete($campaign);

            return redirect()
                ->route('web.campaigns.index')
                ->with('status', 'Campaign removed successfully.');
        } catch (QueryException) {
            return redirect()
                ->route('web.campaigns.show', $campaign)
                ->with('error', 'Campaign cannot be deleted because related records still exist.');
        }
    }
}
