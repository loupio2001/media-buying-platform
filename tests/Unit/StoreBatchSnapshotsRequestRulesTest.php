<?php

namespace Tests\Unit;

use App\Http\Requests\Internal\StoreBatchSnapshotsRequest;
use Tests\TestCase;

class StoreBatchSnapshotsRequestRulesTest extends TestCase
{
    public function test_batch_snapshot_request_includes_metrics_rules(): void
    {
        $rules = (new StoreBatchSnapshotsRequest())->rules();

        $expectedKeys = [
            'snapshots',
            'snapshots.*.ad_id',
            'snapshots.*.snapshot_date',
            'snapshots.*.granularity',
            'snapshots.*.impressions',
            'snapshots.*.clicks',
            'snapshots.*.spend',
            'snapshots.*.ctr',
            'snapshots.*.cpm',
            'snapshots.*.cpc',
            'snapshots.*.conversions',
            'snapshots.*.cpa',
            'snapshots.*.leads',
            'snapshots.*.cpl',
            'snapshots.*.video_views',
            'snapshots.*.vtr',
            'snapshots.*.custom_metrics',
            'snapshots.*.raw_response',
            'snapshots.*.source',
        ];

        foreach ($expectedKeys as $key) {
            $this->assertArrayHasKey($key, $rules, "Missing validation rule for {$key}");
        }
    }
}
