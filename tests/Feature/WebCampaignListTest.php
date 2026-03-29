<?php

namespace Tests\Feature;

use App\Enums\CampaignStatus;
use App\Models\Campaign;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebCampaignListTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_when_accessing_campaigns_index(): void
    {
        $response = $this->get('/campaigns');

        $response->assertRedirect('/login');
    }

    public function test_authenticated_user_can_view_campaigns_index_and_filters(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        Campaign::factory()->create([
            'name' => 'Alpha Search Campaign',
            'status' => CampaignStatus::Active->value,
            'created_by' => $user->id,
        ]);

        Campaign::factory()->create([
            'name' => 'Beta Display Campaign',
            'status' => CampaignStatus::Paused->value,
            'created_by' => $user->id,
        ]);

        $response = $this->actingAs($user)->get('/campaigns?status=active&q=Alpha');

        $response
            ->assertOk()
            ->assertSee('Campaign list')
            ->assertSee('Alpha Search Campaign')
            ->assertDontSee('Beta Display Campaign')
            ->assertSee('View');
    }
}
