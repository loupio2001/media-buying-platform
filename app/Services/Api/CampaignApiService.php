<?php

namespace App\Services\Api;

use App\Models\Campaign;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CampaignApiService
{
    public function index(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        return Campaign::query()
            ->with(['client', 'campaignPlatforms.platform'])
            ->when(isset($filters['status']), fn ($q) => $q->where('status', $filters['status']))
            ->when(isset($filters['client_id']), fn ($q) => $q->where('client_id', $filters['client_id']))
            ->paginate($perPage);
    }

    public function store(array $data, int $userId): Campaign
    {
        $data['created_by'] = $userId;

        return Campaign::create($data);
    }

    public function update(Campaign $campaign, array $data): Campaign
    {
        $campaign->update($data);

        return $campaign->refresh();
    }

    public function delete(Campaign $campaign): void
    {
        $campaign->delete();
    }

    public function dashboard(int $campaignId): Collection
    {
        Campaign::query()->findOrFail($campaignId);

        return DB::table('v_campaign_platform_totals')
            ->where('campaign_id', $campaignId)
            ->get();
    }

    public function adSets(int $campaignId, ?int $platformId = null): Collection
    {
        Campaign::query()->findOrFail($campaignId);

        return DB::table('v_ad_set_totals as v')
            ->join('campaign_platforms as cp', 'cp.id', '=', 'v.campaign_platform_id')
            ->where('cp.campaign_id', $campaignId)
            ->when($platformId, fn ($q) => $q->where('cp.platform_id', $platformId))
            ->select('v.*', 'cp.platform_id')
            ->get();
    }

    public function ads(int $campaignId, array $filters): Collection
    {
        Campaign::query()->findOrFail($campaignId);

        return DB::table('ads as a')
            ->join('ad_sets as aset', 'aset.id', '=', 'a.ad_set_id')
            ->join('campaign_platforms as cp', 'cp.id', '=', 'aset.campaign_platform_id')
            ->join('ad_snapshots as s', function ($join): void {
                $join->on('s.ad_id', '=', 'a.id')
                    ->where('s.granularity', '=', 'daily');
            })
            ->where('cp.campaign_id', $campaignId)
            ->when(isset($filters['platform_id']), fn ($q) => $q->where('cp.platform_id', $filters['platform_id']))
            ->when(isset($filters['ad_set_id']), fn ($q) => $q->where('aset.id', $filters['ad_set_id']))
            ->when(isset($filters['start_date']), fn ($q) => $q->whereDate('s.snapshot_date', '>=', $filters['start_date']))
            ->when(isset($filters['end_date']), fn ($q) => $q->whereDate('s.snapshot_date', '<=', $filters['end_date']))
            ->groupBy('a.id', 'a.name', 'a.status', 'aset.id', 'aset.name', 'cp.platform_id')
            ->selectRaw('
                a.id as ad_id,
                a.name as ad_name,
                a.status,
                aset.id as ad_set_id,
                aset.name as ad_set_name,
                cp.platform_id,
                SUM(s.impressions) as total_impressions,
                SUM(s.reach) as total_reach,
                SUM(s.clicks) as total_clicks,
                SUM(s.spend) as total_spend,
                SUM(s.conversions) as total_conversions,
                SUM(s.leads) as total_leads,
                SUM(s.video_views) as total_video_views,
                SUM(s.engagement) as total_engagement,
                CASE WHEN SUM(s.impressions) > 0 THEN ROUND(SUM(s.clicks)::numeric / SUM(s.impressions) * 100, 4) ELSE 0 END as calc_ctr,
                CASE WHEN SUM(s.impressions) > 0 THEN ROUND(SUM(s.spend) / SUM(s.impressions) * 1000, 4) ELSE 0 END as calc_cpm,
                CASE WHEN SUM(s.clicks) > 0 THEN ROUND(SUM(s.spend) / SUM(s.clicks), 4) ELSE 0 END as calc_cpc,
                CASE WHEN SUM(s.conversions) > 0 THEN ROUND(SUM(s.spend) / SUM(s.conversions), 4) ELSE 0 END as calc_cpa,
                CASE WHEN SUM(s.leads) > 0 THEN ROUND(SUM(s.spend) / SUM(s.leads), 4) ELSE 0 END as calc_cpl,
                CASE WHEN SUM(s.impressions) > 0 AND SUM(s.video_views) > 0 THEN ROUND(SUM(s.video_views)::numeric / SUM(s.impressions) * 100, 4) ELSE NULL END as calc_vtr,
                CASE WHEN SUM(s.reach) > 0 THEN ROUND(SUM(s.impressions)::numeric / SUM(s.reach), 4) ELSE NULL END as calc_frequency
            ')
            ->get();
    }
}
