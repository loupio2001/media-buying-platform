<?php

namespace App\Services;

use App\Models\Campaign;
use App\Models\Platform;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class CampaignAiCommentaryService
{
    public function buildContext(Campaign $campaign, int $days, ?int $platformId): array
    {
        $days = max(1, min($days, 90));

        $endDate = Carbon::now()->startOfDay();
        $startDate = (clone $endDate)->subDays($days - 1);

        $query = $this->baseSnapshotQuery($campaign->id, $startDate->toDateString(), $endDate->toDateString());

        if ($platformId !== null) {
            $query->where('cp.platform_id', $platformId);
        }

        $totals = $query
            ->selectRaw('COALESCE(SUM(s.spend), 0) as spend')
            ->selectRaw('COALESCE(SUM(s.impressions), 0) as impressions')
            ->selectRaw('COALESCE(SUM(s.reach), 0) as reach')
            ->selectRaw('COALESCE(SUM(s.clicks), 0) as clicks')
            ->selectRaw('COALESCE(SUM(s.link_clicks), 0) as link_clicks')
            ->selectRaw('COALESCE(SUM(s.conversions), 0) as conversions')
            ->selectRaw('COALESCE(SUM(s.leads), 0) as leads')
            ->selectRaw('COALESCE(SUM(s.video_views), 0) as video_views')
            ->selectRaw('COALESCE(SUM(s.video_completions), 0) as video_completions')
            ->selectRaw('COALESCE(SUM(s.engagement), 0) as engagement')
            ->first();

        $impressions = (float) ($totals->impressions ?? 0);
        $clicks = (float) ($totals->clicks ?? 0);
        $spend = (float) ($totals->spend ?? 0);
        $conversions = (float) ($totals->conversions ?? 0);
        $leads = (float) ($totals->leads ?? 0);
        $reach = (float) ($totals->reach ?? 0);
        $videoViews = (float) ($totals->video_views ?? 0);

        $platform = $platformId !== null
            ? Platform::query()->find($platformId)
            : null;

        return [
            'metrics' => [
                'spend' => $spend,
                'impressions' => (int) $impressions,
                'reach' => (int) $reach,
                'clicks' => (int) $clicks,
                'link_clicks' => (int) ($totals->link_clicks ?? 0),
                'conversions' => (int) $conversions,
                'leads' => (int) $leads,
                'video_views' => (int) $videoViews,
                'video_completions' => (int) ($totals->video_completions ?? 0),
                'engagement' => (int) ($totals->engagement ?? 0),
                'ctr' => $impressions > 0 ? round(($clicks / $impressions) * 100, 4) : null,
                'cpm' => $impressions > 0 ? round(($spend / $impressions) * 1000, 4) : null,
                'cpc' => $clicks > 0 ? round($spend / $clicks, 4) : null,
                'cpa' => $conversions > 0 ? round($spend / $conversions, 4) : null,
                'cpl' => $leads > 0 ? round($spend / $leads, 4) : null,
                'vtr' => $impressions > 0 ? round(($videoViews / $impressions) * 100, 4) : null,
                'frequency' => $reach > 0 ? round($impressions / $reach, 4) : null,
            ],
            'campaign_context' => [
                'campaign_name' => $campaign->name,
                'campaign_status' => $campaign->status?->value ?? (string) $campaign->status,
                'campaign_objective' => $campaign->objective?->value ?? (string) $campaign->objective,
                'currency' => $campaign->currency,
                'client_name' => $campaign->client?->name,
                'platform' => $platform?->slug ?? 'all-platforms',
                'platform_name' => $platform?->name ?? 'All platforms',
                'days_window' => $days,
            ],
            'period' => sprintf('%s to %s', $startDate->toDateString(), $endDate->toDateString()),
            'language' => 'fr',
            'tone' => 'analytical',
        ];
    }

    public function updateAiComments(Campaign $campaign, array $payload, int $days, ?int $platformId): Campaign
    {
        $campaign->update([
            'ai_commentary_summary' => $payload['ai_commentary_summary'] ?? null,
            'ai_commentary_highlights' => $payload['ai_commentary_highlights'] ?? null,
            'ai_commentary_concerns' => $payload['ai_commentary_concerns'] ?? null,
            'ai_commentary_suggested_action' => $payload['ai_commentary_suggested_action'] ?? null,
            'ai_commentary_generated_at' => now(),
            'ai_commentary_filters' => [
                'days' => $days,
                'platform_id' => $platformId,
                'generated_at' => now()->toIso8601String(),
            ],
        ]);

        return $campaign->refresh();
    }

    private function baseSnapshotQuery(int $campaignId, string $startDate, string $endDate): Builder
    {
        return DB::table('ad_snapshots as s')
            ->join('ads as a', 'a.id', '=', 's.ad_id')
            ->join('ad_sets as aset', 'aset.id', '=', 'a.ad_set_id')
            ->join('campaign_platforms as cp', 'cp.id', '=', 'aset.campaign_platform_id')
            ->where('cp.campaign_id', $campaignId)
            ->where('s.granularity', 'daily')
            ->whereDate('s.snapshot_date', '>=', $startDate)
            ->whereDate('s.snapshot_date', '<=', $endDate)
            ->where('a.is_tracked', true)
            ->where('aset.is_tracked', true);
    }
}
