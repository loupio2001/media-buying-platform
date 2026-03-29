<?php

namespace App\Events;

use App\Models\AdSnapshot;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SnapshotCreated
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public AdSnapshot $snapshot,
        public int $campaignPlatformId,
    ) {
    }
}
