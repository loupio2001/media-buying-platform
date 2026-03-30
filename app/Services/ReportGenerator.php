<?php

namespace App\Services;

use App\Models\Campaign;
use App\Models\CampaignPlatform;
use App\Models\Report;
use App\Models\ReportPlatformSection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ReportGenerator
{
    public function generate(Campaign $campaign, string $type, string $periodStart, string $periodEnd, int $createdBy): Report
    {
        $report = Report::create([
            'campaign_id' => $campaign->id,
            'type' => $type,
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'title' => "{$campaign->name} - " . ucfirst($type) . ' Report',
            'status' => 'draft',
            'created_by' => $createdBy,
        ]);

        $platforms = $campaign->campaignPlatforms()->with('platform')->where('is_active', true)->get();

        foreach ($platforms as $cp) {
            $metrics = $this->aggregateMetrics($cp, $periodStart, $periodEnd);
            $benchmarkStatus = $this->evaluateOverallPerformance($cp);

            ReportPlatformSection::create(array_merge($metrics, [
                'report_id' => $report->id,
                'platform_id' => $cp->platform_id,
                'budget' => $cp->budget,
                'performance_vs_benchmark' => $benchmarkStatus,
            ]));
        }

        return $report;
    }

    private function aggregateMetrics(CampaignPlatform $cp, string $start, string $end): array
    {
        if (!Schema::hasTable('ad_snapshots')) {
            return $this->emptyMetrics();
        }

        $defaults = $this->emptyMetrics();

        $result = DB::table('ad_snapshots AS s')
            ->join('ads AS a', 'a.id', '=', 's.ad_id')
            ->join('ad_sets AS aset', 'aset.id', '=', 'a.ad_set_id')
            ->where('aset.campaign_platform_id', $cp->id)
            ->where('s.granularity', 'daily')
            ->whereBetween('s.snapshot_date', [$start, $end])
            ->where('a.is_tracked', true)
            ->where('aset.is_tracked', true)
            ->selectRaw(
                "
                 CAST(SUM(s.spend) AS DECIMAL(14, 4)) AS spend,
                SUM(s.impressions) AS impressions,
                SUM(s.reach) AS reach,
                SUM(s.clicks) AS clicks,
                SUM(s.link_clicks) AS link_clicks,
                SUM(s.conversions) AS conversions,
                SUM(s.leads) AS leads,
                SUM(s.video_views) AS video_views,
                SUM(s.video_completions) AS video_completions,
                SUM(s.engagement) AS engagement,
                CASE WHEN SUM(s.impressions) > 0
                     THEN ROUND(CAST(SUM(s.clicks) AS DECIMAL(14, 4)) / SUM(s.impressions) * 100, 4)
                     ELSE 0 END AS ctr,
                CASE WHEN SUM(s.impressions) > 0
                     THEN ROUND(SUM(s.spend) / SUM(s.impressions) * 1000, 4)
                     ELSE 0 END AS cpm,
                CASE WHEN SUM(s.clicks) > 0
                     THEN ROUND(SUM(s.spend) / SUM(s.clicks), 4)
                     ELSE 0 END AS cpc,
                CASE WHEN SUM(s.conversions) > 0
                     THEN ROUND(SUM(s.spend) / SUM(s.conversions), 4)
                     ELSE NULL END AS cpa,
                CASE WHEN SUM(s.leads) > 0
                     THEN ROUND(SUM(s.spend) / SUM(s.leads), 4)
                     ELSE NULL END AS cpl,
                CASE WHEN SUM(s.impressions) > 0 AND SUM(s.video_views) > 0
                     THEN ROUND(CAST(SUM(s.video_views) AS DECIMAL(14, 4)) / SUM(s.impressions) * 100, 4)
                     ELSE NULL END AS vtr,
                CASE WHEN SUM(s.reach) > 0
                     THEN ROUND(CAST(SUM(s.impressions) AS DECIMAL(14, 4)) / SUM(s.reach), 4)
                     ELSE NULL END AS frequency
                "
            )
            ->first();

        $metrics = array_merge($defaults, (array) ($result ?? []));

        foreach ($defaults as $key => $defaultValue) {
            if ($metrics[$key] === null) {
                $metrics[$key] = $defaultValue;
            }
        }

        return $metrics;
    }

    private function emptyMetrics(): array
    {
        return [
            'spend' => 0,
            'impressions' => 0,
            'reach' => null,
            'clicks' => 0,
            'link_clicks' => null,
            'ctr' => null,
            'cpm' => null,
            'cpc' => null,
            'conversions' => null,
            'cpa' => null,
            'leads' => null,
            'cpl' => null,
            'video_views' => null,
            'video_completions' => null,
            'vtr' => null,
            'frequency' => null,
            'engagement' => null,
        ];
    }

    private function evaluateOverallPerformance(CampaignPlatform $cp): string
    {
        $checker = app(BenchmarkChecker::class);
        $flags = $checker->check($cp);

        $criticalCount = collect($flags)->where('severity', 'critical')->count();
        $warningCount = collect($flags)->where('severity', 'warning')->count();

        if ($criticalCount > 0) {
            return 'below';
        }
        if ($warningCount > 2) {
            return 'below';
        }
        if ($warningCount === 0) {
            return 'above';
        }

        return 'within';
    }
}
