<?php

namespace Tests\Feature;

use App\Enums\CampaignStatus;
use App\Models\Campaign;
use App\Models\Client;
use App\Models\Platform;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class WebDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login_when_accessing_dashboard(): void
    {
        $response = $this->get('/dashboard');

        $response->assertRedirect('/login');
    }

    public function test_authenticated_user_sees_dashboard_summary(): void
    {
        $isPgsql = config('database.default') === 'pgsql';

        /** @var User $user */
        $user = User::factory()->create();
        $client = Client::factory()->create();
        $platform = Platform::factory()->create();

        Campaign::factory()->create([
            'name' => 'Campaign Archived',
            'status' => CampaignStatus::Archived->value,
            'total_budget' => 400,
            'created_by' => $user->id,
            'client_id' => $client->id,
        ]);

        $campaignOne = Campaign::factory()->create([
            'name' => 'Campaign Alpha',
            'status' => CampaignStatus::Active->value,
            'total_budget' => 100,
            'created_by' => $user->id,
            'client_id' => $client->id,
        ]);

        $campaignTwo = Campaign::factory()->create([
            'name' => 'Campaign Beta',
            'status' => CampaignStatus::Paused->value,
            'total_budget' => 200,
            'created_by' => $user->id,
            'client_id' => $client->id,
        ]);

        Campaign::factory()->create([
            'name' => 'Campaign Gamma',
            'status' => CampaignStatus::Draft->value,
            'total_budget' => 300,
            'created_by' => $user->id,
            'client_id' => $client->id,
        ]);

        if ($isPgsql) {
            $campaignPlatformOne = DB::table('campaign_platforms')->insertGetId([
                'campaign_id' => $campaignOne->id,
                'platform_id' => $platform->id,
                'platform_connection_id' => null,
                'external_campaign_id' => 'cmp-alpha',
                'budget' => 1000,
                'budget_type' => 'lifetime',
                'currency' => 'MAD',
                'is_active' => true,
                'last_sync_at' => null,
                'notes' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $campaignPlatformTwo = DB::table('campaign_platforms')->insertGetId([
                'campaign_id' => $campaignTwo->id,
                'platform_id' => $platform->id,
                'platform_connection_id' => null,
                'external_campaign_id' => 'cmp-beta',
                'budget' => 2000,
                'budget_type' => 'lifetime',
                'currency' => 'MAD',
                'is_active' => true,
                'last_sync_at' => null,
                'notes' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $adSetOne = DB::table('ad_sets')->insertGetId([
                'campaign_platform_id' => $campaignPlatformOne,
                'external_id' => 'as-alpha',
                'name' => 'Ad Set Alpha',
                'objective' => null,
                'targeting_summary' => null,
                'status' => 'active',
                'budget' => null,
                'budget_type' => null,
                'bid_strategy' => null,
                'start_date' => null,
                'end_date' => null,
                'is_tracked' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $adSetTwo = DB::table('ad_sets')->insertGetId([
                'campaign_platform_id' => $campaignPlatformTwo,
                'external_id' => 'as-beta',
                'name' => 'Ad Set Beta',
                'objective' => null,
                'targeting_summary' => null,
                'status' => 'active',
                'budget' => null,
                'budget_type' => null,
                'bid_strategy' => null,
                'start_date' => null,
                'end_date' => null,
                'is_tracked' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $adOne = DB::table('ads')->insertGetId([
                'ad_set_id' => $adSetOne,
                'external_id' => 'ad-alpha',
                'name' => 'Ad Alpha',
                'format' => null,
                'creative_url' => null,
                'headline' => null,
                'body' => null,
                'cta' => null,
                'destination_url' => null,
                'status' => 'active',
                'is_tracked' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $adTwo = DB::table('ads')->insertGetId([
                'ad_set_id' => $adSetTwo,
                'external_id' => 'ad-beta',
                'name' => 'Ad Beta',
                'format' => null,
                'creative_url' => null,
                'headline' => null,
                'body' => null,
                'cta' => null,
                'destination_url' => null,
                'status' => 'active',
                'is_tracked' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('ad_snapshots')->insert([
                [
                    'ad_id' => $adOne,
                    'snapshot_date' => now()->toDateString(),
                    'granularity' => 'daily',
                    'impressions' => 1000,
                    'reach' => 800,
                    'frequency' => null,
                    'clicks' => 100,
                    'link_clicks' => null,
                    'landing_page_views' => null,
                    'ctr' => null,
                    'spend' => 120,
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
                ],
                [
                    'ad_id' => $adTwo,
                    'snapshot_date' => now()->toDateString(),
                    'granularity' => 'daily',
                    'impressions' => 500,
                    'reach' => 400,
                    'frequency' => null,
                    'clicks' => 50,
                    'link_clicks' => null,
                    'landing_page_views' => null,
                    'ctr' => null,
                    'spend' => 60,
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
                ],
            ]);
        }

        $response = $this->actingAs($user)->get('/dashboard');

        $response
            ->assertOk()
            ->assertSee('Campagnes totales')
            ->assertSee('Budget total (MAD)')
            ->assertSee('Spend total (MAD)')
            ->assertSee('CTR global (%)')
            ->assertSee('Campagnes recentes')
            ->assertSee('Campaign Alpha')
            ->assertSee('Campaign Beta')
            ->assertViewHas('summary', function (array $summary) use ($isPgsql): bool {
                $baseAssertions = $summary['total_campaigns'] === 3
                    && $summary['active_campaigns'] === 1
                    && $summary['running_campaigns'] === 2
                    && (float) $summary['total_budget'] === 600.0;

                if (! $baseAssertions) {
                    return false;
                }

                if (! $isPgsql) {
                    return true;
                }

                return (float) $summary['total_spend'] === 180.0
                    && (float) $summary['global_ctr'] === 10.0;
            });
    }

    public function test_authenticated_user_sees_empty_state_when_no_campaign_exists(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/dashboard');

        $response
            ->assertOk()
            ->assertSee('Campagnes totales')
            ->assertSee('Aucune campagne disponible pour le moment.')
            ->assertViewHas('summary', function (array $summary): bool {
                return $summary['total_campaigns'] === 0
                    && $summary['active_campaigns'] === 0
                    && $summary['running_campaigns'] === 0
                    && (float) $summary['total_budget'] === 0.0
                    && (float) $summary['total_spend'] === 0.0
                    && (float) $summary['global_ctr'] === 0.0;
            });
    }
}
