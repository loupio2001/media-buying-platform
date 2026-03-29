<?php

namespace Database\Seeders;

use App\Models\Campaign;
use App\Models\Client;
use App\Models\Platform;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DemoTrendSeeder extends Seeder
{
    public function run(): void
    {
        /** @var User $user */
        $user = User::query()->where('email', 'admin@havasmad.com')->first()
            ?? User::factory()->create([
                'name' => 'Admin Demo',
                'email' => 'admin@havasmad.com',
                'role' => 'admin',
                'password' => Hash::make('password'),
                'is_active' => true,
            ]);

        $client = Client::query()->first() ?? Client::factory()->create([
            'name' => 'Demo Client',
            'currency' => 'MAD',
        ]);

        $platform = Platform::query()->where('slug', 'meta')->first()
            ?? Platform::query()->first()
            ?? Platform::factory()->create(['name' => 'Meta', 'slug' => 'meta']);

        $campaign = Campaign::query()->firstOrCreate(
            ['name' => 'Demo Trend Campaign'],
            [
                'client_id' => $client->id,
                'status' => 'active',
                'objective' => 'traffic',
                'start_date' => Carbon::now()->subDays(30)->toDateString(),
                'end_date' => Carbon::now()->addDays(30)->toDateString(),
                'total_budget' => 25000,
                'currency' => 'MAD',
                'pacing_strategy' => 'even',
                'created_by' => $user->id,
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
                'external_campaign_id' => 'demo-trend-campaign',
                'budget' => 25000,
                'budget_type' => 'lifetime',
                'currency' => 'MAD',
                'is_active' => true,
                'last_sync_at' => null,
                'notes' => 'Seeded demo trend data',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $adSetId = (int) (DB::table('ad_sets')
            ->where('campaign_platform_id', $campaignPlatformId)
            ->where('external_id', 'demo-trend-adset')
            ->value('id') ?? 0);

        if ($adSetId === 0) {
            $adSetId = DB::table('ad_sets')->insertGetId([
                'campaign_platform_id' => $campaignPlatformId,
                'external_id' => 'demo-trend-adset',
                'name' => 'Demo Trend Ad Set',
                'objective' => 'traffic',
                'targeting_summary' => 'Demo targeting',
                'status' => 'active',
                'budget' => 12000,
                'budget_type' => 'lifetime',
                'bid_strategy' => null,
                'start_date' => Carbon::now()->subDays(30)->toDateString(),
                'end_date' => Carbon::now()->addDays(30)->toDateString(),
                'is_tracked' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $adId = (int) (DB::table('ads')
            ->where('ad_set_id', $adSetId)
            ->where('external_id', 'demo-trend-ad')
            ->value('id') ?? 0);

        if ($adId === 0) {
            $adId = DB::table('ads')->insertGetId([
                'ad_set_id' => $adSetId,
                'external_id' => 'demo-trend-ad',
                'name' => 'Demo Trend Ad',
                'format' => 'image',
                'creative_url' => null,
                'headline' => 'Demo Headline',
                'body' => 'Demo copy',
                'cta' => 'Learn More',
                'destination_url' => null,
                'status' => 'active',
                'is_tracked' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $rows = [];
        for ($i = 29; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i)->toDateString();
            $baseline = 80 + ((29 - $i) * 3);
            $wave = 25 * sin((29 - $i) / 3);
            $spend = max(25, round($baseline + $wave, 2));
            $impressions = max(500, (int) round($spend * 120 + 1000));
            $clicks = max(15, (int) round($impressions * 0.018));
            $reach = max(300, (int) round($impressions * 0.72));

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
                'conversions' => null,
                'cpa' => null,
                'leads' => null,
                'cpl' => null,
                'video_views' => null,
                'video_completions' => null,
                'vtr' => null,
                'engagement' => null,
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
            ['impressions', 'reach', 'clicks', 'link_clicks', 'spend', 'source', 'pulled_at']
        );
    }
}
