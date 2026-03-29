<?php

namespace Tests\Feature\Api;

use Tests\TestCase;

class InternalApiAuthTest extends TestCase
{
    public function test_internal_endpoint_rejects_request_without_token(): void
    {
        $response = $this->postJson('/api/internal/v1/snapshots', []);

        $response->assertStatus(401)
            ->assertJsonPath('data', null)
            ->assertJsonPath('meta.error', 'Unauthorized');
    }

    public function test_internal_endpoint_rejects_request_with_invalid_token(): void
    {
        $response = $this->withHeaders([
            'X-Internal-Token' => 'invalid-token',
        ])->postJson('/api/internal/v1/snapshots', []);

        $response->assertStatus(401)
            ->assertJsonPath('data', null)
            ->assertJsonPath('meta.error', 'Unauthorized');
    }
}
