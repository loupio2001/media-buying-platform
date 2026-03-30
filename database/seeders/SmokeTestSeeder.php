<?php

namespace Database\Seeders;

use App\Models\Campaign;
use App\Models\Category;
use App\Models\Client;
use App\Models\Platform;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class SmokeTestSeeder extends Seeder
{
    public function run(): void
    {
        /** @var User $user */
        $user = User::query()->where('email', 'admin@havasmad.com')->first()
            ?? User::factory()->admin()->create([
                'name' => 'Admin Demo',
                'email' => 'admin@havasmad.com',
                'password' => Hash::make('password'),
                'is_active' => true,
            ]);

        $category = Category::query()->firstOrCreate(
            ['slug' => 'smoke-test-category'],
            [
                'name' => 'Smoke Test Category',
                'description' => 'Seeded for API smoke tests',
                'is_custom' => true,
            ]
        );

        $platform = Platform::query()->firstOrCreate(
            ['slug' => 'meta'],
            [
                'name' => 'Meta',
                'icon_url' => null,
                'api_supported' => true,
                'supports_reach' => true,
                'supports_video_metrics' => true,
                'supports_frequency' => true,
                'supports_leads' => true,
                'default_metrics' => [
                    'always' => ['impressions', 'clicks', 'ctr', 'spend', 'cpm', 'cpc'],
                    'optional' => ['reach', 'frequency', 'video_views', 'vtr', 'conversions', 'cpa', 'leads', 'cpl'],
                    'platform_specific' => [],
                ],
                'rate_limit_config' => [
                    'requests_per_hour' => 200,
                    'requests_per_day' => 1000,
                    'batch_size' => 50,
                    'cooldown_seconds' => 2,
                ],
                'is_active' => true,
                'sort_order' => 1,
            ]
        );

        $client = Client::query()->firstOrCreate(
            ['name' => 'Smoke Test Client'],
            [
                'category_id' => $category->id,
                'primary_contact' => 'Smoke Test Contact',
                'contact_email' => 'client@havasmad.com',
                'contact_phone' => '+212600000000',
                'agency_lead' => 'Havas Lead',
                'country' => 'Morocco',
                'currency' => 'MAD',
                'billing_type' => 'project',
                'is_active' => true,
                'notes' => 'Seeded for API smoke tests',
            ]
        );

        $campaign = Campaign::query()->firstOrCreate(
            ['name' => 'Smoke Test Campaign'],
            [
                'client_id' => $client->id,
                'status' => 'active',
                'objective' => 'traffic',
                'start_date' => Carbon::now()->subDays(14)->toDateString(),
                'end_date' => Carbon::now()->addDays(14)->toDateString(),
                'total_budget' => 15000,
                'currency' => 'MAD',
                'kpi_targets' => ['ctr' => 1.5],
                'pacing_strategy' => 'even',
                'created_by' => $user->id,
                'internal_notes' => 'Seeded for API smoke tests',
            ]
        );

        $campaignPlatformId = (int) (DB::table('campaign_platforms')
            ->where('campaign_id', $campaign->id)
            ->where('platform_id', $platform->id)
            ->value('id') ?? 0);

        if ($campaignPlatformId === 0) {
            $campaignPlatformId = DB::table('campaign_platforms')->insertGetId([
                'campaign_id' => $campaign->id,
                'platform_id' => $platform->id,
                'platform_connection_id' => null,
                'external_campaign_id' => 'smoke-test-meta-campaign',
                'budget' => 15000,
                'budget_type' => 'lifetime',
                'currency' => 'MAD',
                'is_active' => true,
                'last_sync_at' => null,
                'notes' => 'Seeded for API smoke tests',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $adSetId = (int) (DB::table('ad_sets')
            ->where('campaign_platform_id', $campaignPlatformId)
            ->where('external_id', 'smoke-test-adset')
            ->value('id') ?? 0);

        if ($adSetId === 0) {
            $adSetId = DB::table('ad_sets')->insertGetId([
                'campaign_platform_id' => $campaignPlatformId,
                'external_id' => 'smoke-test-adset',
                'name' => 'Smoke Test Ad Set',
                'objective' => 'traffic',
                'targeting_summary' => 'Casablanca audience',
                'status' => 'active',
                'budget' => 7500,
                'budget_type' => 'lifetime',
                'bid_strategy' => null,
                'start_date' => Carbon::now()->subDays(14)->toDateString(),
                'end_date' => Carbon::now()->addDays(14)->toDateString(),
                'is_tracked' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $adId = (int) (DB::table('ads')
            ->where('ad_set_id', $adSetId)
            ->where('external_id', 'smoke-test-ad')
            ->value('id') ?? 0);

        if ($adId === 0) {
            $adId = DB::table('ads')->insertGetId([
                'ad_set_id' => $adSetId,
                'external_id' => 'smoke-test-ad',
                'name' => 'Smoke Test Ad',
                'format' => 'image',
                'creative_url' => null,
                'headline' => 'Smoke Test Headline',
                'body' => 'Smoke test copy',
                'cta' => 'Learn More',
                'destination_url' => 'https://example.com',
                'status' => 'active',
                'is_tracked' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $rows = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i)->toDateString();
            $spend = 100 + (($i % 3) * 15);
            $impressions = 10000 + (($i + 1) * 500);
            $reach = 7000 + (($i + 1) * 300);
            $clicks = 180 + ($i * 8);
            $videoViews = 2200 + ($i * 120);

            $rows[] = [
                'ad_id' => $adId,
                'snapshot_date' => $date,
                'granularity' => 'daily',
                'impressions' => $impressions,
                'reach' => $reach,
                'frequency' => null,
                'clicks' => $clicks,
                'link_clicks' => $clicks,
                'landing_page_views' => null,
                'ctr' => null,
                'spend' => $spend,
                'cpm' => null,
                'cpc' => null,
                'conversions' => 12 + $i,
                'cpa' => null,
                'leads' => 4 + intdiv($i, 2),
                'cpl' => null,
                'video_views' => $videoViews,
                'video_completions' => (int) round($videoViews * 0.4),
                'vtr' => null,
                'engagement' => 90 + ($i * 4),
                'engagement_rate' => null,
                'thumb_stop_rate' => null,
                'custom_metrics' => null,
                'raw_response' => null,
                'source' => 'api',
                'pulled_at' => now(),
            ];
        }

        DB::table('ad_snapshots')->upsert(
            $rows,
            ['ad_id', 'snapshot_date', 'granularity'],
            [
                'impressions',
                'reach',
                'clicks',
                'link_clicks',
                'spend',
                'conversions',
                'leads',
                'video_views',
                'video_completions',
                'engagement',
                'source',
                'pulled_at',
            ]
        );
    }
}