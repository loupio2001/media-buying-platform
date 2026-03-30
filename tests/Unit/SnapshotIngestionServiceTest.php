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
use App\Events\SnapshotCreated;
use App\Services\SnapshotIngestionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
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

    public function test_upsert_batch_dispatches_snapshot_created_once_per_unique_campaign_platform_id(): void
    {
        Event::fake([SnapshotCreated::class]);

        $campaignPlatform = $this->createCampaignPlatform();
        $firstAd = $this->createAd($campaignPlatform);
        $secondAd = $this->createAd($campaignPlatform);
        $otherCampaignPlatform = $this->createCampaignPlatform();
        $thirdAd = $this->createAd($otherCampaignPlatform);
        $service = app(SnapshotIngestionService::class);

        $result = $service->upsertBatch([
            [
                'ad_id' => $firstAd->id,
                'snapshot_date' => '2026-03-30',
                'granularity' => 'daily',
                'impressions' => 100,
                'clicks' => 5,
                'spend' => 10.00,
                'source' => 'api',
            ],
            [
                'ad_id' => $secondAd->id,
                'snapshot_date' => '2026-03-30',
                'granularity' => 'daily',
                'impressions' => 150,
                'clicks' => 6,
                'spend' => 15.00,
                'source' => 'api',
            ],
            [
                'ad_id' => $thirdAd->id,
                'snapshot_date' => '2026-03-30',
                'granularity' => 'daily',
                'impressions' => 200,
                'clicks' => 8,
                'spend' => 18.00,
                'source' => 'api',
            ],
            [
                'ad_id' => $thirdAd->id,
                'snapshot_date' => '2026-03-30',
                'granularity' => 'cumulative',
                'impressions' => 500,
                'clicks' => 12,
                'spend' => 40.00,
                'source' => 'api',
            ],
        ]);

        $this->assertSame(
            [$campaignPlatform->id, $otherCampaignPlatform->id],
            $result['campaign_platform_ids']->all()
        );

        $events = Event::dispatched(SnapshotCreated::class);

        $this->assertCount(2, $events);
        $this->assertSame(
            [$campaignPlatform->id, $otherCampaignPlatform->id],
            $events->map(fn (array $event): int => $event[0]->campaignPlatformId)->all()
        );
    }

    private function createAd(?CampaignPlatform $campaignPlatform = null): Ad
    {
        $campaignPlatform ??= $this->createCampaignPlatform();

        return Ad::create([
            'ad_set_id' => AdSet::create([
                'campaign_platform_id' => $campaignPlatform->id,
                'external_id' => fake()->unique()->slug(),
                'name' => 'Timezone Ad Set',
                'status' => 'active',
            ])->id,
            'external_id' => fake()->unique()->slug(),
            'name' => 'Timezone Ad',
            'status' => 'active',
        ]);
    }

    private function createCampaignPlatform(): CampaignPlatform
    {
        $user = User::factory()->create(['role' => 'admin']);
        $category = Category::firstOrCreate(['slug' => 'fmcg'], ['name' => 'FMCG']);
        $client = Client::factory()->create(['category_id' => $category->id]);
        $campaign = Campaign::factory()->create([
            'client_id' => $client->id,
            'created_by' => $user->id,
        ]);
        $platform = Platform::firstOrCreate(['slug' => 'meta'], ['name' => 'Meta', 'api_supported' => true]);
        return CampaignPlatform::create([
            'campaign_id' => $campaign->id,
            'platform_id' => $platform->id,
            'budget' => 5000.00,
            'budget_type' => 'lifetime',
            'currency' => 'MAD',
        ]);
    }
}
