<?php

namespace Tests\Feature;

use App\Models\Platform;
use App\Models\PlatformConnection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PlatformConnectionOAuthControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.meta_ads.client_id', 'meta-client-id');
        config()->set('services.meta_ads.client_secret', 'meta-client-secret');
        config()->set('services.meta_ads.redirect_uri', 'http://127.0.0.1:8000/settings/platform-connections/meta/callback');
        config()->set('services.meta_ads.scopes', ['ads_read', 'ads_management']);
        config()->set('services.google_ads.client_id', 'google-client-id');
        config()->set('services.google_ads.client_secret', 'google-client-secret');
        config()->set('services.google_ads.developer_token', 'google-developer-token');
        config()->set('services.google_ads.redirect_uri', 'http://127.0.0.1:8000/settings/platform-connections/google/callback');
        config()->set('services.google_ads.scopes', ['https://www.googleapis.com/auth/adwords']);
    }

    public function test_admin_can_start_meta_oauth_flow(): void
    {
        $user = User::factory()->admin()->create();
        $this->ensureMetaPlatform();

        $response = $this->actingAs($user)->get(route('web.platform-connections.oauth.authorize', ['platform' => 'meta']));

        $response->assertRedirect();
        $location = (string) $response->headers->get('Location');

        $this->assertStringContainsString('facebook.com/v22.0/dialog/oauth', $location);
        $this->assertStringContainsString('client_id=meta-client-id', $location);
        $this->assertStringContainsString('scope=ads_read%2Cads_management', $location);
        $this->assertNotEmpty(session('platform_oauth.meta.state'));
    }

    public function test_meta_oauth_callback_creates_platform_connection(): void
    {
        $user = User::factory()->admin()->create();
        $platform = $this->ensureMetaPlatform();

        Http::fake([
            'https://graph.facebook.com/v22.0/oauth/access_token*' => Http::response([
                'access_token' => 'meta-access-token',
                'token_type' => 'bearer',
                'expires_in' => 3600,
            ], 200),
            'https://graph.facebook.com/v22.0/me/adaccounts*' => Http::response([
                'data' => [
                    [
                        'account_id' => 'act_998877',
                        'name' => 'Meta Business Account',
                    ],
                ],
            ], 200),
        ]);

        $state = 'fixed-state-value';

        $response = $this->actingAs($user)
            ->withSession([
                'platform_oauth.meta.state' => $state,
                'platform_oauth.meta.user_id' => $user->id,
            ])
            ->get(route('web.platform-connections.oauth.callback', ['platform' => 'meta', 'state' => $state, 'code' => 'sample-code']));

        $response->assertRedirect(route('web.platform-connections.index'));
        $response->assertSessionHas('status');

        $this->assertDatabaseHas('platform_connections', [
            'platform_id' => $platform->id,
            'account_id' => '998877',
            'account_name' => 'Meta Business Account',
            'auth_type' => 'oauth2',
            'created_by' => $user->id,
            'is_connected' => true,
            'error_count' => 0,
        ]);

        $connection = PlatformConnection::query()->firstOrFail();
        $this->assertSame('meta-access-token', $connection->access_token);
    }

    public function test_meta_oauth_callback_rejects_invalid_state(): void
    {
        $user = User::factory()->admin()->create();
        $this->ensureMetaPlatform();

        $response = $this->actingAs($user)
            ->withSession([
                'platform_oauth.meta.state' => 'expected-state',
                'platform_oauth.meta.user_id' => $user->id,
            ])
            ->get(route('web.platform-connections.oauth.callback', ['platform' => 'meta', 'state' => 'wrong-state', 'code' => 'sample-code']));

        $response->assertRedirect(route('web.platform-connections.index'));
        $response->assertSessionHas('error');

        $this->assertDatabaseCount('platform_connections', 0);
    }

    public function test_google_oauth_callback_prompts_for_customer_selection(): void
    {
        $user = User::factory()->admin()->create();
        $this->ensureGooglePlatform();

        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'google-access-token',
                'token_type' => 'Bearer',
                'expires_in' => 3600,
                'refresh_token' => 'google-refresh-token',
            ], 200),
            'https://googleads.googleapis.com/v23/customers:listAccessibleCustomers' => Http::response([
                'resourceNames' => [
                    'customers/1234567890',
                    'customers/9876543210',
                ],
            ], 200),
        ]);

        $state = 'fixed-state-value';

        $response = $this->actingAs($user)
            ->withSession([
                'platform_oauth.google.state' => $state,
                'platform_oauth.google.user_id' => $user->id,
            ])
            ->get(route('web.platform-connections.oauth.callback', ['platform' => 'google', 'state' => $state, 'code' => 'sample-code']));

        $response->assertRedirect(route('web.platform-connections.oauth.google.select', ['platform' => 'google']));
        $response->assertSessionHas('status');

        $pending = session('platform_oauth.google.pending');
        $this->assertIsArray($pending);
        $this->assertSame($user->id, $pending['user_id']);
        $this->assertSame('google-access-token', $pending['token_payload']['access_token']);
        $this->assertCount(2, $pending['customers']);

        $this->assertDatabaseCount('platform_connections', 0);
    }

    public function test_google_customer_selection_finalizes_connection(): void
    {
        $user = User::factory()->admin()->create();
        $platform = $this->ensureGooglePlatform();

        $response = $this->actingAs($user)
            ->withSession([
                'platform_oauth.google.pending' => [
                    'user_id' => $user->id,
                    'platform_id' => $platform->id,
                    'token_payload' => [
                        'access_token' => 'google-access-token',
                        'refresh_token' => 'google-refresh-token',
                        'expires_in' => 3600,
                    ],
                    'customers' => [
                        [
                            'account_id' => '1234567890',
                            'account_name' => 'Google Ads Account 1234567890',
                        ],
                        [
                            'account_id' => '9876543210',
                            'account_name' => 'Google Ads Account 9876543210',
                        ],
                    ],
                ],
            ])
            ->post(route('web.platform-connections.oauth.google.confirm', ['platform' => 'google']), [
                'account_id' => '9876543210',
            ]);

        $response->assertRedirect(route('web.platform-connections.index'));
        $response->assertSessionHas('status');

        $this->assertDatabaseHas('platform_connections', [
            'platform_id' => $platform->id,
            'account_id' => '9876543210',
            'account_name' => 'Google Ads Account 9876543210',
            'auth_type' => 'oauth2',
            'created_by' => $user->id,
            'is_connected' => true,
            'error_count' => 0,
        ]);

        $connection = PlatformConnection::query()->firstOrFail();
        $this->assertSame('google-access-token', $connection->access_token);
        $this->assertNull(session('platform_oauth.google.pending'));
    }

    private function ensureMetaPlatform(): Platform
    {
        return Platform::query()->firstOrCreate(
            ['slug' => 'meta'],
            [
                'name' => 'Meta',
                'api_supported' => true,
                'supports_reach' => true,
                'supports_video_metrics' => true,
                'supports_frequency' => true,
                'supports_leads' => true,
                'is_active' => true,
                'sort_order' => 10,
            ]
        );
    }

    private function ensureGooglePlatform(): Platform
    {
        return Platform::query()->firstOrCreate(
            ['slug' => 'google'],
            [
                'name' => 'Google Ads',
                'api_supported' => true,
                'supports_reach' => true,
                'supports_video_metrics' => true,
                'supports_frequency' => true,
                'supports_leads' => true,
                'is_active' => true,
                'sort_order' => 20,
            ]
        );
    }
}