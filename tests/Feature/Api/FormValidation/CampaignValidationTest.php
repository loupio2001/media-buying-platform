<?php

namespace Tests\Feature\Api\FormValidation;

use App\Models\Campaign;
use App\Models\Category;
use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CampaignValidationTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected Client $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
        $category = Category::firstOrCreate(['slug' => 'fmcg'], ['name' => 'FMCG']);
        $this->client = Client::factory()->create(['category_id' => $category->id]);
        $this->actingAs($this->admin);
    }

    public function test_create_campaign_requires_name(): void
    {
        $response = $this->postJson('/api/campaigns', [
            'client_id' => $this->client->id,
            'objective' => 'awareness',
            'start_date' => '2026-04-01',
            'end_date' => '2026-06-30',
            'total_budget' => 50000,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_create_campaign_requires_client_id(): void
    {
        $response = $this->postJson('/api/campaigns', [
            'name' => 'Ramadan Campaign',
            'objective' => 'awareness',
            'start_date' => '2026-04-01',
            'end_date' => '2026-06-30',
            'total_budget' => 50000,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['client_id']);
    }

    public function test_create_campaign_client_id_must_exist(): void
    {
        $response = $this->postJson('/api/campaigns', [
            'client_id' => 99999,
            'name' => 'Ramadan Campaign',
            'objective' => 'awareness',
            'start_date' => '2026-04-01',
            'end_date' => '2026-06-30',
            'total_budget' => 50000,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['client_id']);
    }

    public function test_create_campaign_end_date_must_be_after_start_date(): void
    {
        $response = $this->postJson('/api/campaigns', [
            'client_id' => $this->client->id,
            'name' => 'Ramadan Campaign',
            'objective' => 'awareness',
            'start_date' => '2026-06-30',
            'end_date' => '2026-04-01',
            'total_budget' => 50000,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['end_date']);
    }

    public function test_create_campaign_objective_must_be_valid_enum(): void
    {
        $response = $this->postJson('/api/campaigns', [
            'client_id' => $this->client->id,
            'name' => 'Ramadan Campaign',
            'objective' => 'invalid_objective',
            'start_date' => '2026-04-01',
            'end_date' => '2026-06-30',
            'total_budget' => 50000,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['objective']);
    }

    public function test_create_campaign_status_must_be_valid_enum(): void
    {
        $response = $this->postJson('/api/campaigns', [
            'client_id' => $this->client->id,
            'name' => 'Ramadan Campaign',
            'objective' => 'awareness',
            'status' => 'flying',
            'start_date' => '2026-04-01',
            'end_date' => '2026-06-30',
            'total_budget' => 50000,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['status']);
    }

    public function test_create_campaign_pacing_strategy_must_be_valid_enum(): void
    {
        $response = $this->postJson('/api/campaigns', [
            'client_id' => $this->client->id,
            'name' => 'Ramadan Campaign',
            'objective' => 'awareness',
            'pacing_strategy' => 'invalid',
            'start_date' => '2026-04-01',
            'end_date' => '2026-06-30',
            'total_budget' => 50000,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['pacing_strategy']);
    }

    public function test_create_campaign_total_budget_must_be_positive(): void
    {
        $response = $this->postJson('/api/campaigns', [
            'client_id' => $this->client->id,
            'name' => 'Ramadan Campaign',
            'objective' => 'awareness',
            'start_date' => '2026-04-01',
            'end_date' => '2026-06-30',
            'total_budget' => -500,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['total_budget']);
    }

    public function test_create_campaign_with_valid_data_returns_201(): void
    {
        $response = $this->postJson('/api/campaigns', [
            'client_id' => $this->client->id,
            'name' => 'Ramadan Campaign 2026',
            'objective' => 'awareness',
            'start_date' => '2026-04-01',
            'end_date' => '2026-06-30',
            'total_budget' => 50000,
            'currency' => 'MAD',
            'pacing_strategy' => 'even',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['data', 'meta']);
    }

    public function test_update_campaign_allows_partial_data(): void
    {
        $campaign = Campaign::factory()->create([
            'client_id' => $this->client->id,
            'created_by' => $this->admin->id,
        ]);

        $response = $this->patchJson("/api/campaigns/{$campaign->id}", [
            'status' => 'active',
        ]);

        $response->assertStatus(200);
    }

    public function test_update_campaign_rejects_invalid_status(): void
    {
        $campaign = Campaign::factory()->create([
            'client_id' => $this->client->id,
            'created_by' => $this->admin->id,
        ]);

        $response = $this->patchJson("/api/campaigns/{$campaign->id}", [
            'status' => 'not-a-status',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['status']);
    }
}
