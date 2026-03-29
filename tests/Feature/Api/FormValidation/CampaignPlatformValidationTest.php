<?php

namespace Tests\Feature\Api\FormValidation;

use App\Models\Category;
use App\Models\Client;
use App\Models\Campaign;
use App\Models\CampaignPlatform;
use App\Models\Platform;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CampaignPlatformValidationTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected Campaign $campaign;
    protected Platform $platform;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
        $category = Category::firstOrCreate(['slug' => 'fmcg'], ['name' => 'FMCG']);
        $client = Client::factory()->create(['category_id' => $category->id]);
        $this->campaign = Campaign::factory()->create([
            'client_id' => $client->id,
            'created_by' => $this->admin->id,
        ]);
        $this->platform = Platform::firstOrCreate(['slug' => 'meta'], ['name' => 'Meta', 'api_supported' => true]);
        $this->actingAs($this->admin);
    }

    public function test_create_campaign_platform_requires_campaign_id(): void
    {
        $response = $this->postJson('/api/campaign-platforms', [
            'platform_id' => $this->platform->id,
            'budget' => 10000,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['campaign_id']);
    }

    public function test_create_campaign_platform_requires_platform_id(): void
    {
        $response = $this->postJson('/api/campaign-platforms', [
            'campaign_id' => $this->campaign->id,
            'budget' => 10000,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['platform_id']);
    }

    public function test_create_campaign_platform_requires_budget(): void
    {
        $response = $this->postJson('/api/campaign-platforms', [
            'campaign_id' => $this->campaign->id,
            'platform_id' => $this->platform->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['budget']);
    }

    public function test_create_campaign_platform_budget_type_must_be_valid(): void
    {
        $response = $this->postJson('/api/campaign-platforms', [
            'campaign_id' => $this->campaign->id,
            'platform_id' => $this->platform->id,
            'budget' => 10000,
            'budget_type' => 'monthly',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['budget_type']);
    }

    public function test_create_campaign_platform_unique_per_campaign(): void
    {
        CampaignPlatform::create([
            'campaign_id' => $this->campaign->id,
            'platform_id' => $this->platform->id,
            'budget' => 10000,
        ]);

        $response = $this->postJson('/api/campaign-platforms', [
            'campaign_id' => $this->campaign->id,
            'platform_id' => $this->platform->id,
            'budget' => 5000,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['platform_id']);
    }

    public function test_create_campaign_platform_with_valid_data_returns_201(): void
    {
        $response = $this->postJson('/api/campaign-platforms', [
            'campaign_id' => $this->campaign->id,
            'platform_id' => $this->platform->id,
            'budget' => 25000,
            'budget_type' => 'lifetime',
            'currency' => 'MAD',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['data', 'meta']);
    }
}
