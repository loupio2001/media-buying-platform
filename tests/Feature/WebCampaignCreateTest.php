<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebCampaignCreateTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login_when_accessing_campaign_create(): void
    {
        $response = $this->get(route('web.campaigns.create'));

        $response->assertRedirect(route('login'));
    }

    public function test_viewer_is_forbidden_from_campaign_create_page(): void
    {
        /** @var User $viewer */
        $viewer = User::factory()->create(['role' => 'viewer']);

        $response = $this->actingAs($viewer)->get(route('web.campaigns.create'));

        $response->assertStatus(403);
    }

    public function test_admin_and_manager_can_access_campaign_create_page(): void
    {
        $client = Client::factory()->create();

        foreach ([User::factory()->admin()->create(), User::factory()->create(['role' => 'manager'])] as $user) {
            $response = $this->actingAs($user)->get(route('web.campaigns.create'));

            $response
                ->assertOk()
                ->assertSee('Create Campaign')
                ->assertSee($client->name);
        }
    }

    public function test_admin_can_create_campaign_from_web_form(): void
    {
        $admin = User::factory()->admin()->create();
        $client = Client::factory()->create();

        $response = $this->actingAs($admin)->post(route('web.campaigns.store'), [
            'client_id' => $client->id,
            'name' => 'Meta Sync Test Campaign',
            'objective' => 'leads',
            'start_date' => now()->startOfMonth()->toDateString(),
            'end_date' => now()->endOfMonth()->toDateString(),
            'total_budget' => 12000.50,
            'currency' => 'MAD',
            'internal_notes' => 'Campaign created from web UI test',
        ]);

        $campaign = Campaign::query()->where('name', 'Meta Sync Test Campaign')->first();

        $this->assertNotNull($campaign);

        $response
            ->assertRedirect(route('web.campaigns.show', $campaign))
            ->assertSessionHas('status', 'Campaign created successfully.');

        $this->assertDatabaseHas('campaigns', [
            'id' => $campaign->id,
            'client_id' => $client->id,
            'name' => 'Meta Sync Test Campaign',
            'objective' => 'leads',
            'status' => 'draft',
            'pacing_strategy' => 'even',
            'created_by' => $admin->id,
        ]);
    }

    public function test_campaign_create_form_validates_required_fields(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->post(route('web.campaigns.store'), [
            'client_id' => null,
            'name' => '',
            'objective' => 'invalid-objective',
            'start_date' => '2026-04-20',
            'end_date' => '2026-04-01',
            'total_budget' => -10,
        ]);

        $response
            ->assertSessionHasErrors([
                'client_id',
                'name',
                'objective',
                'end_date',
                'total_budget',
            ]);
    }
}
