<?php

namespace App\Services;

use App\Models\CampaignPlatform;
use Illuminate\Support\Facades\DB;

class PacingChecker
{
    public function check(CampaignPlatform $cp): ?array
    {
        $totals = DB::table('v_campaign_platform_totals')->where('campaign_platform_id', $cp->id)->first();
        if (!$totals || $totals->budget <= 0) {
            return null;
        }

        $campaign = $cp->campaign;
        $totalDays = max(1, $campaign->totalDays());
        $daysElapsed = max(1, $campaign->daysElapsed());
        $daysRemaining = $campaign->daysRemaining();

        $spentPct = (float) $totals->budget_pct_used;
        $timePct = round($daysElapsed / $totalDays * 100, 2);

        $expectedPct = match ($campaign->pacing_strategy->value) {
            'even' => $timePct,
            'front_loaded' => min(100, $timePct * 1.4),
            'back_loaded' => max(0, $timePct * 0.6),
            'custom' => $timePct,
        };

        $deviation = $spentPct - $expectedPct;
        if ($deviation <= 15) {
            return null;
        }

        $projectedOverspend = round((($totals->total_spend / $daysElapsed) * $totalDays) - $totals->budget, 2);

        return [
            'campaign_platform_id' => $cp->id,
            'campaign_id' => $campaign->id,
            'platform_id' => $cp->platform_id,
            'budget' => (float) $totals->budget,
            'spent' => (float) $totals->total_spend,
            'budget_pct_used' => $spentPct,
            'expected_pct' => $expectedPct,
            'deviation' => round($deviation, 2),
            'days_elapsed' => $daysElapsed,
            'days_remaining' => $daysRemaining,
            'projected_overspend' => max(0, $projectedOverspend),
            'pacing_strategy' => $campaign->pacing_strategy->value,
            'severity' => $deviation > 30 ? 'critical' : 'warning',
        ];
    }
}
