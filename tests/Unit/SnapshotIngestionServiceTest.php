<?php

namespace Tests\Unit;

use App\Models\Ad;
use App\Models\AdSet;
use App\Models\Campaign;
use App\Models\CampaignPlatform;
use App\Models\Category;
use App\Models\Client;
use App\Models\Platform;
use App\Models\User;
use App\Services\SnapshotIngestionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SnapshotIngestionServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (config('database.default') !== 'pgsql') {
            $this->markTestSkipped('Snapshot ingestion timezone tests require PostgreSQL.');
        }
    }

    public function test_upsert_snapshot_normalizes_snapshot_date_to_app_timezone(): void
    {
        config(['app.timezone' => 'Africa/Casablanca']);

        $ad = $this->createAd();
        $service = app(SnapshotIngestionService::class);

        $result = $service->upsertSnapshot([
            'ad_id' => $ad->id,
            'snapshot_date' => '2026-03-29T23:30:00+00:00',
            'granularity' => 'daily',
            'impressions' => 1000,
            'clicks' => 50,
            'spend' => 100.00,
            'source' => 'api',
        ]);

        $this->assertSame('2026-03-30', $result['snapshot']->snapshot_date->toDateString());
        $this->assertDatabaseHas('ad_snapshots', [
            'ad_id' => $ad->id,
            'snapshot_date' => '2026-03-30',
            'granularity' => 'daily',
        ]);
    }

    public function test_upsert_batch_normalizes_each_snapshot_date_to_app_timezone(): void
    {
        config(['app.timezone' => 'Africa/Casablanca']);

        $ad = $this->createAd();
        $service = app(SnapshotIngestionService::class);

        $service->upsertBatch([
            [
                'ad_id' => $ad->id,
                'snapshot_date' => '2026-03-29T23:30:00+00:00',
                'granularity' => 'daily',
                'impressions' => 1000,
                'clicks' => 10,
                'spend' => 25.00,
                'source' => 'api',
            ],
            [
                'ad_id' => $ad->id,
                'snapshot_date' => '2026-03-30T00:10:00+00:00',
                'granularity' => 'cumulative',
                'impressions' => 5000,
                'clicks' => 90,
                'spend' => 120.00,
                'source' => 'api',
            ],
        ]);

        $this->assertDatabaseHas('ad_snapshots', [
            'ad_id' => $ad->id,
            'snapshot_date' => '2026-03-30',
            'granularity' => 'daily',
        ]);
        $this->assertDatabaseHas('ad_snapshots', [
            'ad_id' => $ad->id,
            'snapshot_date' => '2026-03-30',
            'granularity' => 'cumulative',
        ]);
    }

    private function createAd(): Ad
    {
        $user = User::factory()->create(['role' => 'admin']);
        $category = Category::firstOrCreate(['slug' => 'fmcg'], ['name' => 'FMCG']);
        $client = Client::factory()->create(['category_id' => $category->id]);
        $campaign = Campaign::factory()->create([
            'client_id' => $client->id,
            'created_by' => $user->id,
        ]);
        $platform = Platform::firstOrCreate(['slug' => 'meta'], ['name' => 'Meta', 'api_supported' => true]);
        $campaignPlatform = CampaignPlatform::create([
            'campaign_id' => $campaign->id,
            'platform_id' => $platform->id,
            'budget' => 5000.00,
            'budget_type' => 'lifetime',
            'currency' => 'MAD',
        ]);
        $adSet = AdSet::create([
            'campaign_platform_id' => $campaignPlatform->id,
            'external_id' => 'adset_tz',
            'name' => 'Timezone Ad Set',
            'status' => 'active',
        ]);

        return Ad::create([
            'ad_set_id' => $adSet->id,
            'external_id' => 'ad_tz',
            'name' => 'Timezone Ad',
            'status' => 'active',
        ]);
    }
}
