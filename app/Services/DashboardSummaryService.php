<?php

namespace App\Services;

use App\Enums\CampaignStatus;
use App\Models\Campaign;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DashboardSummaryService
{
    public function summary(): array
    {
        $baseQuery = Campaign::query()->forDashboard();

        try {
            $performance = DB::table('v_campaign_platform_totals as v')
                ->join('campaigns as c', 'c.id', '=', 'v.campaign_id')
                ->where('c.status', '!=', CampaignStatus::Archived->value)
                ->selectRaw('COALESCE(SUM(v.total_spend), 0) as total_spend')
                ->selectRaw('COALESCE(SUM(v.total_clicks), 0) as total_clicks')
                ->selectRaw('COALESCE(SUM(v.total_impressions), 0) as total_impressions')
                ->first();
        } catch (QueryException) {
            $performance = (object) [
                'total_spend' => 0,
                'total_clicks' => 0,
                'total_impressions' => 0,
            ];
        }

        $totalClicks = (float) ($performance->total_clicks ?? 0);
        $totalImpressions = (float) ($performance->total_impressions ?? 0);
        $globalCtr = $totalImpressions > 0
            ? round($totalClicks / $totalImpressions * 100, 4)
            : 0.0;

        return [
            'total_campaigns' => (clone $baseQuery)->count(),
            'active_campaigns' => (clone $baseQuery)->active()->count(),
            'running_campaigns' => (clone $baseQuery)->running()->count(),
            'total_budget' => (float) (clone $baseQuery)->sum('total_budget'),
            'total_spend' => (float) ($performance->total_spend ?? 0),
            'global_ctr' => $globalCtr,
        ];
    }

    public function recentCampaigns(int $limit = 5): Collection
    {
        return Campaign::query()
            ->forDashboard()
            ->with('client:id,name')
            ->latest('created_at')
            ->limit($limit)
            ->get();
    }
}
