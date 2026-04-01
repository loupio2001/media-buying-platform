<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\CampaignPlatform;
use App\Models\Client;
use App\Models\Platform;
use App\Models\PlatformConnection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebCampaignPlatformLinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_when_linking_platform_to_campaign(): void
    {
        $campaign = Campaign::factory()->create();

        $response = $this->post(route('web.campaigns.platforms.store', $campaign), []);

        $response->assertRedirect(route('login'));
    }

    public function test_viewer_is_forbidden_when_linking_platform_to_campaign(): void
    {
        /** @var User $viewer */
        $viewer = User::factory()->create(['role' => 'viewer']);
        $campaign = Campaign::factory()->create();

        $response = $this->actingAs($viewer)
            ->post(route('web.campaigns.platforms.store', $campaign), []);

        $response->assertStatus(403);
    }

    public function test_admin_can_link_platform_to_campaign(): void
    {
        /** @var User $admin */
        $admin = User::factory()->admin()->create();
        $client = Client::factory()->create();
        $campaign = Campaign::factory()->create([
            'client_id' => $client->id,
            'created_by' => $admin->id,
        ]);

        $platform = Platform::query()->firstOrCreate(
            ['slug' => 'meta'],
            [
                'name' => 'Meta',
                'api_supported' => true,
                'supports_reach' => true,
                'supports_video_metrics' => true,
                'supports_frequency' => true,
                'supports_leads' => true,
                'is_active' => true,
                'sort_order' => 1,
            ]
        );

        $connection = PlatformConnection::query()->create([
            'platform_id' => $platform->id,
            'account_id' => 'act_12345',
            'account_name' => 'Meta Account',
            'auth_type' => 'oauth2',
            'access_token' => 'secret-token',
            'is_connected' => true,
            'error_count' => 0,
            'created_by' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->post(route('web.campaigns.platforms.store', $campaign), [
            'platform_id' => $platform->id,
            'platform_connection_id' => $connection->id,
            'external_campaign_id' => '12021555555555',
            'budget' => 7000,
            'budget_type' => 'lifetime',
            'currency' => 'MAD',
            'is_active' => true,
            'notes' => 'Primary mapping for sync',
        ]);

        $response
            ->assertRedirect(route('web.campaigns.show', $campaign))
            ->assertSessionHas('status', 'Platform linked to campaign successfully.');

        $this->assertDatabaseHas('campaign_platforms', [
            'campaign_id' => $campaign->id,
            'platform_id' => $platform->id,
            'platform_connection_id' => $connection->id,
            'external_campaign_id' => '12021555555555',
            'budget' => 7000,
            'budget_type' => 'lifetime',
            'currency' => 'MAD',
            'is_active' => true,
        ]);
    }

    public function test_linking_platform_validates_connection_platform_match(): void
    {
        /** @var User $admin */
        $admin = User::factory()->admin()->create();
        $campaign = Campaign::factory()->create(['created_by' => $admin->id]);

        $meta = Platform::query()->firstOrCreate(
            ['slug' => 'meta'],
            [
                'name' => 'Meta',
                'api_supported' => true,
                'supports_reach' => true,
                'supports_video_metrics' => true,
                'supports_frequency' => true,
                'supports_leads' => true,
                'is_active' => true,
                'sort_order' => 1,
            ]
        );
        $google = Platform::query()->firstOrCreate(
            ['slug' => 'google'],
            [
                'name' => 'Google Ads',
                'api_supported' => true,
                'supports_reach' => true,
                'supports_video_metrics' => true,
                'supports_frequency' => true,
                'supports_leads' => true,
                'is_active' => true,
                'sort_order' => 2,
            ]
        );

        $googleConnection = PlatformConnection::query()->create([
            'platform_id' => $google->id,
            'account_id' => 'google_1',
            'account_name' => 'Google Account',
            'auth_type' => 'api_key',
            'api_key' => 'g-secret',
            'is_connected' => true,
            'error_count' => 0,
            'created_by' => $admin->id,
        ]);

        $response = $this->actingAs($admin)
            ->from(route('web.campaigns.show', $campaign))
            ->post(route('web.campaigns.platforms.store', $campaign), [
                'platform_id' => $meta->id,
                'platform_connection_id' => $googleConnection->id,
                'external_campaign_id' => '12029999999999',
                'budget' => 5000,
                'budget_type' => 'daily',
                'currency' => 'MAD',
                'is_active' => true,
            ]);

        $response
            ->assertRedirect(route('web.campaigns.show', $campaign))
            ->assertSessionHasErrors(['platform_connection_id']);

        $this->assertSame(0, CampaignPlatform::query()->count());
    }
}
