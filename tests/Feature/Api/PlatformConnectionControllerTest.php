<?php

namespace Tests\Feature\Api;

use App\Models\Platform;
use App\Models\PlatformConnection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PlatformConnectionControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_crud_platform_connections_via_api(): void
    {
        $user = User::factory()->admin()->create();
        $platform = Platform::factory()->create();

        Sanctum::actingAs($user);

        $createResponse = $this->postJson('/api/platform-connections', [
            'platform_id' => $platform->id,
            'account_id' => 'acc-001',
            'account_name' => 'Meta BM',
            'auth_type' => 'oauth2',
            'access_token' => 'secret-token',
            'is_connected' => true,
        ]);

        $createResponse
            ->assertCreated()
            ->assertJsonPath('meta.status', 'created')
            ->assertJsonPath('data.account_id', 'acc-001');

        $connectionId = (int) $createResponse->json('data.id');

        $this->assertDatabaseHas('platform_connections', [
            'id' => $connectionId,
            'platform_id' => $platform->id,
            'account_id' => 'acc-001',
            'auth_type' => 'oauth2',
            'created_by' => $user->id,
        ]);

        $indexResponse = $this->getJson('/api/platform-connections');

        $indexResponse
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonCount(1, 'data');

        $showResponse = $this->getJson("/api/platform-connections/{$connectionId}");

        $showResponse
            ->assertOk()
            ->assertJsonPath('data.id', $connectionId)
            ->assertJsonPath('meta.total', 1);

        $updateResponse = $this->patchJson("/api/platform-connections/{$connectionId}", [
            'account_name' => 'Meta BM Updated',
            'is_connected' => false,
            'last_error' => 'Manual test error',
        ]);

        $updateResponse
            ->assertOk()
            ->assertJsonPath('meta.status', 'updated')
            ->assertJsonPath('data.account_name', 'Meta BM Updated');

        $this->assertDatabaseHas('platform_connections', [
            'id' => $connectionId,
            'account_name' => 'Meta BM Updated',
            'is_connected' => false,
            'last_error' => 'Manual test error',
        ]);

        $deleteResponse = $this->deleteJson("/api/platform-connections/{$connectionId}");

        $deleteResponse
            ->assertOk()
            ->assertJsonPath('meta.status', 'deleted')
            ->assertJsonPath('data.id', $connectionId);

        $this->assertDatabaseMissing('platform_connections', [
            'id' => $connectionId,
        ]);
    }

    public function test_auth_type_specific_validation_is_enforced(): void
    {
        $user = User::factory()->admin()->create();
        $platform = Platform::factory()->create();

        Sanctum::actingAs($user);

        $oauthWithoutToken = $this->postJson('/api/platform-connections', [
            'platform_id' => $platform->id,
            'account_id' => 'acc-oauth-missing-token',
            'auth_type' => 'oauth2',
        ]);

        $oauthWithoutToken
            ->assertStatus(422)
            ->assertJsonValidationErrors(['access_token']);

        $apiKeyWithoutKey = $this->postJson('/api/platform-connections', [
            'platform_id' => $platform->id,
            'account_id' => 'acc-api-key-missing',
            'auth_type' => 'api_key',
        ]);

        $apiKeyWithoutKey
            ->assertStatus(422)
            ->assertJsonValidationErrors(['api_key']);

        $serviceAccountWithoutExtra = $this->postJson('/api/platform-connections', [
            'platform_id' => $platform->id,
            'account_id' => 'acc-service-missing',
            'auth_type' => 'service_account',
        ]);

        $serviceAccountWithoutExtra
            ->assertStatus(422)
            ->assertJsonValidationErrors(['extra_credentials']);
    }

    public function test_tokens_are_stored_encrypted_and_not_exposed_in_response(): void
    {
        $user = User::factory()->admin()->create();
        $platform = Platform::factory()->create();

        Sanctum::actingAs($user);

        $plainToken = 'plain-secret-token';

        $response = $this->postJson('/api/platform-connections', [
            'platform_id' => $platform->id,
            'account_id' => 'acc-encrypted',
            'auth_type' => 'oauth2',
            'access_token' => $plainToken,
        ]);

        $response
            ->assertCreated()
            ->assertJsonMissingPath('data.access_token');

        $connectionId = (int) $response->json('data.id');

        $storedRawToken = (string) DB::table('platform_connections')->where('id', $connectionId)->value('access_token');

        $this->assertNotSame($plainToken, $storedRawToken);
        $this->assertNotEmpty($storedRawToken);

        $decryptedToken = PlatformConnection::query()->findOrFail($connectionId)->access_token;
        $this->assertSame($plainToken, $decryptedToken);
    }

    public function test_manual_health_check_marks_connection_as_connected_when_oauth2_credentials_are_valid(): void
    {
        $user = User::factory()->admin()->create();
        $platform = Platform::factory()->create();

        Sanctum::actingAs($user);

        $connection = PlatformConnection::query()->create([
            'platform_id' => $platform->id,
            'account_id' => 'acc-health-oauth',
            'auth_type' => 'oauth2',
            'access_token' => 'valid-access-token-123',
            'token_expires_at' => now()->addHour(),
            'is_connected' => false,
            'error_count' => 3,
            'last_error' => 'Previous API failure',
            'created_by' => $user->id,
        ]);

        $response = $this->postJson(route('platform-connections.test-health', $connection));

        $response
            ->assertOk()
            ->assertJsonPath('meta.status', 'tested')
            ->assertJsonPath('meta.health_status', 'connected')
            ->assertJsonPath('data.connection.id', $connection->id)
            ->assertJsonPath('data.health.status', 'connected');

        $this->assertDatabaseHas('platform_connections', [
            'id' => $connection->id,
            'is_connected' => true,
            'error_count' => 0,
            'last_error' => null,
        ]);
    }

    public function test_manual_health_check_increments_error_count_for_invalid_api_key_connection(): void
    {
        $user = User::factory()->admin()->create();
        $platform = Platform::factory()->create();

        Sanctum::actingAs($user);

        $connection = PlatformConnection::query()->create([
            'platform_id' => $platform->id,
            'account_id' => 'acc-health-api-key',
            'auth_type' => 'api_key',
            'api_key' => 'short',
            'is_connected' => true,
            'error_count' => 1,
            'created_by' => $user->id,
        ]);

        $response = $this->postJson(route('platform-connections.test-health', $connection));

        $response
            ->assertOk()
            ->assertJsonPath('meta.status', 'tested')
            ->assertJsonPath('meta.health_status', 'failed')
            ->assertJsonPath('data.health.status', 'failed');

        $connection->refresh();

        $this->assertSame(2, $connection->error_count);
        $this->assertNotNull($connection->last_error);
        $this->assertStringContainsString('API key format looks invalid', (string) $connection->last_error);
    }

    public function test_manual_health_check_fails_for_missing_service_account_credentials(): void
    {
        $user = User::factory()->admin()->create();
        $platform = Platform::factory()->create();

        Sanctum::actingAs($user);

        $connection = PlatformConnection::query()->create([
            'platform_id' => $platform->id,
            'account_id' => 'acc-health-service-account',
            'auth_type' => 'service_account',
            'extra_credentials' => [],
            'is_connected' => true,
            'error_count' => 0,
            'created_by' => $user->id,
        ]);

        $response = $this->postJson(route('platform-connections.test-health', $connection));

        $response
            ->assertOk()
            ->assertJsonPath('meta.health_status', 'failed')
            ->assertJsonPath('data.health.status', 'failed')
            ->assertJsonPath('data.connection.id', $connection->id);

        $this->assertDatabaseHas('platform_connections', [
            'id' => $connection->id,
            'error_count' => 1,
        ]);
    }
}