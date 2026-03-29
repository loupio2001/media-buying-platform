<?php

namespace Tests\Feature\Api\FormValidation;

use App\Models\Platform;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlatformValidationTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($this->admin);
    }

    public function test_create_platform_requires_name(): void
    {
        $response = $this->postJson('/api/platforms', [
            'slug' => 'test-platform',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_create_platform_requires_slug(): void
    {
        $response = $this->postJson('/api/platforms', [
            'name' => 'Test Platform',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['slug']);
    }

    public function test_create_platform_slug_must_be_unique(): void
    {
        Platform::factory()->create(['slug' => 'existing-slug']);

        $response = $this->postJson('/api/platforms', [
            'name' => 'Another Platform',
            'slug' => 'existing-slug',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['slug']);
    }

    public function test_create_platform_name_max_length(): void
    {
        $response = $this->postJson('/api/platforms', [
            'name' => str_repeat('a', 51),
            'slug' => 'valid-slug',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_update_platform_allows_partial_data(): void
    {
        $platform = Platform::factory()->create();

        $response = $this->patchJson("/api/platforms/{$platform->id}", [
            'is_active' => false,
        ]);

        $response->assertStatus(200);
    }

    public function test_update_platform_slug_unique_ignores_self(): void
    {
        $platform = Platform::factory()->create(['slug' => 'my-slug']);

        $response = $this->patchJson("/api/platforms/{$platform->id}", [
            'slug' => 'my-slug',
        ]);

        $response->assertStatus(200);
    }

    public function test_create_platform_with_valid_data_returns_201(): void
    {
        $response = $this->postJson('/api/platforms', [
            'name' => 'Snapchat Pro',
            'slug' => 'snapchat-pro',
            'api_supported' => true,
            'sort_order' => 10,
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['data', 'meta']);
    }
}
