<?php

namespace Tests\Feature\Api\FormValidation;

use App\Models\Category;
use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientValidationTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->category = Category::firstOrCreate(['slug' => 'fmcg'], ['name' => 'FMCG']);
        $this->actingAs($this->admin);
    }

    public function test_create_client_requires_name(): void
    {
        $response = $this->postJson('/api/clients', [
            'category_id' => $this->category->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_create_client_requires_category_id(): void
    {
        $response = $this->postJson('/api/clients', [
            'name' => 'Test Client',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['category_id']);
    }

    public function test_create_client_category_id_must_exist(): void
    {
        $response = $this->postJson('/api/clients', [
            'name' => 'Test Client',
            'category_id' => 99999,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['category_id']);
    }

    public function test_create_client_contact_email_must_be_valid(): void
    {
        $response = $this->postJson('/api/clients', [
            'name' => 'Test Client',
            'category_id' => $this->category->id,
            'contact_email' => 'not-an-email',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['contact_email']);
    }

    public function test_create_client_billing_type_must_be_valid(): void
    {
        $response = $this->postJson('/api/clients', [
            'name' => 'Test Client',
            'category_id' => $this->category->id,
            'billing_type' => 'invalid_type',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['billing_type']);
    }

    public function test_create_client_contract_end_must_be_after_start(): void
    {
        $response = $this->postJson('/api/clients', [
            'name' => 'Test Client',
            'category_id' => $this->category->id,
            'contract_start' => '2026-06-01',
            'contract_end' => '2026-01-01',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['contract_end']);
    }

    public function test_create_client_with_valid_data_returns_201(): void
    {
        $response = $this->postJson('/api/clients', [
            'name' => 'RAM Airlines',
            'category_id' => $this->category->id,
            'billing_type' => 'retainer',
            'country' => 'Morocco',
            'currency' => 'MAD',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['data', 'meta']);
    }

    public function test_update_client_allows_partial_data(): void
    {
        $client = Client::factory()->create(['category_id' => $this->category->id]);

        $response = $this->patchJson("/api/clients/{$client->id}", [
            'is_active' => false,
        ]);

        $response->assertStatus(200);
    }
}
