<?php

namespace App\Listeners;

use App\Events\SnapshotCreated;
use App\Models\CampaignPlatform;

class UpdateSyncTimestamp
{
    public function handle(SnapshotCreated $event): void
    {
        CampaignPlatform::where('id', $event->campaignPlatformId)->update(['last_sync_at' => now()]);
    }
}
