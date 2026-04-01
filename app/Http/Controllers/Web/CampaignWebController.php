<?php

namespace App\Http\Controllers\Web;

use App\Enums\CampaignStatus;
use App\Enums\PacingStrategy;
use App\Http\Controllers\Controller;
use App\Http\Requests\Web\StoreCampaignWebRequest;
use App\Http\Requests\Web\UpdateCampaignStatusWebRequest;
use App\Models\Campaign;
use App\Models\Client;
use App\Services\Api\CampaignApiService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class CampaignWebController extends Controller
{
    public function __construct(private CampaignApiService $campaignApiService) {}

    public function create(): View
    {
        $clients = Client::query()->orderBy('name')->get(['id', 'name']);

        return view('campaigns.create', compact('clients'));
    }

    public function store(StoreCampaignWebRequest $request): RedirectResponse
    {
        $payload = $request->validated();
        $payload['status'] = CampaignStatus::Draft->value;
        $payload['pacing_strategy'] = PacingStrategy::Even->value;
        $payload['currency'] = $payload['currency'] ?? 'MAD';

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
}
