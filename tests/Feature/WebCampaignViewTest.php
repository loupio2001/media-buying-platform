<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\CampaignPlatform;
use App\Models\Platform;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebCampaignViewTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_when_viewing_campaign_detail(): void
    {
        $campaign = Campaign::factory()->create();

        $response = $this->get('/campaigns/' . $campaign->id);

        $response->assertRedirect('/login');
    }

    public function test_authenticated_user_can_view_campaign_detail_page(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $platform = Platform::factory()->create(['name' => 'Meta Ads']);

        $campaign = Campaign::factory()->create([
            'name' => 'Campaign Delta',
            'created_by' => $user->id,
        ]);

        CampaignPlatform::create([
            'campaign_id' => $campaign->id,
            'platform_id' => $platform->id,
            'platform_connection_id' => null,
            'external_campaign_id' => 'ext-delta',
            'budget' => 5000,
            'budget_type' => 'lifetime',
            'currency' => 'MAD',
            'is_active' => true,
            'last_sync_at' => null,
            'notes' => null,
        ]);

        $response = $this->actingAs($user)->get('/campaigns/' . $campaign->id);

        $response
            ->assertOk()
            ->assertSee('Campaign Delta')
            ->assertSee('Meta Ads')
            ->assertSee('Spend (MAD)')
            ->assertSee('CTR (%)')
            ->assertSee('Trend 7 days')
            ->assertSee('Spend sparkline')
            ->assertSee('Clicks sparkline')
            ->assertSee('No data yet')
            ->assertSee('14d')
            ->assertSee('30d')
            ->assertSee('Export CSV')
            ->assertSee('Back to dashboard');

        $response->assertDontSee('<polyline', false);
    }

    public function test_campaign_trend_period_can_be_changed_or_falls_back_to_default(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $campaign = Campaign::factory()->create([
            'created_by' => $user->id,
        ]);

        $response14 = $this->actingAs($user)->get('/campaigns/' . $campaign->id . '?days=14');
        $response14
            ->assertOk()
            ->assertSee('Trend 14 days');

        $responseInvalid = $this->actingAs($user)->get('/campaigns/' . $campaign->id . '?days=999');
        $responseInvalid
            ->assertOk()
            ->assertSee('Trend 7 days');
    }

    public function test_campaign_trend_csv_export_uses_selected_period(): void
    {
        /** @var User $user */
        $user = User::factory()->create();
        $campaign = Campaign::factory()->create([
            'created_by' => $user->id,
        ]);

        $response = $this->actingAs($user)->get('/campaigns/' . $campaign->id . '/trend.csv?days=14');

        $response
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8')
            ->assertHeader('content-disposition', 'attachment; filename=campaign-' . $campaign->id . '-trend-14d.csv');

        $this->assertStringContainsString('date,spend_mad,impressions,clicks,ctr_pct', $response->streamedContent());
    }
}
