<?php

namespace App\Services\Web;

use App\Models\PlatformConnection;
use Illuminate\Support\Facades\Process;
use InvalidArgumentException;

class PlatformManualSyncRunner
{
    public function dispatchAll(): void
    {
        Process::path(base_path())
            ->forever()
            ->env($this->environment())
            ->run($this->commandForAll())
            ->throw();
    }

    public function dispatchConnection(int $connectionId): void
    {
        if ($connectionId < 1) {
            throw new InvalidArgumentException('The connection ID must be a positive integer.');
        }

        $connection = PlatformConnection::query()->findOrFail($connectionId);

        if (! $connection->is_connected) {
            throw new InvalidArgumentException('The selected connection is disconnected. Reconnect it before forcing sync.');
        }

        Process::path(base_path())
            ->forever()
            ->env($this->environment())
            ->run($this->commandForConnection($connectionId))
            ->throw();
    }

    private function commandForAll(): array
    {
        return [
            $this->pythonBinary(),
            '-c',
            "from havas_collectors.tasks.celery_app import app; app.send_task('havas_collectors.tasks.pull_tasks.pull_all_active_campaigns')",
        ];
    }

    private function commandForConnection(int $connectionId): array
    {
        return [
            $this->pythonBinary(),
            '-c',
            "from havas_collectors.tasks.celery_app import app; app.send_task('havas_collectors.tasks.pull_tasks.pull_connection_campaigns', kwargs={'connection_id': {$connectionId}})",
        ];
    }

    private function environment(): array
    {
        $baseEnvironment = $this->baseEnvironment();

        $internalApiToken = trim((string) config('services.internal_api_token'));
        if ($internalApiToken === '') {
            throw new InvalidArgumentException('Missing configuration: services.internal_api_token.');
        }

        $apiUrl = trim((string) config('services.ai_report_commentary.api_url', ''));
        if ($apiUrl === '') {
            $appUrl = rtrim(trim((string) config('app.url')), '/');

            if ($appUrl === '') {
                throw new InvalidArgumentException('Missing configuration: services.ai_report_commentary.api_url or app.url.');
            }

            $apiUrl = $appUrl . '/api/internal/v1';
        }

        $databaseConnection = (array) config('database.connections.pgsql', []);
        $databaseUrl = trim((string) env('DATABASE_URL', ''));

        if ($databaseUrl === '') {
            $dbUser = (string) ($databaseConnection['username'] ?? '');
            $dbPassword = (string) ($databaseConnection['password'] ?? '');
            $dbHost = (string) ($databaseConnection['host'] ?? '127.0.0.1');
            $dbPort = (string) ($databaseConnection['port'] ?? '5432');
            $dbName = (string) ($databaseConnection['database'] ?? '');

            if ($dbUser !== '' && $dbName !== '') {
                $databaseUrl = sprintf(
                    'postgresql://%s:%s@%s:%s/%s',
                    rawurlencode($dbUser),
                    rawurlencode($dbPassword),
                    $dbHost,
                    $dbPort,
                    rawurlencode($dbName),
                );
            }
        }

        $redisUrl = trim((string) config('database.redis.default.url', ''));
        if ($redisUrl === '') {
            $redisHost = (string) config('database.redis.default.host', '127.0.0.1');
            $redisPort = (string) config('database.redis.default.port', '6379');
            $redisDatabase = (string) config('database.redis.default.database', '0');
            $redisUrl = sprintf('redis://%s:%s/%s', $redisHost, $redisPort, $redisDatabase);
        }

        return array_merge($baseEnvironment, [
            'LARAVEL_API_URL' => rtrim($apiUrl, '/'),
            'INTERNAL_API_TOKEN' => $internalApiToken,
            'DATABASE_URL' => $databaseUrl,
            'DB_HOST' => (string) ($databaseConnection['host'] ?? '127.0.0.1'),
            'DB_PORT' => (string) ($databaseConnection['port'] ?? '5432'),
            'DB_NAME' => (string) ($databaseConnection['database'] ?? ''),
            'DB_USER' => (string) ($databaseConnection['username'] ?? ''),
            'DB_PASSWORD' => (string) ($databaseConnection['password'] ?? ''),
            'REDIS_URL' => $redisUrl,
        ]);
    }

    private function baseEnvironment(): array
    {
        $environment = getenv();

        if (! is_array($environment)) {
            return [];
        }

        return array_filter(
            $environment,
            static fn ($value): bool => is_scalar($value),
        );
    }

    private function pythonBinary(): string
    {
        return trim((string) config('services.ai_report_commentary.python_binary', 'python')) ?: 'python';
    }
}
