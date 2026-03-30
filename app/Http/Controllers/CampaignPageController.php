<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CampaignPageController extends Controller
{
    private const PERIOD_OPTIONS = [7, 14, 30];

    public function __invoke(Request $request, Campaign $campaign): View
    {
        $selectedPeriod = $this->resolveSelectedPeriod($request);

        $campaign->load(['client:id,name', 'campaignPlatforms.platform:id,name']);

        try {
            $totals = DB::table('v_campaign_platform_totals')
                ->where('campaign_id', $campaign->id)
                ->selectRaw('COALESCE(SUM(total_spend), 0) as total_spend')
                ->selectRaw('COALESCE(SUM(total_clicks), 0) as total_clicks')
                ->selectRaw('COALESCE(SUM(total_impressions), 0) as total_impressions')
                ->first();
        } catch (QueryException) {
            $totals = (object) [
                'total_spend' => 0,
                'total_clicks' => 0,
                'total_impressions' => 0,
            ];
        }

        $totalImpressions = (float) ($totals->total_impressions ?? 0);
        $ctr = $totalImpressions > 0
            ? round((float) ($totals->total_clicks ?? 0) / $totalImpressions * 100, 4)
            : 0.0;

        try {
            $platformTotals = DB::table('campaign_platforms as cp')
                ->join('platforms as p', 'p.id', '=', 'cp.platform_id')
                ->leftJoin('v_campaign_platform_totals as v', 'v.campaign_platform_id', '=', 'cp.id')
                ->where('cp.campaign_id', $campaign->id)
                ->selectRaw('cp.id as campaign_platform_id')
                ->selectRaw('p.name as platform_name')
                ->selectRaw('cp.budget')
                ->selectRaw('cp.budget_type')
                ->selectRaw('cp.is_active')
                ->selectRaw('COALESCE(v.total_spend, 0) as total_spend')
                ->selectRaw('COALESCE(v.total_impressions, 0) as total_impressions')
                ->selectRaw('COALESCE(v.total_clicks, 0) as total_clicks')
                ->selectRaw('CASE WHEN COALESCE(v.total_impressions, 0) > 0 THEN ROUND(COALESCE(v.total_clicks, 0)::numeric / COALESCE(v.total_impressions, 0) * 100, 4) ELSE 0 END as calc_ctr')
                ->orderBy('p.name')
                ->get();
        } catch (QueryException) {
            $platformTotals = $campaign->campaignPlatforms
                ->map(static fn ($item) => (object) [
                    'campaign_platform_id' => $item->id,
                    'platform_name' => $item->platform?->name ?? '-',
                    'budget' => (float) $item->budget,
                    'budget_type' => $item->budget_type,
                    'is_active' => (bool) $item->is_active,
                    'total_spend' => 0.0,
                    'total_impressions' => 0,
                    'total_clicks' => 0,
                    'calc_ctr' => 0.0,
                ]);
        }

        $dailyTrend = $this->dailyTrend($campaign->id, $selectedPeriod);
        $spendSparkline = $this->buildSparkline($dailyTrend, 'total_spend');
        $clicksSparkline = $this->buildSparkline($dailyTrend, 'total_clicks');

        return view('campaigns.show', [
            'campaign' => $campaign,
            'kpi' => [
                'total_spend' => (float) ($totals->total_spend ?? 0),
                'ctr' => $ctr,
            ],
            'platformTotals' => $platformTotals,
            'dailyTrend' => $dailyTrend,
            'spendSparkline' => $spendSparkline,
            'clicksSparkline' => $clicksSparkline,
            'selectedPeriod' => $selectedPeriod,
            'periodOptions' => self::PERIOD_OPTIONS,
        ]);
    }

    public function exportTrendCsv(Request $request, Campaign $campaign): StreamedResponse
    {
        $selectedPeriod = $this->resolveSelectedPeriod($request);
        $trendRows = $this->dailyTrend($campaign->id, $selectedPeriod);

        $filename = sprintf('campaign-%d-trend-%dd.csv', $campaign->id, $selectedPeriod);

        return response()->streamDownload(function () use ($trendRows): void {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }

            fputcsv($handle, ['date', 'spend_mad', 'impressions', 'clicks', 'ctr_pct']);

            foreach ($trendRows as $row) {
                fputcsv($handle, [
                    (string) $row->snapshot_date,
                    number_format((float) $row->total_spend, 2, '.', ''),
                    (int) $row->total_impressions,
                    (int) $row->total_clicks,
                    number_format((float) $row->calc_ctr, 4, '.', ''),
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function resolveSelectedPeriod(Request $request): int
    {
        $selectedPeriod = (int) $request->integer('days', 7);

        if (!in_array($selectedPeriod, self::PERIOD_OPTIONS, true)) {
            return 7;
        }

        return $selectedPeriod;
    }

    private function dailyTrend(int $campaignId, int $days): Collection
    {
        $endDate = Carbon::now()->startOfDay();
        $startDate = (clone $endDate)->subDays($days - 1);

        try {
            $rows = DB::table('ad_snapshots as s')
                ->join('ads as a', 'a.id', '=', 's.ad_id')
                ->join('ad_sets as aset', 'aset.id', '=', 'a.ad_set_id')
                ->join('campaign_platforms as cp', 'cp.id', '=', 'aset.campaign_platform_id')
                ->where('cp.campaign_id', $campaignId)
                ->where('s.granularity', 'daily')
                ->whereDate('s.snapshot_date', '>=', $startDate->toDateString())
                ->whereDate('s.snapshot_date', '<=', $endDate->toDateString())
                ->groupBy('s.snapshot_date')
                ->orderBy('s.snapshot_date')
                ->selectRaw('s.snapshot_date')
                ->selectRaw('COALESCE(SUM(s.spend), 0) as total_spend')
                ->selectRaw('COALESCE(SUM(s.impressions), 0) as total_impressions')
                ->selectRaw('COALESCE(SUM(s.clicks), 0) as total_clicks')
                ->selectRaw('CASE WHEN COALESCE(SUM(s.impressions), 0) > 0 THEN ROUND(SUM(s.clicks)::numeric / SUM(s.impressions) * 100, 4) ELSE 0 END as calc_ctr')
                ->get();

            return $this->fillMissingTrendDays($rows, $startDate, $days);
        } catch (QueryException) {
            return $this->fillMissingTrendDays(collect(), $startDate, $days);
        }
    }

    private function fillMissingTrendDays(Collection $rows, Carbon $startDate, int $days): Collection
    {
        $rowsByDate = $rows->keyBy(static fn ($row) => (string) $row->snapshot_date);

        return collect(range(0, $days - 1))
            ->map(static function (int $offset) use ($rowsByDate, $startDate) {
                $date = (clone $startDate)->addDays($offset)->toDateString();
                $row = $rowsByDate->get($date);

                if ($row !== null) {
                    return (object) [
                        'snapshot_date' => $date,
                        'total_spend' => (float) ($row->total_spend ?? 0),
                        'total_impressions' => (int) ($row->total_impressions ?? 0),
                        'total_clicks' => (int) ($row->total_clicks ?? 0),
                        'calc_ctr' => (float) ($row->calc_ctr ?? 0),
                    ];
                }

                return (object) [
                    'snapshot_date' => $date,
                    'total_spend' => 0.0,
                    'total_impressions' => 0,
                    'total_clicks' => 0,
                    'calc_ctr' => 0.0,
                ];
            })
            ->values();
    }

    private function buildSparkline(Collection $dailyTrend, string $metricKey): array
    {
        $values = $dailyTrend->pluck($metricKey)->map(static fn ($value) => (float) $value)->values();

        if ($values->isEmpty()) {
            return [
                'linePoints' => '',
                'areaPoints' => '',
                'maxSpend' => 0.0,
                'lastSpend' => 0.0,
                'hasData' => false,
            ];
        }

        $width = 240.0;
        $height = 72.0;
        $max = max($values->all());
        $min = min($values->all());
        $hasData = $max > 0.0;

        if (! $hasData) {
            return [
                'linePoints' => '',
                'areaPoints' => '',
                'maxSpend' => (float) $max,
                'lastSpend' => (float) $values->last(),
                'hasData' => false,
            ];
        }

        $range = max($max - $min, 1.0);
        $count = $values->count();
        $stepX = $count > 1 ? $width / ($count - 1) : 0.0;

        $points = $values->map(static function (float $value, int $index) use ($height, $min, $range, $stepX): array {
            $x = round($index * $stepX, 2);
            $y = round($height - (($value - $min) / $range) * $height, 2);

            return ['x' => $x, 'y' => $y];
        });

        $linePoints = $points
            ->map(static fn (array $point): string => $point['x'] . ',' . $point['y'])
            ->implode(' ');

        $firstX = (string) $points->first()['x'];
        $lastX = (string) $points->last()['x'];
        $areaPoints = $firstX . ',' . $height . ' ' . $linePoints . ' ' . $lastX . ',' . $height;

        return [
            'linePoints' => $linePoints,
            'areaPoints' => $areaPoints,
            'maxSpend' => (float) $max,
            'lastSpend' => (float) $values->last(),
            'hasData' => true,
        ];
    }
}
