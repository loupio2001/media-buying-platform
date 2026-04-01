<?php

namespace Tests\Feature;

use App\Enums\CampaignStatus;
use App\Models\Campaign;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebCampaignStatusUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_when_updating_campaign_status(): void
    {
        $campaign = Campaign::factory()->create([
            'status' => CampaignStatus::Draft->value,
        ]);

        $response = $this->patch(route('web.campaigns.status.update', $campaign), [
            'status' => CampaignStatus::Active->value,
        ]);

        $response->assertRedirect(route('login'));
    }

    public function test_viewer_is_forbidden_when_updating_campaign_status(): void
    {
        /** @var User $viewer */
        $viewer = User::factory()->create(['role' => 'viewer']);
        $campaign = Campaign::factory()->create([
            'status' => CampaignStatus::Draft->value,
            'created_by' => $viewer->id,
        ]);

        $response = $this->actingAs($viewer)
            ->patch(route('web.campaigns.status.update', $campaign), [
                'status' => CampaignStatus::Active->value,
            ]);

        $response->assertStatus(403);
    }

    public function test_admin_can_update_campaign_status_to_active(): void
    {
        /** @var User $admin */
        $admin = User::factory()->admin()->create();
        $campaign = Campaign::factory()->create([
            'status' => CampaignStatus::Draft->value,
            'created_by' => $admin->id,
        ]);

        $response = $this->actingAs($admin)
            ->patch(route('web.campaigns.status.update', $campaign), [
                'status' => CampaignStatus::Active->value,
            ]);

        $response
            ->assertRedirect(route('web.campaigns.show', $campaign))
            ->assertSessionHas('status', 'Campaign status updated successfully.');

        $this->assertDatabaseHas('campaigns', [
            'id' => $campaign->id,
            'status' => CampaignStatus::Active->value,
        ]);
    }
}
