<?php

namespace Tests\Feature\Api;

use App\Models\Campaign;
use App\Models\Platform;
use App\Models\Report;
use App\Models\ReportPlatformSection;
use App\Models\User;
use App\Services\ReportSectionAiCommentaryRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class ReportAiCommentsRegenerateTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($this->admin);
    }

    public function test_regenerate_ai_comments_returns_success_with_processed_count_when_report_has_sections(): void
    {
        $campaign = Campaign::factory()->create(['created_by' => $this->admin->id]);
        $report = Report::query()->create([
            'campaign_id' => $campaign->id,
            'type' => 'mid',
            'period_start' => '2026-04-01',
            'period_end' => '2026-05-15',
            'status' => 'draft',
            'created_by' => $this->admin->id,
        ]);

        $firstPlatform = Platform::factory()->create();
        $secondPlatform = Platform::factory()->create();

        $firstSection = ReportPlatformSection::query()->create([
            'report_id' => $report->id,
            'platform_id' => $firstPlatform->id,
        ]);

        $secondSection = ReportPlatformSection::query()->create([
            'report_id' => $report->id,
            'platform_id' => $secondPlatform->id,
        ]);

        $runner = Mockery::mock(ReportSectionAiCommentaryRunner::class);
        $runner->shouldReceive('runSections')
            ->once()
            ->with([$firstSection->id, $secondSection->id])
            ->andReturn(2);
        $this->app->instance(ReportSectionAiCommentaryRunner::class, $runner);

        $response = $this->postJson(route('reports.ai-comments.regenerate', $report));

        $response->assertOk()
            ->assertJsonPath('meta.status', 'regenerated')
            ->assertJsonPath('data.report_id', $report->id)
            ->assertJsonPath('data.count', 2);
    }

    public function test_regenerate_ai_comments_returns_success_with_zero_when_report_has_no_sections(): void
    {
        $campaign = Campaign::factory()->create(['created_by' => $this->admin->id]);
        $report = Report::query()->create([
            'campaign_id' => $campaign->id,
            'type' => 'mid',
            'period_start' => '2026-04-01',
            'period_end' => '2026-05-15',
            'status' => 'draft',
            'created_by' => $this->admin->id,
        ]);

        $runner = Mockery::mock(ReportSectionAiCommentaryRunner::class);
        $runner->shouldNotReceive('runSections');
        $this->app->instance(ReportSectionAiCommentaryRunner::class, $runner);

        $response = $this->postJson(route('reports.ai-comments.regenerate', $report));

        $response->assertOk()
            ->assertJsonPath('meta.status', 'regenerated')
            ->assertJsonPath('data.report_id', $report->id)
            ->assertJsonPath('data.count', 0);
    }
}
