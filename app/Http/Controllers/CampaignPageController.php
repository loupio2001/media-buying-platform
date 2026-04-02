<?php

namespace App\Http\Controllers;

use App\Models\CampaignPlatform;
use App\Models\Campaign;
use App\Services\CampaignAiCommentaryService;
use App\Services\CampaignAiCommentaryRunner;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CampaignPageController extends Controller
{
    private const PERIOD_OPTIONS = [7, 14, 30];

    public function __construct(
        private CampaignAiCommentaryRunner $campaignAiCommentaryRunner,
        private CampaignAiCommentaryService $campaignAiCommentaryService,
    )
    {
    }

    public function __invoke(Request $request, Campaign $campaign): View
    {
        $selectedPeriod = $this->resolveSelectedPeriod($request);
        $selectedPlatformId = $this->resolveSelectedPlatformId($request, $campaign->id);
        $trendRange = $this->resolveTrendRange($request, $selectedPeriod);

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
                ->selectRaw('cp.external_campaign_id')
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
                    'external_campaign_id' => $item->external_campaign_id,
                    'budget' => (float) $item->budget,
                    'budget_type' => $item->budget_type,
                    'is_active' => (bool) $item->is_active,
                    'total_spend' => 0.0,
                    'total_impressions' => 0,
                    'total_clicks' => 0,
                    'calc_ctr' => 0.0,
                ]);
        }

        $dailyTrend = $this->dailyTrend(
            $campaign->id,
            $trendRange['days'],
            $selectedPlatformId,
            $trendRange['startDate'],
            $trendRange['endDate'],
        );
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
            'activeTrendDays' => $trendRange['days'],
            'selectedPlatformId' => $selectedPlatformId,
            'selectedStartDate' => $trendRange['startDateInput'],
            'selectedEndDate' => $trendRange['endDateInput'],
            'periodOptions' => self::PERIOD_OPTIONS,
        ]);
    }

    public function regenerateAiComments(Request $request, Campaign $campaign): RedirectResponse
    {
        $selectedPeriod = $this->resolveSelectedPeriod($request);
        $trendRange = $this->resolveTrendRange($request, $selectedPeriod);
        $days = $trendRange['days'];
        $platformId = $this->resolveSelectedPlatformId($request, $campaign->id);

        try {
            $this->campaignAiCommentaryRunner->runCampaign($campaign->id, $days, $platformId);

            return redirect()
                ->route('web.campaigns.show', [
                    'campaign' => $campaign->id,
                    'days' => $days,
                    'platform_id' => $platformId,
                    'start_date' => $trendRange['startDateInput'] ?: null,
                    'end_date' => $trendRange['endDateInput'] ?: null,
                ])
                ->with('status', 'AI comments updated using current filters.');
        } catch (Throwable $exception) {
            report($exception);

            if ($this->allowLocalFallback() && $this->shouldFallbackToLocalCommentary($exception)) {
                $this->campaignAiCommentaryService->generateLocalFallbackCommentary($campaign, $days, $platformId);

                return redirect()
                    ->route('web.campaigns.show', [
                        'campaign' => $campaign->id,
                        'days' => $days,
                        'platform_id' => $platformId,
                        'start_date' => $trendRange['startDateInput'] ?: null,
                        'end_date' => $trendRange['endDateInput'] ?: null,
                    ])
                    ->with('status', 'AI comments updated in local fallback mode.');
            }

            return redirect()
                ->route('web.campaigns.show', [
                    'campaign' => $campaign->id,
                    'days' => $days,
                    'platform_id' => $platformId,
                    'start_date' => $trendRange['startDateInput'] ?: null,
                    'end_date' => $trendRange['endDateInput'] ?: null,
                ])
                ->withErrors(['ai_comments' => 'Unable to update AI comments right now.']);
        }
    }

    public function exportTrendCsv(Request $request, Campaign $campaign): StreamedResponse
    {
        $selectedPeriod = $this->resolveSelectedPeriod($request);
        $selectedPlatformId = $this->resolveSelectedPlatformId($request, $campaign->id);
        $trendRange = $this->resolveTrendRange($request, $selectedPeriod);
        $trendRows = $this->dailyTrend(
            $campaign->id,
            $trendRange['days'],
            $selectedPlatformId,
            $trendRange['startDate'],
            $trendRange['endDate'],
        );
        $campaignCurrency = strtoupper((string) ($campaign->currency ?: 'MAD'));

        $filename = $selectedPlatformId !== null
            ? sprintf('campaign-%d-platform-%d-trend-%dd.csv', $campaign->id, $selectedPlatformId, $trendRange['days'])
            : sprintf('campaign-%d-trend-%dd.csv', $campaign->id, $trendRange['days']);

        return response()->streamDownload(function () use ($trendRows, $campaignCurrency): void {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }

            fputcsv($handle, ['date', 'spend', 'currency', 'impressions', 'clicks', 'ctr_pct']);

            foreach ($trendRows as $row) {
                fputcsv($handle, [
                    (string) $row->snapshot_date,
                    number_format((float) $row->total_spend, 2, '.', ''),
                    $campaignCurrency,
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

    private function resolveSelectedPlatformId(Request $request, int $campaignId): ?int
    {
        $platformId = (int) $request->integer('platform_id', 0);

        if ($platformId < 1) {
            return null;
        }

        $belongsToCampaign = CampaignPlatform::query()
            ->where('campaign_id', $campaignId)
            ->where('platform_id', $platformId)
            ->exists();

        return $belongsToCampaign ? $platformId : null;
    }

    private function resolveTrendRange(Request $request, int $selectedPeriod): array
    {
        $validated = $request->validate([
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date'],
        ]);

        $startDateInput = (string) ($validated['start_date'] ?? '');
        $endDateInput = (string) ($validated['end_date'] ?? '');

        if ($startDateInput !== '' || $endDateInput !== '') {
            $endDate = $endDateInput !== ''
                ? Carbon::createFromFormat('Y-m-d', $endDateInput)->startOfDay()
                : Carbon::now()->startOfDay();

            $startDate = $startDateInput !== ''
                ? Carbon::createFromFormat('Y-m-d', $startDateInput)->startOfDay()
                : (clone $endDate)->subDays($selectedPeriod - 1);

            $days = (int) $startDate->diffInDays($endDate) + 1;

            return [
                'startDate' => $startDate,
                'endDate' => $endDate,
                'days' => $days,
                'startDateInput' => $startDate->toDateString(),
                'endDateInput' => $endDate->toDateString(),
            ];
        }

        $endDate = Carbon::now()->startOfDay();
        $startDate = (clone $endDate)->subDays($selectedPeriod - 1);

        return [
            'startDate' => $startDate,
            'endDate' => $endDate,
            'days' => $selectedPeriod,
            'startDateInput' => '',
            'endDateInput' => '',
        ];
    }

    private function dailyTrend(
        int $campaignId,
        int $days,
        ?int $platformId = null,
        ?Carbon $startDate = null,
        ?Carbon $endDate = null,
    ): Collection
    {
        $resolvedEndDate = $endDate ? (clone $endDate)->startOfDay() : Carbon::now()->startOfDay();
        $resolvedStartDate = $startDate ? (clone $startDate)->startOfDay() : (clone $resolvedEndDate)->subDays($days - 1);
        $resolvedDays = (int) $resolvedStartDate->diffInDays($resolvedEndDate) + 1;

        try {
            $query = DB::table('ad_snapshots as s')
                ->join('ads as a', 'a.id', '=', 's.ad_id')
                ->join('ad_sets as aset', 'aset.id', '=', 'a.ad_set_id')
                ->join('campaign_platforms as cp', 'cp.id', '=', 'aset.campaign_platform_id')
                ->where('cp.campaign_id', $campaignId)
                ->where('s.granularity', 'daily')
                ->whereDate('s.snapshot_date', '>=', $resolvedStartDate->toDateString())
                ->whereDate('s.snapshot_date', '<=', $resolvedEndDate->toDateString())
                ->groupBy('s.snapshot_date')
                ->orderBy('s.snapshot_date')
                ->selectRaw('s.snapshot_date')
                ->selectRaw('COALESCE(SUM(s.spend), 0) as total_spend')
                ->selectRaw('COALESCE(SUM(s.impressions), 0) as total_impressions')
                ->selectRaw('COALESCE(SUM(s.clicks), 0) as total_clicks')
                ->selectRaw('CASE WHEN COALESCE(SUM(s.impressions), 0) > 0 THEN ROUND(SUM(s.clicks)::numeric / SUM(s.impressions) * 100, 4) ELSE 0 END as calc_ctr');

            if ($platformId !== null) {
                $query->where('cp.platform_id', $platformId);
            }

            $rows = $query->get();

            return $this->fillMissingTrendDays($rows, $resolvedStartDate, $resolvedDays);
        } catch (QueryException) {
            return $this->fillMissingTrendDays(collect(), $resolvedStartDate, $resolvedDays);
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

    private function shouldFallbackToLocalCommentary(Throwable $exception): bool
    {
        $message = $exception->getMessage();

        return str_contains($message, 'WinError 10106')
            || str_contains($message, 'httpx.ConnectError')
            || str_contains($message, 'httpcore.ConnectError');
    }

    private function allowLocalFallback(): bool
    {
        return (bool) config('services.ai_report_commentary.allow_local_fallback', false);
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
