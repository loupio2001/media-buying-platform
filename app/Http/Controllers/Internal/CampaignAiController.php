<?php

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Internal\UpdateCampaignAiCommentsRequest;
use App\Models\Campaign;
use App\Services\CampaignAiCommentaryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CampaignAiController extends Controller
{
    public function __construct(private CampaignAiCommentaryService $service)
    {
    }

    public function showAiContext(Request $request, Campaign $campaign): JsonResponse
    {
        $days = max(1, min((int) $request->integer('days', 7), 90));
        $platformId = $request->integer('platform_id');

        return response()->json([
            'data' => $this->service->buildContext($campaign->loadMissing('client'), $days, $platformId > 0 ? $platformId : null),
            'meta' => ['total' => 1],
        ]);
    }

    public function updateAiComments(UpdateCampaignAiCommentsRequest $request, Campaign $campaign): JsonResponse
    {
        $validated = $request->validated();
        $days = (int) $validated['days'];
        $platformId = isset($validated['platform_id']) ? (int) $validated['platform_id'] : null;

        $updatedCampaign = $this->service->updateAiComments($campaign, $validated, $days, $platformId);

        return response()->json([
            'data' => [
                'id' => $updatedCampaign->id,
                'ai_commentary_summary' => $updatedCampaign->ai_commentary_summary,
                'ai_commentary_highlights' => $updatedCampaign->ai_commentary_highlights,
                'ai_commentary_concerns' => $updatedCampaign->ai_commentary_concerns,
                'ai_commentary_suggested_action' => $updatedCampaign->ai_commentary_suggested_action,
                'ai_commentary_generated_at' => $updatedCampaign->ai_commentary_generated_at?->toIso8601String(),
                'ai_commentary_filters' => $updatedCampaign->ai_commentary_filters,
            ],
            'meta' => ['status' => 'updated'],
        ]);
    }
}
