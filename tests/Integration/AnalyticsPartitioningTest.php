<?php

namespace Tests\Integration;

use App\Models\User;
use App\Models\Platform;
use App\Models\Client;
use App\Models\Campaign;
use App\Models\CampaignPlatform;
use App\Models\AdSet;
use App\Models\Ad;
use App\Models\AdSnapshot;
use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalyticsPartitioningTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $campaign;
    protected $campaignPlatform;
    protected $adSet;
    protected $ad;

    protected function setUp(): void
    {
        parent::setUp();

        if (config('database.default') !== 'pgsql') {
            $this->markTestSkipped('Integration analytics tests require PostgreSQL.');
        }
        $this->user = User::factory()->create(['role' => 'admin']);
        $category = Category::firstOrCreate(['slug' => 'fmcg'], ['name' => 'FMCG']);
        $client = Client::factory()->create(['category_id' => $category->id]);
        $this->campaign = Campaign::factory()->create(['client_id' => $client->id, 'created_by' => $this->user->id]);
        $platform = Platform::firstOrCreate(['slug' => 'meta'], ['name' => 'Meta', 'api_supported' => true]);
        $this->campaignPlatform = CampaignPlatform::create([
            'campaign_id' => $this->campaign->id,
            'platform_id' => $platform->id,
            'budget' => 5000.00,
            'budget_type' => 'lifetime',
        ]);
        $this->adSet = AdSet::create([
            'campaign_platform_id' => $this->campaignPlatform->id,
            'external_id' => 'adset_001',
            'name' => 'Test Ad Set',
        ]);
        $this->ad = Ad::create([
            'ad_set_id' => $this->adSet->id,
            'external_id' => 'ad_001',
            'name' => 'Test Ad',
        ]);
    }

    public function test_snapshots_inserted_across_multiple_dates(): void
    {
        $dates = [
            now()->subDays(3)->toDateString(),
            now()->subDays(2)->toDateString(),
            now()->subDays(1)->toDateString(),
            now()->toDateString(),
        ];

        foreach ($dates as $date) {
            AdSnapshot::create([
                'ad_id' => $this->ad->id,
                'snapshot_date' => $date,
                'granularity' => 'daily',
                'impressions' => 1000,
                'clicks' => 50,
                'spend' => 100.00,
                'ctr' => 0,
                'cpm' => 0,
                'cpc' => 0,
                'source' => 'api',
                'pulled_at' => now(),
            ]);
        }

        $count = AdSnapshot::count();
        $this->assertEquals(4, $count);

        $uniqueDates = AdSnapshot::distinct()->pluck('snapshot_date')->count();
        $this->assertEquals(4, $uniqueDates);
    }

    public function test_snapshot_upsert_overwrites_on_same_date(): void
    {
        $snapshotDate = now()->toDateString();

        AdSnapshot::create([
            'ad_id' => $this->ad->id,
            'snapshot_date' => $snapshotDate,
            'granularity' => 'daily',
            'impressions' => 1000,
            'clicks' => 50,
            'spend' => 100.00,
            'ctr' => 0,
            'cpm' => 0,
            'cpc' => 0,
            'source' => 'api',
            'pulled_at' => now(),
        ]);

        $this->assertEquals(1, AdSnapshot::count());

        AdSnapshot::where('ad_id', $this->ad->id)
            ->where('snapshot_date', $snapshotDate)
            ->where('granularity', 'daily')
            ->delete();

        AdSnapshot::create([
            'ad_id' => $this->ad->id,
            'snapshot_date' => $snapshotDate,
            'granularity' => 'daily',
            'impressions' => 2000,
            'clicks' => 100,
            'spend' => 200.00,
            'ctr' => 0,
            'cpm' => 0,
            'cpc' => 0,
            'source' => 'api',
            'pulled_at' => now(),
        ]);

        $this->assertEquals(1, AdSnapshot::count());
        $snap = AdSnapshot::first();
        $this->assertEquals(2000, $snap->impressions);
    }

    public function test_unique_constraint_ad_id_snapshot_date_granularity(): void
    {
        $snapshotDate = now()->toDateString();

        AdSnapshot::create([
            'ad_id' => $this->ad->id,
            'snapshot_date' => $snapshotDate,
            'granularity' => 'daily',
            'impressions' => 1000,
            'clicks' => 50,
            'spend' => 100.00,
            'ctr' => 0,
            'cpm' => 0,
            'cpc' => 0,
            'source' => 'api',
            'pulled_at' => now(),
        ]);

        $this->assertDatabaseCount('ad_snapshots', 1);

        $this->expectException(\Exception::class);

        AdSnapshot::create([
            'ad_id' => $this->ad->id,
            'snapshot_date' => $snapshotDate,
            'granularity' => 'daily',
            'impressions' => 500,
            'clicks' => 25,
            'spend' => 50.00,
            'ctr' => 0,
            'cpm' => 0,
            'cpc' => 0,
            'source' => 'api',
            'pulled_at' => now(),
        ]);
    }

    public function test_daily_and_cumulative_can_coexist(): void
    {
        $snapshotDate = now()->toDateString();

        AdSnapshot::create([
            'ad_id' => $this->ad->id,
            'snapshot_date' => $snapshotDate,
            'granularity' => 'daily',
            'impressions' => 1000,
            'clicks' => 50,
            'spend' => 100.00,
            'ctr' => 0,
            'cpm' => 0,
            'cpc' => 0,
            'source' => 'api',
            'pulled_at' => now(),
        ]);

        AdSnapshot::create([
            'ad_id' => $this->ad->id,
            'snapshot_date' => $snapshotDate,
            'granularity' => 'cumulative',
            'impressions' => 50000,
            'clicks' => 2500,
            'spend' => 5000.00,
            'ctr' => 0,
            'cpm' => 0,
            'cpc' => 0,
            'source' => 'api',
            'pulled_at' => now(),
        ]);

        $this->assertDatabaseCount('ad_snapshots', 2);
    }

    public function test_snapshot_source_field_preserved(): void
    {
        $apiSnapshot = AdSnapshot::create([
            'ad_id' => $this->ad->id,
            'snapshot_date' => now()->toDateString(),
            'granularity' => 'daily',
            'impressions' => 1000,
            'clicks' => 50,
            'spend' => 100.00,
            'ctr' => 0,
            'cpm' => 0,
            'cpc' => 0,
            'source' => 'api',
            'pulled_at' => now(),
        ]);

        $this->assertEquals('api', $apiSnapshot->source);

        $ad2 = Ad::create([
            'ad_set_id' => $this->adSet->id,
            'external_id' => 'ad_002',
            'name' => 'Manual Ad',
        ]);

        $manualSnapshot = AdSnapshot::create([
            'ad_id' => $ad2->id,
            'snapshot_date' => now()->addDay()->toDateString(),
            'granularity' => 'daily',
            'impressions' => 500,
            'clicks' => 25,
            'spend' => 50.00,
            'ctr' => 0,
            'cpm' => 0,
            'cpc' => 0,
            'source' => 'manual',
            'pulled_at' => now(),
        ]);

        $this->assertEquals('manual', $manualSnapshot->source);
    }

    public function test_snapshots_queryable_by_date_range(): void
    {
        $baseDate = now()->startOfMonth();

        for ($i = 0; $i < 10; $i++) {
            AdSnapshot::create([
                'ad_id' => $this->ad->id,
                'snapshot_date' => $baseDate->clone()->addDays($i)->toDateString(),
                'granularity' => 'daily',
                'impressions' => 1000 + ($i * 100),
                'clicks' => 50 + ($i * 5),
                'spend' => 100.00 + ($i * 10),
                'ctr' => 0,
                'cpm' => 0,
                'cpc' => 0,
                'source' => 'api',
                'pulled_at' => now(),
            ]);
        }

        $startDate = $baseDate->clone()->addDays(2)->toDateString();
        $endDate = $baseDate->clone()->addDays(5)->toDateString();

        $snapshots = AdSnapshot::whereBetween('snapshot_date', [$startDate, $endDate])->get();

        $this->assertCount(4, $snapshots);
    }

    public function test_snapshot_raw_response_preserved(): void
    {
        $rawResponse = [
            'meta' => ['request_id' => '12345'],
            'data' => ['impressions' => 1000, 'clicks' => 50],
        ];

        $snapshot = AdSnapshot::create([
            'ad_id' => $this->ad->id,
            'snapshot_date' => now()->toDateString(),
            'granularity' => 'daily',
            'impressions' => 1000,
            'clicks' => 50,
            'spend' => 100.00,
            'ctr' => 0,
            'cpm' => 0,
            'cpc' => 0,
            'raw_response' => $rawResponse,
            'source' => 'api',
            'pulled_at' => now(),
        ]);

        $retrieved = AdSnapshot::find($snapshot->id);
        $this->assertEquals($rawResponse, $retrieved->raw_response);
    }
}
