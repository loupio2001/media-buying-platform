<?php

namespace App\Services;

use App\Models\ReportPlatformSection;
use Illuminate\Support\Facades\Process;
use InvalidArgumentException;

class ReportSectionAiCommentaryRunner
{
    public function runPending(): int
    {
        $sectionIds = ReportPlatformSection::query()
            ->whereNull('ai_summary')
            ->orderBy('id')
            ->pluck('id')
            ->all();

        if ($sectionIds === []) {
            return 0;
        }

        return $this->runSections($sectionIds);
    }

    public function runReport(int $reportId): int
    {
        if ($reportId < 1) {
            throw new InvalidArgumentException('The report_id argument must be a positive integer.');
        }

        $sectionIds = ReportPlatformSection::query()
            ->where('report_id', $reportId)
            ->orderBy('id')
            ->pluck('id')
            ->all();

        if ($sectionIds === []) {
            throw new InvalidArgumentException("No report sections found for report_id={$reportId}.");
        }

        return $this->runSections($sectionIds);
    }

    public function runSections(array $sectionIds): int
    {
        $normalizedSectionIds = $this->normalizeSectionIds($sectionIds);

        if ($normalizedSectionIds === []) {
            throw new InvalidArgumentException('Provide at least one valid report section ID.');
        }

        foreach ($normalizedSectionIds as $sectionId) {
            Process::path(base_path())
                ->forever()
                ->env($this->environment())
                ->run($this->commandForSection($sectionId))
                ->throw();
        }

        return count($normalizedSectionIds);
    }

    private function commandForSection(int $sectionId): array
    {
        return [
            $this->pythonBinary(),
            '-m',
            $this->pythonModule(),
            (string) $sectionId,
        ];
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
        ];
    }

    private function normalizeSectionIds(array $sectionIds): array
    {
        $normalized = [];

        foreach ($sectionIds as $sectionId) {
            if ($sectionId === null || $sectionId === '') {
                continue;
            }

            $value = filter_var($sectionId, FILTER_VALIDATE_INT);

            if ($value === false || (int) $value < 1) {
                throw new InvalidArgumentException("Invalid report section ID [{$sectionId}].");
            }

            $normalized[] = (int) $value;
        }

        return array_values(array_unique($normalized));
    }

    private function pythonBinary(): string
    {
        return trim((string) config('services.ai_report_commentary.python_binary', 'python')) ?: 'python';
    }

    private function pythonModule(): string
    {
        return trim((string) config('services.ai_report_commentary.module', 'havas_collectors.ai.report_platform_section_commentary'))
            ?: 'havas_collectors.ai.report_platform_section_commentary';
    }
}