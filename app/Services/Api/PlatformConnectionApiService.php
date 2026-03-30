<?php

namespace App\Services\Api;

use App\Models\PlatformConnection;
use Illuminate\Support\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class PlatformConnectionApiService
{
    public function index(int $perPage = 15): LengthAwarePaginator
    {
        return PlatformConnection::query()
            ->with(['platform', 'creator'])
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function store(array $data, int $actorId): PlatformConnection
    {
        $data['created_by'] = $actorId;

        return PlatformConnection::create($data);
    }

    public function update(PlatformConnection $platformConnection, array $data): PlatformConnection
    {
        unset($data['created_by']);

        $platformConnection->update($data);

        return $platformConnection->refresh();
    }

    public function delete(PlatformConnection $platformConnection): void
    {
        $platformConnection->delete();
    }

    public function testHealth(PlatformConnection $platformConnection): array
    {
        $checks = $this->buildHealthChecks($platformConnection);
        $failedChecks = collect($checks)->where('passed', false)->values();

        if ($failedChecks->isEmpty()) {
            $platformConnection->update([
                'is_connected' => true,
                'error_count' => 0,
                'last_error' => null,
                'last_sync_at' => now(),
            ]);
        } else {
            $platformConnection->recordError(
                $failedChecks
                    ->pluck('message')
                    ->implode(' | ')
            );
        }

        $platformConnection = $platformConnection->refresh()->load(['platform', 'creator']);

        return [
            'connection' => $platformConnection,
            'health' => [
                'status' => $failedChecks->isEmpty() ? 'connected' : 'failed',
                'checked_at' => now()->toIso8601String(),
                'checks' => $checks,
            ],
        ];
    }

    private function buildHealthChecks(PlatformConnection $platformConnection): array
    {
        $checks = [
            $this->makeCheck(
                'account_id_present',
                filled($platformConnection->account_id),
                'Account identifier is required.'
            ),
        ];

        if ($platformConnection->auth_type === 'oauth2') {
            $checks[] = $this->makeCheck(
                'access_token_present',
                filled($platformConnection->access_token),
                'OAuth2 access token is missing.'
            );

            $isTokenUsable = $platformConnection->token_expires_at === null
                || Carbon::parse($platformConnection->token_expires_at)->isFuture();

            $checks[] = $this->makeCheck(
                'access_token_not_expired',
                $isTokenUsable,
                'OAuth2 access token is expired.'
            );
        }

        if ($platformConnection->auth_type === 'api_key') {
            $apiKey = (string) ($platformConnection->api_key ?? '');

            $checks[] = $this->makeCheck(
                'api_key_present',
                $apiKey !== '',
                'API key is missing.'
            );

            $checks[] = $this->makeCheck(
                'api_key_min_length',
                strlen($apiKey) >= 12,
                'API key format looks invalid (too short).'
            );
        }

        if ($platformConnection->auth_type === 'service_account') {
            $extraCredentials = (array) ($platformConnection->extra_credentials ?? []);

            $hasUsableCredential = collect($extraCredentials)
                ->contains(function (mixed $value): bool {
                    if (is_string($value)) {
                        return trim($value) !== '';
                    }

                    if (is_numeric($value)) {
                        return true;
                    }

                    return false;
                });

            $checks[] = $this->makeCheck(
                'service_account_credentials_present',
                ! empty($extraCredentials),
                'Service account credentials payload is missing.'
            );

            $checks[] = $this->makeCheck(
                'service_account_credentials_usable',
                $hasUsableCredential,
                'Service account credentials payload is empty or invalid.'
            );
        }

        return $checks;
    }

    private function makeCheck(string $key, bool $passed, string $failureMessage): array
    {
        return [
            'key' => $key,
            'passed' => $passed,
            'message' => $passed ? 'ok' : $failureMessage,
        ];
    }
}