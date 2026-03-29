<?php

namespace App\Listeners;

use App\Events\SnapshotCreated;
use App\Models\CampaignPlatform;
use App\Services\BenchmarkChecker;
use App\Services\NotificationService;

class CheckBenchmarks
{
    public function __construct(
        private BenchmarkChecker $checker,
        private NotificationService $notifier,
    ) {
    }

    public function handle(SnapshotCreated $event): void
    {
        $cp = CampaignPlatform::with('campaign.client.category', 'platform')->find($event->campaignPlatformId);
        if (!$cp) {
            return;
        }

        $flags = $this->checker->check($cp);

        foreach ($flags as $flag) {
            $platformName = $cp->platform->name;
            $campaignName = $cp->campaign->name;
            $metric = strtoupper($flag['metric']);

            $this->notifier->notifyAll(
                type: 'performance_flag',
                severity: $flag['severity'],
                title: "{$metric} underperforming on {$platformName}",
                message: "{$campaignName}: {$metric} is at {$flag['value']} " .
                    ($flag['source'] === 'benchmark'
                        ? "(benchmark: {$flag['benchmark_min']}-{$flag['benchmark_max']})"
                        : "(target: {$flag['target']})") .
                    " - {$flag['deviation_pct']}% deviation",
                entityType: 'campaigns',
                entityId: $cp->campaign_id,
                meta: array_merge($flag, [
                    'campaign_id' => $cp->campaign_id,
                    'platform_slug' => $cp->platform->slug,
                ]),
                actionUrl: "/campaigns/{$cp->campaign_id}",
                isActionable: $flag['severity'] === 'critical',
            );
        }
    }
}
