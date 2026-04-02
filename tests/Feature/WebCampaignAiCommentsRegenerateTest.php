<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\CampaignPlatform;
use App\Models\Platform;
use App\Models\User;
use App\Services\CampaignAiCommentaryService;
use App\Services\CampaignAiCommentaryRunner;
use Illuminate\Support\Facades\Config;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class WebCampaignAiCommentsRegenerateTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_regenerate_campaign_ai_comments_with_active_filters(): void
    {
        /** @var User $admin */
        $admin = User::factory()->create(['role' => 'admin']);

        $campaign = Campaign::factory()->create([
            'created_by' => $admin->id,
        ]);

        $platform = Platform::factory()->create();
        CampaignPlatform::query()->create([
            'campaign_id' => $campaign->id,
            'platform_id' => $platform->id,
            'platform_connection_id' => null,
            'external_campaign_id' => 'test-campaign-ext',
            'budget' => 1000,
            'budget_type' => 'lifetime',
            'currency' => 'MAD',
            'is_active' => true,
            'last_sync_at' => null,
            'notes' => null,
        ]);

        $runner = Mockery::mock(CampaignAiCommentaryRunner::class);
        $runner->shouldReceive('runCampaign')
            ->once()
            ->with($campaign->id, 14, $platform->id);
        $this->app->instance(CampaignAiCommentaryRunner::class, $runner);

        $response = $this->actingAs($admin)
            ->post(route('web.campaigns.ai-comments.regenerate', $campaign), [
                'days' => 14,
                'platform_id' => $platform->id,
            ]);

        $response
            ->assertRedirect(route('web.campaigns.show', [
                'campaign' => $campaign->id,
                'days' => 14,
                'platform_id' => $platform->id,
            ]))
            ->assertSessionHas('status', 'AI comments updated using current filters.');
    }

    public function test_viewer_is_forbidden_when_regenerating_campaign_ai_comments(): void
    {
        /** @var User $viewer */
        $viewer = User::factory()->create(['role' => 'viewer']);

        $campaign = Campaign::factory()->create([
            'created_by' => $viewer->id,
        ]);

        $response = $this->actingAs($viewer)
            ->post(route('web.campaigns.ai-comments.regenerate', $campaign), [
                'days' => 7,
            ]);

        $response->assertForbidden();
    }

    public function test_falls_back_to_local_commentary_when_python_network_error_occurs(): void
    {
        /** @var User $admin */
        $admin = User::factory()->create(['role' => 'admin']);

        $campaign = Campaign::factory()->create([
            'created_by' => $admin->id,
        ]);

        Config::set('services.ai_report_commentary.allow_local_fallback', true);

        $runner = Mockery::mock(CampaignAiCommentaryRunner::class);
        $runner->shouldReceive('runCampaign')
            ->once()
            ->with($campaign->id, 7, null)
            ->andThrow(new \RuntimeException('httpx.ConnectError: [WinError 10106] test'));
        $this->app->instance(CampaignAiCommentaryRunner::class, $runner);

        $service = Mockery::mock(CampaignAiCommentaryService::class);
        $service->shouldReceive('generateLocalFallbackCommentary')
            ->once()
            ->with(Mockery::type(Campaign::class), 7, null)
            ->andReturn($campaign);
        $this->app->instance(CampaignAiCommentaryService::class, $service);

        $response = $this->actingAs($admin)
            ->post(route('web.campaigns.ai-comments.regenerate', $campaign), [
                'days' => 7,
            ]);

        $response
            ->assertRedirect(route('web.campaigns.show', [
                'campaign' => $campaign->id,
                'days' => 7,
                'platform_id' => null,
            ]))
            ->assertSessionHas('status', 'AI comments updated in local fallback mode.');

    }
}
