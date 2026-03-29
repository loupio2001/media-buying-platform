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
use Illuminate\Support\Facades\DB;

class AnalyticsViewAggregationTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $category;
    protected $client;
    protected $campaign;
    protected $platform;
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
        $this->category = Category::firstOrCreate(['slug' => 'fmcg'], ['name' => 'FMCG']);
        $this->client = Client::factory()->create(['category_id' => $this->category->id]);
        $this->campaign = Campaign::factory()->create(['client_id' => $this->client->id, 'created_by' => $this->user->id]);
        $this->platform = Platform::firstOrCreate(['slug' => 'meta'], ['name' => 'Meta', 'api_supported' => true]);
        $this->campaignPlatform = CampaignPlatform::create([
            'campaign_id' => $this->campaign->id,
            'platform_id' => $this->platform->id,
            'budget' => 1000.00,
            'budget_type' => 'lifetime',
            'currency' => 'MAD',
        ]);
        $this->adSet = AdSet::create([
            'campaign_platform_id' => $this->campaignPlatform->id,
            'external_id' => 'adset_001',
            'name' => 'Test Ad Set',
            'status' => 'active',
        ]);
        $this->ad = Ad::create([
            'ad_set_id' => $this->adSet->id,
            'external_id' => 'ad_001',
            'name' => 'Test Ad',
            'status' => 'active',
        ]);
    }

    public function test_v_ad_set_totals_calculates_ctr_from_sums_not_average(): void
    {
        $snapshots = [
            [
                'impressions' => 1000,
                'clicks' => 15,
                'spend' => 50.00,
                'granularity' => 'daily',
            ],
            [
                'impressions' => 2000,
                'clicks' => 30,
                'spend' => 100.00,
                'granularity' => 'daily',
            ],
        ];

        foreach ($snapshots as $index => $data) {
            AdSnapshot::create([
                'ad_id' => $this->ad->id,
            'snapshot_date' => now()->addDays($index)->toDateString(),
                'granularity' => $data['granularity'],
                'impressions' => $data['impressions'],
                'clicks' => $data['clicks'],
                'spend' => $data['spend'],
                'ctr' => 0,
                'cpm' => 0,
                'cpc' => 0,
                'source' => 'api',
                'pulled_at' => now(),
            ]);
        }

        $result = DB::table('v_ad_set_totals')->where('ad_set_id', $this->adSet->id)->first();

        $this->assertNotNull($result);

        $expectedCTR = (45 / 3000) * 100;
        $this->assertEqualsWithDelta($expectedCTR, $result->calc_ctr, 0.01);

        $this->assertNotEqualsWithDelta(0.825, $result->calc_ctr, 0.01);
    }

    public function test_v_ad_set_totals_calculates_cpm_from_sums(): void
    {
        AdSnapshot::create([
            'ad_id' => $this->ad->id,
            'snapshot_date' => now()->toDateString(),
            'granularity' => 'daily',
            'impressions' => 5000,
            'clicks' => 100,
            'spend' => 75.00,
            'ctr' => 0,
            'cpm' => 0,
            'cpc' => 0,
            'source' => 'api',
            'pulled_at' => now(),
        ]);

        $result = DB::table('v_ad_set_totals')->where('ad_set_id', $this->adSet->id)->first();

        $this->assertNotNull($result);

        $expectedCPM = (75.00 / 5000) * 1000;
        $this->assertEqualsWithDelta($expectedCPM, $result->calc_cpm, 0.01);
    }

    public function test_v_ad_set_totals_calculates_cpc_from_sums(): void
    {
        AdSnapshot::create([
            'ad_id' => $this->ad->id,
            'snapshot_date' => now()->toDateString(),
            'granularity' => 'daily',
            'impressions' => 2000,
            'clicks' => 50,
            'spend' => 100.00,
            'ctr' => 0,
            'cpm' => 0,
            'cpc' => 0,
            'source' => 'api',
            'pulled_at' => now(),
        ]);

        $result = DB::table('v_ad_set_totals')->where('ad_set_id', $this->adSet->id)->first();

        $this->assertNotNull($result);

        $expectedCPC = 100.00 / 50;
        $this->assertEqualsWithDelta($expectedCPC, $result->calc_cpc, 0.01);
    }

    public function test_v_ad_set_totals_calculates_cpa_from_sums(): void
    {
        AdSnapshot::create([
            'ad_id' => $this->ad->id,
            'snapshot_date' => now()->toDateString(),
            'granularity' => 'daily',
            'impressions' => 5000,
            'clicks' => 200,
            'conversions' => 20,
            'spend' => 400.00,
            'ctr' => 0,
            'cpm' => 0,
            'cpc' => 0,
            'cpa' => 0,
            'source' => 'api',
            'pulled_at' => now(),
        ]);

        $result = DB::table('v_ad_set_totals')->where('ad_set_id', $this->adSet->id)->first();

        $this->assertNotNull($result);

        $expectedCPA = 400.00 / 20;
        $this->assertEqualsWithDelta($expectedCPA, $result->calc_cpa, 0.01);
    }

    public function test_v_ad_set_totals_calculates_vtr_from_sums(): void
    {
        AdSnapshot::create([
            'ad_id' => $this->ad->id,
            'snapshot_date' => now()->toDateString(),
            'granularity' => 'daily',
            'impressions' => 10000,
            'clicks' => 200,
            'video_views' => 3500,
            'spend' => 500.00,
            'ctr' => 0,
            'cpm' => 0,
            'cpc' => 0,
            'cpa' => 0,
            'source' => 'api',
            'pulled_at' => now(),
        ]);

        $result = DB::table('v_ad_set_totals')->where('ad_set_id', $this->adSet->id)->first();

        $this->assertNotNull($result);

        $expectedVTR = (3500 / 10000) * 100;
        $this->assertEqualsWithDelta($expectedVTR, $result->calc_vtr, 0.01);
    }

    public function test_v_ad_set_totals_calculates_cpl_from_sums(): void
    {
        AdSnapshot::create([
            'ad_id' => $this->ad->id,
            'snapshot_date' => now()->toDateString(),
            'granularity' => 'daily',
            'impressions' => 4000,
            'clicks' => 100,
            'leads' => 25,
            'spend' => 500.00,
            'ctr' => 0,
            'cpm' => 0,
            'cpc' => 0,
            'cpl' => 0,
            'source' => 'api',
            'pulled_at' => now(),
        ]);

        $result = DB::table('v_ad_set_totals')->where('ad_set_id', $this->adSet->id)->first();

        $this->assertNotNull($result);
        $this->assertEquals(25, $result->total_leads);

        $expectedCPL = 500.00 / 25;
        $this->assertEqualsWithDelta($expectedCPL, $result->calc_cpl, 0.01);
    }

    public function test_v_ad_set_totals_calculates_frequency_from_sums(): void
    {
        AdSnapshot::create([
            'ad_id' => $this->ad->id,
            'snapshot_date' => now()->toDateString(),
            'granularity' => 'daily',
            'impressions' => 12000,
            'reach' => 3000,
            'clicks' => 150,
            'spend' => 300.00,
            'ctr' => 0,
            'cpm' => 0,
            'cpc' => 0,
            'source' => 'api',
            'pulled_at' => now(),
        ]);

        $result = DB::table('v_ad_set_totals')->where('ad_set_id', $this->adSet->id)->first();

        $this->assertNotNull($result);

        $expectedFrequency = 12000 / 3000;
        $this->assertEqualsWithDelta($expectedFrequency, $result->calc_frequency, 0.01);
    }

    public function test_v_ad_set_totals_aggregates_multiple_ads(): void
    {
        $ad2 = Ad::create([
            'ad_set_id' => $this->adSet->id,
            'external_id' => 'ad_002',
            'name' => 'Test Ad 2',
            'status' => 'active',
        ]);

        AdSnapshot::create([
            'ad_id' => $this->ad->id,
            'snapshot_date' => now()->toDateString(),
            'granularity' => 'daily',
            'impressions' => 1000,
            'clicks' => 10,
            'spend' => 50.00,
            'ctr' => 0,
            'cpm' => 0,
            'cpc' => 0,
            'source' => 'api',
            'pulled_at' => now(),
        ]);

        AdSnapshot::create([
            'ad_id' => $ad2->id,
            'snapshot_date' => now()->toDateString(),
            'granularity' => 'daily',
            'impressions' => 1000,
            'clicks' => 20,
            'spend' => 50.00,
            'ctr' => 0,
            'cpm' => 0,
            'cpc' => 0,
            'source' => 'api',
            'pulled_at' => now(),
        ]);

        $result = DB::table('v_ad_set_totals')->where('ad_set_id', $this->adSet->id)->first();

        $this->assertNotNull($result);
        $this->assertEquals(2, $result->ad_count);
        $this->assertEquals(2000, $result->total_impressions);
        $this->assertEquals(30, $result->total_clicks);
        $this->assertEquals(100.00, $result->total_spend);
    }

    public function test_v_ad_set_totals_handles_zero_impressions(): void
    {
        AdSnapshot::create([
            'ad_id' => $this->ad->id,
            'snapshot_date' => now()->toDateString(),
            'granularity' => 'daily',
            'impressions' => 0,
            'clicks' => 0,
            'spend' => 0,
            'ctr' => 0,
            'cpm' => 0,
            'cpc' => 0,
            'source' => 'api',
            'pulled_at' => now(),
        ]);

        $result = DB::table('v_ad_set_totals')->where('ad_set_id', $this->adSet->id)->first();

        $this->assertNotNull($result);
        $this->assertEquals(0, $result->calc_ctr);
        $this->assertEquals(0, $result->calc_cpm);
    }

    public function test_v_ad_set_totals_handles_zero_leads_and_zero_reach(): void
    {
        AdSnapshot::create([
            'ad_id' => $this->ad->id,
            'snapshot_date' => now()->toDateString(),
            'granularity' => 'daily',
            'impressions' => 1500,
            'reach' => 0,
            'clicks' => 20,
            'leads' => 0,
            'spend' => 75.00,
            'ctr' => 0,
            'cpm' => 0,
            'cpc' => 0,
            'cpl' => 0,
            'source' => 'api',
            'pulled_at' => now(),
        ]);

        $result = DB::table('v_ad_set_totals')->where('ad_set_id', $this->adSet->id)->first();

        $this->assertNotNull($result);
        $this->assertEquals(0, $result->calc_cpl);
        $this->assertNull($result->calc_frequency);
    }

    public function test_v_campaign_platform_totals_aggregates_all_ad_sets(): void
    {
        $adSet2 = AdSet::create([
            'campaign_platform_id' => $this->campaignPlatform->id,
            'external_id' => 'adset_002',
            'name' => 'Ad Set 2',
            'status' => 'active',
        ]);

        $ad2 = Ad::create([
            'ad_set_id' => $adSet2->id,
            'external_id' => 'ad_002',
            'name' => 'Ad 2',
            'status' => 'active',
        ]);

        AdSnapshot::create([
            'ad_id' => $this->ad->id,
            'snapshot_date' => now()->toDateString(),
            'granularity' => 'daily',
            'impressions' => 5000,
            'clicks' => 100,
            'spend' => 250.00,
            'ctr' => 0,
            'cpm' => 0,
            'cpc' => 0,
            'source' => 'api',
            'pulled_at' => now(),
        ]);

        AdSnapshot::create([
            'ad_id' => $ad2->id,
            'snapshot_date' => now()->toDateString(),
            'granularity' => 'daily',
            'impressions' => 3000,
            'clicks' => 60,
            'spend' => 150.00,
            'ctr' => 0,
            'cpm' => 0,
            'cpc' => 0,
            'source' => 'api',
            'pulled_at' => now(),
        ]);

        $result = DB::table('v_campaign_platform_totals')->where('campaign_platform_id', $this->campaignPlatform->id)->first();

        $this->assertNotNull($result);
        $this->assertEquals(2, $result->ad_set_count);
        $this->assertEquals(2, $result->ad_count);
        $this->assertEquals(8000, $result->total_impressions);
        $this->assertEquals(160, $result->total_clicks);
        $this->assertEquals(400.00, $result->total_spend);
    }

    public function test_v_campaign_platform_totals_calculates_budget_utilization(): void
    {
        AdSnapshot::create([
            'ad_id' => $this->ad->id,
            'snapshot_date' => now()->toDateString(),
            'granularity' => 'daily',
            'impressions' => 5000,
            'clicks' => 100,
            'spend' => 500.00,
            'ctr' => 0,
            'cpm' => 0,
            'cpc' => 0,
            'source' => 'api',
            'pulled_at' => now(),
        ]);

        $result = DB::table('v_campaign_platform_totals')->where('campaign_platform_id', $this->campaignPlatform->id)->first();

        $this->assertNotNull($result);

        $expectedBudgetPct = (500.00 / 1000.00) * 100;
        $this->assertEqualsWithDelta($expectedBudgetPct, $result->budget_pct_used, 0.01);
    }

    public function test_v_campaign_platform_totals_filters_daily_granularity_only(): void
    {
        AdSnapshot::create([
            'ad_id' => $this->ad->id,
            'snapshot_date' => now()->toDateString(),
            'granularity' => 'daily',
            'impressions' => 1000,
            'clicks' => 10,
            'spend' => 50.00,
            'ctr' => 0,
            'cpm' => 0,
            'cpc' => 0,
            'source' => 'api',
            'pulled_at' => now(),
        ]);

        AdSnapshot::create([
            'ad_id' => $this->ad->id,
            'snapshot_date' => now()->toDateString(),
            'granularity' => 'cumulative',
            'impressions' => 50000,
            'clicks' => 500,
            'spend' => 2500.00,
            'ctr' => 0,
            'cpm' => 0,
            'cpc' => 0,
            'source' => 'api',
            'pulled_at' => now(),
        ]);

        $result = DB::table('v_campaign_platform_totals')->where('campaign_platform_id', $this->campaignPlatform->id)->first();

        $this->assertNotNull($result);

        $this->assertEquals(1000, $result->total_impressions);
        $this->assertEquals(10, $result->total_clicks);
        $this->assertEquals(50.00, $result->total_spend);
    }

    public function test_v_campaign_platform_totals_calculates_cpa_platform_level(): void
    {
        AdSnapshot::create([
            'ad_id' => $this->ad->id,
            'snapshot_date' => now()->toDateString(),
            'granularity' => 'daily',
            'impressions' => 10000,
            'clicks' => 200,
            'conversions' => 50,
            'spend' => 1000.00,
            'ctr' => 0,
            'cpm' => 0,
            'cpc' => 0,
            'cpa' => 0,
            'source' => 'api',
            'pulled_at' => now(),
        ]);

        $result = DB::table('v_campaign_platform_totals')->where('campaign_platform_id', $this->campaignPlatform->id)->first();

        $this->assertNotNull($result);
        $this->assertEquals(50, $result->total_conversions);

        $expectedCPA = 1000.00 / 50;
        $this->assertEqualsWithDelta($expectedCPA, $result->calc_cpa, 0.01);
    }

    public function test_v_campaign_platform_totals_calculates_vtr_from_sums(): void
    {
        AdSnapshot::create([
            'ad_id' => $this->ad->id,
            'snapshot_date' => now()->toDateString(),
            'granularity' => 'daily',
            'impressions' => 20000,
            'clicks' => 300,
            'video_views' => 8000,
            'spend' => 1200.00,
            'ctr' => 0,
            'cpm' => 0,
            'cpc' => 0,
            'source' => 'api',
            'pulled_at' => now(),
        ]);

        $result = DB::table('v_campaign_platform_totals')->where('campaign_platform_id', $this->campaignPlatform->id)->first();

        $this->assertNotNull($result);

        $expectedVTR = (8000 / 20000) * 100;
        $this->assertEqualsWithDelta($expectedVTR, $result->calc_vtr, 0.01);
    }

    public function test_v_campaign_platform_totals_aggregates_engagement(): void
    {
        AdSnapshot::create([
            'ad_id' => $this->ad->id,
            'snapshot_date' => now()->toDateString(),
            'granularity' => 'daily',
            'impressions' => 4000,
            'clicks' => 80,
            'engagement' => 120,
            'spend' => 200.00,
            'ctr' => 0,
            'cpm' => 0,
            'cpc' => 0,
            'source' => 'api',
            'pulled_at' => now(),
        ]);

        $result = DB::table('v_campaign_platform_totals')->where('campaign_platform_id', $this->campaignPlatform->id)->first();

        $this->assertNotNull($result);
        $this->assertEquals(120, $result->total_engagement);
    }

    public function test_v_campaign_platform_totals_handles_zero_leads_and_zero_reach(): void
    {
        AdSnapshot::create([
            'ad_id' => $this->ad->id,
            'snapshot_date' => now()->toDateString(),
            'granularity' => 'daily',
            'impressions' => 2200,
            'reach' => 0,
            'clicks' => 30,
            'leads' => 0,
            'spend' => 110.00,
            'ctr' => 0,
            'cpm' => 0,
            'cpc' => 0,
            'cpl' => 0,
            'source' => 'api',
            'pulled_at' => now(),
        ]);

        $result = DB::table('v_campaign_platform_totals')->where('campaign_platform_id', $this->campaignPlatform->id)->first();

        $this->assertNotNull($result);
        $this->assertEquals(0, $result->calc_cpl);
        $this->assertNull($result->calc_frequency);
    }

    public function test_views_exclude_untracked_ads(): void
    {
        $this->ad->update(['is_tracked' => false]);

        AdSnapshot::create([
            'ad_id' => $this->ad->id,
            'snapshot_date' => now()->toDateString(),
            'granularity' => 'daily',
            'impressions' => 5000,
            'clicks' => 100,
            'spend' => 500.00,
            'ctr' => 0,
            'cpm' => 0,
            'cpc' => 0,
            'source' => 'api',
            'pulled_at' => now(),
        ]);

        $result = DB::table('v_campaign_platform_totals')->where('campaign_platform_id', $this->campaignPlatform->id)->first();

        if ($result === null) {
            $this->assertTrue(true);
        } else {
            $this->assertEquals(0, $result->total_impressions);
        }
    }
}
