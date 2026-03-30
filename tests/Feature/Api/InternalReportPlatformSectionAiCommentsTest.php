<?php

namespace Tests\Feature\Api;

use App\Models\Campaign;
use App\Models\CampaignPlatform;
use App\Models\Category;
use App\Models\CategoryBenchmark;
use App\Models\Client;
use App\Models\Platform;
use App\Models\Report;
use App\Models\ReportPlatformSection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InternalReportPlatformSectionAiCommentsTest extends TestCase
{
    use RefreshDatabase;

    public function test_internal_endpoint_persists_partial_ai_comment_payload(): void
    {
        config()->set('services.internal_api_token', 'test-internal-token');

        $user = User::factory()->create(['role' => 'admin']);
        $campaign = Campaign::factory()->create(['created_by' => $user->id]);
        $platform = Platform::factory()->create();

        $report = Report::query()->create([
            'campaign_id' => $campaign->id,
            'type' => 'end',
            'period_start' => '2026-03-01',
            'period_end' => '2026-03-31',
            'status' => 'draft',
            'created_by' => $user->id,
        ]);

        $reportPlatformSection = ReportPlatformSection::query()->create([
            'report_id' => $report->id,
            'platform_id' => $platform->id,
            'ai_summary' => 'Ancien resume',
            'ai_highlights' => ['Ancien highlight'],
            'human_notes' => 'Note precedente',
        ]);

        $payload = [
            'ai_summary' => 'Nouveau resume IA pour la plateforme.',
            'performance_flags' => ['low_reach', 'high_ctr'],
        ];

        $response = $this->withHeaders([
            'X-Internal-Token' => 'test-internal-token',
        ])->patchJson(
            "/api/internal/v1/report-platform-sections/{$reportPlatformSection->id}/ai-comments",
            $payload
        );

        $response->assertOk()
            ->assertJsonPath('meta.status', 'updated')
            ->assertJsonPath('data.id', $reportPlatformSection->id)
            ->assertJsonPath('data.ai_summary', $payload['ai_summary'])
            ->assertJsonPath('data.performance_flags', $payload['performance_flags'])
            ->assertJsonPath('data.ai_highlights', ['Ancien highlight'])
            ->assertJsonPath('data.human_notes', 'Note precedente');

        $section = $reportPlatformSection->refresh();

        $this->assertSame($payload['ai_summary'], $section->ai_summary);
        $this->assertSame($payload['performance_flags'], $section->performance_flags);
        $this->assertSame(['Ancien highlight'], $section->ai_highlights);
        $this->assertSame('Note precedente', $section->human_notes);
    }

    public function test_internal_endpoint_returns_ai_generation_context_for_report_platform_section(): void
    {
        config()->set('services.internal_api_token', 'test-internal-token');

        $user = User::factory()->create(['role' => 'admin']);
        $category = Category::factory()->create([
            'name' => 'Retail',
            'slug' => 'retail',
        ]);
        $client = Client::factory()->create([
            'category_id' => $category->id,
            'name' => 'Client Test',
        ]);
        $campaign = Campaign::factory()->create([
            'client_id' => $client->id,
            'created_by' => $user->id,
            'name' => 'Spring Launch',
            'status' => 'active',
            'objective' => 'traffic',
            'currency' => 'MAD',
            'kpi_targets' => [
                'ctr' => ['target' => 2.5, 'unit' => '%'],
                'cpc' => ['target' => 4.2, 'unit' => 'MAD'],
            ],
        ]);
        $platform = Platform::factory()->create([
            'name' => 'Meta Ads',
            'slug' => 'meta-ads',
            'default_metrics' => ['spend', 'impressions', 'clicks', 'ctr'],
            'supports_reach' => true,
            'supports_video_metrics' => true,
            'supports_frequency' => true,
            'supports_leads' => true,
        ]);

        $campaignPlatform = CampaignPlatform::query()->create([
            'campaign_id' => $campaign->id,
            'platform_id' => $platform->id,
            'external_campaign_id' => 'meta_123',
            'budget' => 12000,
            'budget_type' => 'lifetime',
            'currency' => 'MAD',
            'is_active' => true,
        ]);

        CategoryBenchmark::query()->create([
            'category_id' => $category->id,
            'platform_id' => $platform->id,
            'metric' => 'ctr',
            'min_value' => 1.5,
            'max_value' => 3.5,
            'unit' => '%',
            'sample_size' => 120,
            'notes' => 'Bench CTR retail Meta',
        ]);

        $report = Report::query()->create([
            'campaign_id' => $campaign->id,
            'type' => 'mid',
            'period_start' => '2026-03-01',
            'period_end' => '2026-03-31',
            'title' => 'Spring Launch - Mid Report',
            'status' => 'draft',
            'created_by' => $user->id,
        ]);

        $reportPlatformSection = ReportPlatformSection::query()->create([
            'report_id' => $report->id,
            'platform_id' => $platform->id,
            'spend' => 2500.50,
            'budget' => 12000,
            'impressions' => 100000,
            'reach' => 72000,
            'clicks' => 2600,
            'link_clicks' => 2100,
            'ctr' => 2.6,
            'cpm' => 25,
            'cpc' => 0.96,
            'conversions' => 80,
            'cpa' => 31.25,
            'leads' => 35,
            'cpl' => 71.44,
            'video_views' => 18000,
            'video_completions' => 12500,
            'vtr' => 18,
            'frequency' => 1.39,
            'engagement' => 6400,
            'performance_vs_benchmark' => 'above',
        ]);

        $response = $this->withHeaders([
            'X-Internal-Token' => 'test-internal-token',
        ])->getJson(
            "/api/internal/v1/report-platform-sections/{$reportPlatformSection->id}/ai-context"
        );

        $response->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.report_platform_section.id', $reportPlatformSection->id)
            ->assertJsonPath('data.report_platform_section.metrics.spend', 2500.5)
            ->assertJsonPath('data.report_platform_section.metrics.ctr', 2.6)
            ->assertJsonPath('data.report.id', $report->id)
            ->assertJsonPath('data.report.period.start', '2026-03-01')
            ->assertJsonPath('data.report.period.end', '2026-03-31')
            ->assertJsonPath('data.campaign.id', $campaign->id)
            ->assertJsonPath('data.campaign.name', 'Spring Launch')
            ->assertJsonPath('data.campaign.client.name', 'Client Test')
            ->assertJsonPath('data.platform.id', $platform->id)
            ->assertJsonPath('data.platform.name', 'Meta Ads')
            ->assertJsonPath('data.campaign_platform.id', $campaignPlatform->id)
            ->assertJsonPath('data.campaign_platform.external_campaign_id', 'meta_123')
            ->assertJsonPath('data.performance_vs_benchmark.overall_status', 'above')
            ->assertJsonPath('data.performance_vs_benchmark.benchmarks.ctr.min_value', 1.5)
            ->assertJsonPath('data.performance_vs_benchmark.benchmarks.ctr.max_value', 3.5)
            ->assertJsonPath('data.performance_vs_benchmark.kpi_targets.ctr.target', 2.5)
            ->assertJsonPath('data.performance_vs_benchmark.kpi_targets.cpc.target', 4.2);
    }
}