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
                    '-c',
                    "from havas_collectors.tasks.celery_app import app; app.send_task('havas_collectors.tasks.pull_tasks.pull_all_active_campaigns')",
                ];
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
            '-c',
            "from havas_collectors.tasks.celery_app import app; app.send_task('havas_collectors.tasks.pull_tasks.pull_connection_campaigns', kwargs={'connection_id': {$connection->id}})",
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
