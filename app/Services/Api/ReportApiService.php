<?php

namespace App\Services\Api;

use App\Events\ReportCreated;
use App\Models\CampaignPlatform;
use App\Models\Campaign;
use App\Models\CategoryBenchmark;
use App\Models\Report;
use App\Models\ReportPlatformSection;
use App\Services\ReportGenerator;
use App\Services\ReportSectionAiCommentaryRunner;
use Illuminate\Process\Exceptions\ProcessFailedException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Throwable;

class ReportApiService
{
    private const AI_COMMENT_FIELDS = [
        'ai_summary',
        'ai_highlights',
        'ai_concerns',
        'ai_suggested_action',
        'performance_flags',
        'top_performing_ads',
        'worst_performing_ads',
        'human_notes',
    ];

    public function __construct(
        private ReportGenerator $reportGenerator,
        private ReportSectionAiCommentaryRunner $reportSectionAiCommentaryRunner,
    )
    {
    }

    public function index(int $perPage = 15): LengthAwarePaginator
    {
        return Report::query()->with(['campaign', 'creator', 'reviewer'])->paginate($perPage);
    }

    public function store(array $data, int $userId): Report
    {
        $report = DB::transaction(function () use ($data, $userId): Report {
            $campaign = Campaign::query()->findOrFail($data['campaign_id']);

            $report = $this->reportGenerator->generate(
                $campaign,
                (string) $data['type'],
                (string) $data['period_start'],
                (string) $data['period_end'],
                $userId
            );

            $overrides = Arr::except($data, ['campaign_id', 'type', 'period_start', 'period_end']);

            if ($overrides !== []) {
                $report->fill($overrides);
                $report->save();
            }

            return $report->refresh();
        });

        if ($report->platformSections()->exists()) {
            ReportCreated::dispatch($report->id);
        }

        return $report;
    }

    public function update(Report $report, array $data): Report
    {
        $report->update($data);

        return $report->refresh();
    }

    public function updatePlatformSectionAiComments(ReportPlatformSection $reportPlatformSection, array $data): ReportPlatformSection
    {
        $reportPlatformSection->update(Arr::only($data, self::AI_COMMENT_FIELDS));

        return $reportPlatformSection->refresh();
    }

    public function regenerateAiComments(Report $report): array
    {
        $sectionIds = $report->platformSections()
            ->orderBy('id')
            ->pluck('id')
            ->all();

        if ($sectionIds === []) {
            return [
                'report_id' => $report->id,
                'count' => 0,
            ];
        }

        try {
            return [
                'report_id' => $report->id,
                'count' => $this->reportSectionAiCommentaryRunner->runSections($sectionIds),
                'mode' => 'python',
            ];
        } catch (Throwable $exception) {
            if (! $this->allowLocalFallback() || ! $this->shouldFallbackToLocalCommentary($exception)) {
                throw $exception;
            }

            return [
                'report_id' => $report->id,
                'count' => $this->generateLocalFallbackComments($report),
                'mode' => 'local_fallback',
            ];
        }
    }

    public function getPlatformSectionAiContext(ReportPlatformSection $reportPlatformSection): array
    {
        $section = $reportPlatformSection->loadMissing([
            'platform',
            'report.campaign.client.category',
        ]);

        $report = $section->report;
        $campaign = $report->campaign;
        $platform = $section->platform;
        $client = $campaign->client;
        $category = $client?->category;

        $campaignPlatform = CampaignPlatform::query()
            ->where('campaign_id', $campaign->id)
            ->where('platform_id', $section->platform_id)
            ->first();

        $benchmarks = [];

        if ($client?->category_id) {
            $benchmarks = CategoryBenchmark::query()
                ->where('category_id', $client->category_id)
                ->where('platform_id', $section->platform_id)
                ->get()
                ->mapWithKeys(fn (CategoryBenchmark $benchmark) => [
                    $benchmark->metric => $this->serializeBenchmark($benchmark),
                ])
                ->all();
        }

        return [
            'report_platform_section' => [
                'id' => $section->id,
                'metrics' => $this->serializeSectionMetrics($section),
            ],
            'report' => [
                'id' => $report->id,
                'type' => $report->type,
                'title' => $report->title,
                'status' => $report->status,
                'period' => [
                    'start' => $report->period_start?->toDateString(),
                    'end' => $report->period_end?->toDateString(),
                ],
            ],
            'campaign' => [
                'id' => $campaign->id,
                'name' => $campaign->name,
                'status' => $campaign->status?->value ?? $campaign->status,
                'objective' => $campaign->objective?->value ?? $campaign->objective,
                'start_date' => $campaign->start_date?->toDateString(),
                'end_date' => $campaign->end_date?->toDateString(),
                'currency' => $campaign->currency,
                'client' => [
                    'id' => $client?->id,
                    'name' => $client?->name,
                    'category' => $category ? [
                        'id' => $category->id,
                        'name' => $category->name,
                        'slug' => $category->slug,
                    ] : null,
                ],
            ],
            'platform' => [
                'id' => $platform->id,
                'name' => $platform->name,
                'slug' => $platform->slug,
                'default_metrics' => $platform->default_metrics,
                'supports_reach' => $platform->supports_reach,
                'supports_video_metrics' => $platform->supports_video_metrics,
                'supports_frequency' => $platform->supports_frequency,
                'supports_leads' => $platform->supports_leads,
            ],
            'campaign_platform' => $campaignPlatform ? [
                'id' => $campaignPlatform->id,
                'external_campaign_id' => $campaignPlatform->external_campaign_id,
                'budget' => $this->toFloatOrNull($campaignPlatform->budget),
                'budget_type' => $campaignPlatform->budget_type,
                'currency' => $campaignPlatform->currency,
                'is_active' => $campaignPlatform->is_active,
            ] : null,
            'performance_vs_benchmark' => [
                'overall_status' => $section->performance_vs_benchmark,
                'benchmarks' => $benchmarks,
                'kpi_targets' => $campaign->kpi_targets ?: null,
            ],
        ];
    }

    public function delete(Report $report): void
    {
        $report->delete();
    }

    private function serializeSectionMetrics(ReportPlatformSection $section): array
    {
        return [
            'spend' => $this->toFloatOrNull($section->spend),
            'budget' => $this->toFloatOrNull($section->budget),
            'impressions' => $this->toIntOrNull($section->impressions),
            'reach' => $this->toIntOrNull($section->reach),
            'clicks' => $this->toIntOrNull($section->clicks),
            'link_clicks' => $this->toIntOrNull($section->link_clicks),
            'ctr' => $this->toFloatOrNull($section->ctr),
            'cpm' => $this->toFloatOrNull($section->cpm),
            'cpc' => $this->toFloatOrNull($section->cpc),
            'conversions' => $this->toIntOrNull($section->conversions),
            'cpa' => $this->toFloatOrNull($section->cpa),
            'leads' => $this->toIntOrNull($section->leads),
            'cpl' => $this->toFloatOrNull($section->cpl),
            'video_views' => $this->toIntOrNull($section->video_views),
            'video_completions' => $this->toIntOrNull($section->video_completions),
            'vtr' => $this->toFloatOrNull($section->vtr),
            'frequency' => $this->toFloatOrNull($section->frequency),
            'engagement' => $this->toIntOrNull($section->engagement),
        ];
    }

    private function serializeBenchmark(CategoryBenchmark $benchmark): array
    {
        return [
            'metric' => $benchmark->metric,
            'min_value' => $this->toFloatOrNull($benchmark->min_value),
            'max_value' => $this->toFloatOrNull($benchmark->max_value),
            'unit' => $benchmark->unit,
            'sample_size' => $benchmark->sample_size,
            'last_reviewed_at' => $benchmark->last_reviewed_at?->toDateString(),
            'notes' => $benchmark->notes,
        ];
    }

    private function toFloatOrNull(mixed $value): ?float
    {
        return $value === null ? null : (float) $value;
    }

    private function toIntOrNull(mixed $value): ?int
    {
        return $value === null ? null : (int) $value;
    }

    private function shouldFallbackToLocalCommentary(Throwable $exception): bool
    {
        if (! $exception instanceof ProcessFailedException) {
            return false;
        }

        $message = $exception->getMessage();

        return str_contains($message, 'WinError 10106')
            || str_contains($message, 'httpx.ConnectError')
            || str_contains($message, 'httpcore.ConnectError');
    }

    private function allowLocalFallback(): bool
    {
        return (bool) config('services.ai_report_commentary.allow_local_fallback', false);
    }

    private function generateLocalFallbackComments(Report $report): int
    {
        $sections = $report->platformSections()->with('platform')->get();

        foreach ($sections as $section) {
            $spend = (float) ($section->spend ?? 0);
            $impressions = (int) ($section->impressions ?? 0);
            $clicks = (int) ($section->clicks ?? 0);
            $conversions = (int) ($section->conversions ?? 0);
            $leads = (int) ($section->leads ?? 0);
            $ctr = $section->ctr !== null
                ? (float) $section->ctr
                : ($impressions > 0 ? round(($clicks / $impressions) * 100, 2) : 0.0);

            $highlights = [];
            $concerns = [];
            $actions = [];

            if ($ctr >= 1.5) {
                $highlights[] = sprintf('CTR solide a %.2f%% sur la periode.', $ctr);
            }
            if ($conversions > 0) {
                $highlights[] = sprintf('Volume de conversions positif (%d).', $conversions);
            }
            if ($leads > 0) {
                $highlights[] = sprintf('Generation de leads active (%d).', $leads);
            }

            if ($spend > 0 && $clicks <= 0) {
                $concerns[] = 'Depense engagee sans clic detecte.';
            }
            if ($ctr < 0.6) {
                $concerns[] = sprintf('CTR faible (%.2f%%), possible fatigue creative ou ciblage large.', $ctr);
            }
            if ($spend > 0 && $conversions <= 0) {
                $concerns[] = 'Depense sans conversion observee sur la periode.';
            }

            if ($actions === []) {
                $actions[] = 'Maintenir une revue hebdomadaire des creatives, audiences et placements pour proteger la performance.';
            }

            if ($highlights === []) {
                $highlights[] = 'Donnees limitees: lecture prudente necessaire pour confirmer une tendance.';
            }
            if ($concerns === []) {
                $concerns[] = 'Aucun risque critique detecte avec les metriques disponibles.';
            }

            $periodStart = $report->period_start?->toDateString() ?? '-';
            $periodEnd = $report->period_end?->toDateString() ?? '-';
            $platformName = $section->platform?->name ?? 'plateforme';

            $section->update([
                'ai_summary' => sprintf(
                    'Commentaire genere en mode de secours local pour %s (%s a %s). Depense %.2f, clics %d, conversions %d.',
                    $platformName,
                    $periodStart,
                    $periodEnd,
                    $spend,
                    $clicks,
                    $conversions,
                ),
                'ai_highlights' => $highlights,
                'ai_concerns' => $concerns,
                'ai_suggested_action' => $actions[0],
            ]);
        }

        return $sections->count();
    }
}
