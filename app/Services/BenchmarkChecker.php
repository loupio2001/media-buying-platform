<?php

namespace App\Services;

use App\Models\CampaignPlatform;
use App\Models\CategoryBenchmark;
use Illuminate\Support\Facades\DB;

class BenchmarkChecker
{
    private const METRIC_DIRECTION = [
        'ctr' => false,
        'vtr' => false,
        'cpm' => true,
        'cpc' => true,
        'cpa' => true,
        'cpl' => true,
        'frequency' => true,
    ];

    public function check(CampaignPlatform $cp): array
    {
        $flags = [];

        $totals = DB::table('v_campaign_platform_totals')->where('campaign_platform_id', $cp->id)->first();
        if (!$totals) {
            return [];
        }

        $campaign = $cp->campaign()->with('client.category')->first();
        $categoryId = $campaign->client->category_id;
        $kpiTargets = $campaign->kpi_targets ?? [];

        $benchmarks = CategoryBenchmark::where('category_id', $categoryId)
            ->where('platform_id', $cp->platform_id)
            ->get()
            ->keyBy('metric');

        $metricMapping = [
            'ctr' => 'calc_ctr',
            'cpm' => 'calc_cpm',
            'cpc' => 'calc_cpc',
            'cpa' => 'calc_cpa',
            'cpl' => 'calc_cpl',
            'vtr' => 'calc_vtr',
            'frequency' => 'calc_frequency',
        ];

        foreach ($metricMapping as $metric => $viewColumn) {
            $currentValue = $totals->{$viewColumn} ?? null;
            if ($currentValue === null) {
                continue;
            }

            $lowerIsBetter = self::METRIC_DIRECTION[$metric] ?? false;
            $benchmark = $benchmarks->get($metric);
            $target = $kpiTargets[$metric] ?? null;

            if ($benchmark) {
                $flag = $this->evaluateBenchmark($metric, (float) $currentValue, $benchmark, $lowerIsBetter);
                if ($flag) {
                    $flags[] = $flag;
                }
            }

            if ($target && isset($target['target'])) {
                $flag = $this->evaluateTarget($metric, (float) $currentValue, (float) $target['target'], $lowerIsBetter);
                if ($flag) {
                    $flags[] = $flag;
                }
            }
        }

        return $flags;
    }

    private function evaluateBenchmark(string $metric, float $value, CategoryBenchmark $benchmark, bool $lowerIsBetter): ?array
    {
        $status = $benchmark->evaluate($value);
        if ($status === 'within') {
            return null;
        }

        $isUnderperforming = ($status === 'below' && !$lowerIsBetter)
            || ($status === 'above' && $lowerIsBetter);

        if (!$isUnderperforming) {
            return null;
        }

        $deviation = $benchmark->deviationPct($value);
        $severity = abs($deviation) > 30 ? 'critical' : 'warning';

        return [
            'source' => 'benchmark',
            'metric' => $metric,
            'value' => $value,
            'benchmark_min' => (float) $benchmark->min_value,
            'benchmark_max' => (float) $benchmark->max_value,
            'status' => 'underperforming',
            'severity' => $severity,
            'deviation_pct' => $deviation,
        ];
    }

    private function evaluateTarget(string $metric, float $value, float $target, bool $lowerIsBetter): ?array
    {
        $isUnderperforming = $lowerIsBetter ? ($value > $target) : ($value < $target);
        if (!$isUnderperforming) {
            return null;
        }

        $deviation = round(($value - $target) / $target * 100, 2);
        $severity = abs($deviation) > 30 ? 'critical' : 'warning';

        return [
            'source' => 'kpi_target',
            'metric' => $metric,
            'value' => $value,
            'target' => $target,
            'status' => 'underperforming',
            'severity' => $severity,
            'deviation_pct' => $deviation,
        ];
    }
}
