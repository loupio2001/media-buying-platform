<?php

namespace Tests\Feature;

use App\Models\Platform;
use App\Models\PlatformConnection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class WebPlatformConnectionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login_for_platform_connections_settings(): void
    {
        $response = $this->get(route('web.platform-connections.index'));

        $response->assertRedirect(route('login'));
    }

    public function test_admin_and_manager_can_view_platform_connections_settings(): void
    {
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

        foreach ([User::factory()->admin()->create(), User::factory()->create(['role' => 'manager'])] as $user) {
            PlatformConnection::query()->create([
                'platform_id' => $platform->id,
                'account_id' => 'act-' . $user->id,
                'account_name' => 'Account ' . $user->id,
                'auth_type' => 'api_key',
                'api_key' => 'secret-' . $user->id,
                'is_connected' => true,
                'error_count' => 0,
                'created_by' => $user->id,
            ]);

            $response = $this->actingAs($user)->get(route('web.platform-connections.index'));

            $response
                ->assertOk()
                ->assertSee('Platform Connections')
                ->assertSee('Existing connections');
        }
    }

    public function test_viewer_is_forbidden_from_platform_connections_settings(): void
    {
        /** @var User $viewer */
        $viewer = User::factory()->create(['role' => 'viewer']);

        $response = $this->actingAs($viewer)->get(route('web.platform-connections.index'));

        $response->assertStatus(403);
    }

    public function test_admin_can_create_update_and_delete_manual_connection(): void
    {
        $admin = User::factory()->admin()->create();
        $platform = Platform::query()->firstOrCreate(
            ['slug' => 'google'],
            [
                'name' => 'Google Ads',
                'api_supported' => true,
                'supports_reach' => true,
                'supports_video_metrics' => true,
                'supports_frequency' => true,
                'supports_leads' => true,
                'is_active' => true,
                'sort_order' => 2,
            ]
        );

        $storeResponse = $this->actingAs($admin)->post(route('web.platform-connections.store'), [
            'platform_id' => $platform->id,
            'account_id' => 'google-account-01',
            'account_name' => 'Google Account 01',
            'auth_type' => 'api_key',
            'api_key' => 'google-api-key',
        ]);

        $storeResponse
            ->assertRedirect(route('web.platform-connections.index'))
            ->assertSessionHas('status');

        $this->assertDatabaseHas('platform_connections', [
            'platform_id' => $platform->id,
            'account_id' => 'google-account-01',
            'auth_type' => 'api_key',
            'created_by' => $admin->id,
        ]);

        $connection = PlatformConnection::query()->where('account_id', 'google-account-01')->firstOrFail();

        $updateResponse = $this->actingAs($admin)->patch(route('web.platform-connections.update', $connection), [
            'is_connected' => false,
        ]);

        $updateResponse
            ->assertRedirect(route('web.platform-connections.index'))
            ->assertSessionHas('status');

        $this->assertDatabaseHas('platform_connections', [
            'id' => $connection->id,
            'is_connected' => false,
        ]);

        $deleteResponse = $this->actingAs($admin)->delete(route('web.platform-connections.destroy', $connection));

        $deleteResponse
            ->assertRedirect(route('web.platform-connections.index'))
            ->assertSessionHas('status');

        $this->assertDatabaseMissing('platform_connections', ['id' => $connection->id]);
    }

    public function test_admin_can_dispatch_manual_sync_for_all_platforms(): void
    {
        config()->set('app.url', 'https://example.test');
        config()->set('services.internal_api_token', 'test-internal-token');
        config()->set('services.ai_report_commentary.python_binary', 'python3');
        Process::fake();

        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)
            ->from(route('web.platform-connections.index'))
            ->post(route('web.platform-connections.sync-all'));

        $response
            ->assertRedirect(route('web.platform-connections.index'))
            ->assertSessionHas('status');

        Process::assertRan(fn ($process): bool => $process->command === [
            'python3',
            '-c',
            "from havas_collectors.tasks.celery_app import app; app.send_task('havas_collectors.tasks.pull_tasks.pull_all_active_campaigns')",
        ]);
    }

    public function test_admin_can_dispatch_manual_sync_for_single_connection(): void
    {
        config()->set('app.url', 'https://example.test');
        config()->set('services.internal_api_token', 'test-internal-token');
        config()->set('services.ai_report_commentary.python_binary', 'python3');
        Process::fake();

        $admin = User::factory()->admin()->create();
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
            'account_id' => 'meta-account-99',
            'account_name' => 'Meta Account 99',
            'auth_type' => 'oauth2',
            'access_token' => 'test-token',
            'is_connected' => true,
            'error_count' => 0,
            'created_by' => $admin->id,
        ]);

        $response = $this->actingAs($admin)
            ->from(route('web.platform-connections.index'))
            ->post(route('web.platform-connections.sync', $connection));

        $response
            ->assertRedirect(route('web.platform-connections.index'))
            ->assertSessionHas('status');

        Process::assertRan(fn ($process): bool => $process->command === [
            'python3',
            '-c',
            "from havas_collectors.tasks.celery_app import app; app.send_task('havas_collectors.tasks.pull_tasks.pull_connection_campaigns', kwargs={'connection_id': {$connection->id}})",
        ]);
    }
}