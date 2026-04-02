<?php

namespace App\Listeners;

use App\Events\SnapshotCreated;
use App\Models\CampaignPlatform;
use App\Services\NotificationService;
use App\Services\PacingChecker;

class CheckPacing
{
    public function __construct(
        private PacingChecker $checker,
        private NotificationService $notifier,
    ) {
    }

    public function handle(SnapshotCreated $event): void
    {
        $cp = CampaignPlatform::with('campaign', 'platform')->find($event->campaignPlatformId);
        if (!$cp) {
            return;
        }

        $flag = $this->checker->check($cp);
        if (!$flag) {
            return;
        }

        $platformName = $cp->platform->name;
        $campaignName = $cp->campaign->name;
        $currency = strtoupper((string) ($cp->currency ?: $cp->campaign?->currency ?: 'MAD'));

        $this->notifier->notifyAll(
            type: 'budget_warning',
            severity: $flag['severity'],
            title: "Budget pacing alert: {$platformName}",
            message: "{$campaignName}: {$flag['budget_pct_used']}% budget used with {$flag['days_remaining']} days remaining. Projected overspend: {$flag['projected_overspend']} {$currency}",
            entityType: 'campaigns',
            entityId: $cp->campaign_id,
            meta: $flag,
            actionUrl: "/campaigns/{$cp->campaign_id}",
            isActionable: true,
        );
    }
}
