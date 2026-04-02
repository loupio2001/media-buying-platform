<?php

namespace App\Services;

use Illuminate\Process\Exceptions\ProcessFailedException;
use Illuminate\Support\Facades\Process;
use InvalidArgumentException;

class CampaignAiCommentaryRunner
{
    public function runCampaign(int $campaignId, int $days, ?int $platformId = null): void
    {
        if ($campaignId < 1) {
            throw new InvalidArgumentException('The campaign_id argument must be a positive integer.');
        }

        if ($days < 1 || $days > 90) {
            throw new InvalidArgumentException('The days argument must be between 1 and 90.');
        }

        $environment = $this->environment();

        try {
            $this->runCommand($campaignId, $days, $platformId, $environment);
        } catch (ProcessFailedException $exception) {
            $alternateApiUrl = $this->alternateLoopbackApiUrl($environment['LARAVEL_API_URL'] ?? '');

            if (! $this->shouldRetryWithAlternateApiUrl($exception, $alternateApiUrl, $environment['LARAVEL_API_URL'] ?? '')) {
                throw $exception;
            }

            $retryEnvironment = $environment;
            $retryEnvironment['LARAVEL_API_URL'] = $alternateApiUrl;

            $this->runCommand($campaignId, $days, $platformId, $retryEnvironment);
        }
    }

    private function runCommand(int $campaignId, int $days, ?int $platformId, array $environment): void
    {
        Process::path(base_path())
            ->forever()
            ->env($environment)
            ->run($this->command($campaignId, $days, $platformId))
            ->throw();
    }

    private function command(int $campaignId, int $days, ?int $platformId): array
    {
        $command = [
            $this->pythonBinary(),
            '-m',
            $this->pythonModule(),
            (string) $campaignId,
            '--days',
            (string) $days,
        ];

        if ($platformId !== null && $platformId > 0) {
            $command[] = '--platform-id';
            $command[] = (string) $platformId;
        }

        return $command;
    }

    private function environment(): array
    {
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

        return [
            'LARAVEL_API_URL' => rtrim($apiUrl, '/'),
            'INTERNAL_API_TOKEN' => $internalApiToken,
            'AI_FORCE_LLM' => config('services.ai_report_commentary.force_llm', true) ? '1' : '0',
            'NO_PROXY' => '*',
            'no_proxy' => '*',
            'HTTP_PROXY' => '',
            'HTTPS_PROXY' => '',
            'ALL_PROXY' => '',
        ];
    }

    private function pythonBinary(): string
    {
        return trim((string) config('services.ai_report_commentary.python_binary', 'python')) ?: 'python';
    }

    private function pythonModule(): string
    {
        return trim((string) config('services.ai_campaign_commentary.module', 'havas_collectors.ai.campaign_commentary'))
            ?: 'havas_collectors.ai.campaign_commentary';
    }

    private function alternateLoopbackApiUrl(string $apiUrl): string
    {
        if ($apiUrl === '') {
            return '';
        }

        if (str_contains($apiUrl, '://127.0.0.1')) {
            return str_replace('://127.0.0.1', '://localhost', $apiUrl);
        }

        if (str_contains($apiUrl, '://localhost')) {
            return str_replace('://localhost', '://127.0.0.1', $apiUrl);
        }

        return '';
    }

    private function shouldRetryWithAlternateApiUrl(
        ProcessFailedException $exception,
        string $alternateApiUrl,
        string $currentApiUrl,
    ): bool {
        if ($alternateApiUrl === '' || $alternateApiUrl === $currentApiUrl) {
            return false;
        }

        $message = $exception->getMessage();

        return str_contains($message, 'WinError 10106')
            || str_contains($message, 'httpx.ConnectError')
            || str_contains($message, 'httpcore.ConnectError');
    }
}
