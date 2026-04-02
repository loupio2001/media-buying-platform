<?php

namespace Tests\Unit;

use App\Models\Platform;
use App\Models\PlatformConnection;
use App\Models\User;
use App\Services\Web\PlatformManualSyncRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Process;
use InvalidArgumentException;
use Tests\TestCase;

class PlatformManualSyncRunnerTest extends TestCase
{
    use RefreshDatabase;

    public function test_dispatch_all_queues_pull_all_task(): void
    {
        config()->set('app.url', 'https://example.test');
        config()->set('services.internal_api_token', 'test-internal-token');
        config()->set('services.ai_report_commentary.python_binary', 'python3');
        config()->set('services.ai_report_commentary.api_url', 'https://example.test/api/internal/v1');

        Process::fake();

        app(PlatformManualSyncRunner::class)->dispatchAll();

        Process::assertRan(function ($process): bool {
            return $process->path === base_path()
                && $process->environment['LARAVEL_API_URL'] === 'https://example.test/api/internal/v1'
                && $process->environment['INTERNAL_API_TOKEN'] === 'test-internal-token'
                && $process->command === [
                    'python3',
                    '-m',
                    'havas_collectors.tasks.manual_sync',
                    '--all',
                ];
        });
    }

    public function test_dispatch_all_falls_back_to_localhost_api_port(): void
    {
        config()->set('app.url', 'http://localhost');
        config()->set('services.internal_api_token', 'test-internal-token');
        config()->set('services.ai_report_commentary.python_binary', 'python3');
        config()->set('services.ai_report_commentary.api_url', '');

        Process::fake();

        app(PlatformManualSyncRunner::class)->dispatchAll();

        Process::assertRan(function ($process): bool {
            return $process->environment['LARAVEL_API_URL'] === 'http://localhost:8000/api/internal/v1';
        });
    }

    public function test_dispatch_connection_queues_connection_task(): void
    {
        config()->set('app.url', 'https://example.test');
        config()->set('services.internal_api_token', 'test-internal-token');
        config()->set('services.ai_report_commentary.python_binary', 'python3');
        config()->set('services.ai_report_commentary.api_url', 'https://example.test/api/internal/v1');

        Process::fake();

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
            'account_id' => 'meta-account-01',
            'account_name' => 'Meta Account 01',
            'auth_type' => 'oauth2',
            'access_token' => 'test-token',
            'is_connected' => true,
            'error_count' => 0,
            'created_by' => $user->id,
        ]);

        app(PlatformManualSyncRunner::class)->dispatchConnection((int) $connection->id);

        Process::assertRan(fn ($process): bool => $process->command === [
            'python3',
            '-m',
            'havas_collectors.tasks.manual_sync',
            '--connection-id',
            (string) $connection->id,
        ]);
    }

    public function test_dispatch_connection_rejects_disconnected_connection(): void
    {
        config()->set('app.url', 'https://example.test');
        config()->set('services.internal_api_token', 'test-internal-token');
        config()->set('services.ai_report_commentary.python_binary', 'python3');

        Process::fake();

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
            'account_id' => 'meta-account-02',
            'account_name' => 'Meta Account 02',
            'auth_type' => 'oauth2',
            'access_token' => 'test-token',
            'is_connected' => false,
            'error_count' => 0,
            'created_by' => $user->id,
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The selected connection is disconnected');

        app(PlatformManualSyncRunner::class)->dispatchConnection((int) $connection->id);

        Process::assertNothingRan();
    }
}
