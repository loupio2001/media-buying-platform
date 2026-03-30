<?php

namespace Tests\Feature\Api;

use App\Models\Platform;
use App\Models\PlatformConnection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class InternalPlatformConnectionTokenRefreshTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.internal_api_token', 'test-internal-token');
        config()->set('services.meta_ads.client_id', 'meta-client-id');
        config()->set('services.meta_ads.client_secret', 'meta-client-secret');
    }

    public function test_internal_refresh_token_endpoint_refreshes_meta_oauth_connection(): void
    {
        $user = User::factory()->admin()->create();
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
            'account_id' => 'meta-acc-01',
            'auth_type' => 'oauth2',
            'access_token' => 'old-access-token',
            'token_expires_at' => now()->subMinutes(5),
            'is_connected' => false,
            'error_count' => 3,
            'last_error' => 'Expired token',
            'created_by' => $user->id,
        ]);

        Http::fake([
            'https://graph.facebook.com/v22.0/oauth/access_token*' => Http::response([
                'access_token' => 'new-access-token',
                'expires_in' => 3600,
            ], 200),
        ]);

        $response = $this->withHeaders([
            'X-Internal-Token' => 'test-internal-token',
        ])->postJson("/api/internal/v1/platform-connections/{$connection->id}/refresh-token", [
            'force' => true,
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('meta.status', 'ok')
            ->assertJsonPath('data.status', 'ok')
            ->assertJsonPath('data.refreshed', true)
            ->assertJsonPath('data.reason', 'meta_token_refreshed');

        $connection->refresh();

        $this->assertSame('new-access-token', $connection->access_token);
        $this->assertTrue($connection->is_connected);
        $this->assertSame(0, $connection->error_count);
        $this->assertNull($connection->last_error);
        $this->assertNotNull($connection->token_expires_at);
    }

    public function test_internal_refresh_token_endpoint_skips_non_oauth_connection(): void
    {
        $user = User::factory()->admin()->create();
        $platform = Platform::query()->firstOrCreate(
            ['slug' => 'google'],
            [
                'name' => 'Google',
                'api_supported' => true,
                'supports_reach' => true,
                'supports_video_metrics' => true,
                'supports_frequency' => true,
                'supports_leads' => true,
                'is_active' => true,
                'sort_order' => 2,
            ]
        );

        $connection = PlatformConnection::query()->create([
            'platform_id' => $platform->id,
            'account_id' => 'google-acc-01',
            'auth_type' => 'api_key',
            'api_key' => 'api-key-for-google-account',
            'is_connected' => true,
            'error_count' => 0,
            'created_by' => $user->id,
        ]);

        $response = $this->withHeaders([
            'X-Internal-Token' => 'test-internal-token',
        ])->postJson("/api/internal/v1/platform-connections/{$connection->id}/refresh-token");

        $response
            ->assertOk()
            ->assertJsonPath('data.status', 'skipped')
            ->assertJsonPath('data.refreshed', false)
            ->assertJsonPath('data.reason', 'non_oauth_connection');
    }

    public function test_internal_refresh_token_endpoint_records_failure_on_meta_refresh_error(): void
    {
        $user = User::factory()->admin()->create();
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
            'account_id' => 'meta-acc-02',
            'auth_type' => 'oauth2',
            'access_token' => 'broken-token',
            'is_connected' => true,
            'error_count' => 0,
            'created_by' => $user->id,
        ]);

        Http::fake([
            'https://graph.facebook.com/v22.0/oauth/access_token*' => Http::response([
                'error' => ['message' => 'Invalid OAuth access token.'],
            ], 400),
        ]);

        $response = $this->withHeaders([
            'X-Internal-Token' => 'test-internal-token',
        ])->postJson("/api/internal/v1/platform-connections/{$connection->id}/refresh-token", [
            'force' => true,
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.status', 'failed')
            ->assertJsonPath('data.refreshed', false)
            ->assertJsonPath('data.reason', 'refresh_error');

        $connection->refresh();

        $this->assertSame(1, $connection->error_count);
        $this->assertNotNull($connection->last_error);
        $this->assertStringContainsString('Token refresh failed', (string) $connection->last_error);
    }
}