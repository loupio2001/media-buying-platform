<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\Report;
use App\Models\User;
use App\Services\Api\ReportApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class WebReportAiCommentsRegenerateTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_regenerate_ai_comments_from_web_route(): void
    {
        /** @var User $admin */
        $admin = User::factory()->create(['role' => 'admin']);
        $campaign = Campaign::factory()->create(['created_by' => $admin->id]);
        $report = Report::query()->create([
            'campaign_id' => $campaign->id,
            'type' => 'weekly',
            'period_start' => '2026-03-23',
            'period_end' => '2026-03-29',
            'status' => 'draft',
            'created_by' => $admin->id,
        ]);

        $service = Mockery::mock(ReportApiService::class);
        $service->shouldReceive('regenerateAiComments')
            ->once()
            ->withArgs(fn (Report $resolvedReport): bool => $resolvedReport->id === $report->id)
            ->andReturn([
                'report_id' => $report->id,
                'count' => 1,
            ]);
        $this->app->instance(ReportApiService::class, $service);

        $response = $this->actingAs($admin)
            ->postJson(route('web.reports.ai-comments.regenerate', $report));

        $response->assertOk()
            ->assertJsonPath('meta.status', 'regenerated')
            ->assertJsonPath('data.report_id', $report->id)
            ->assertJsonPath('data.count', 1);
    }

    public function test_viewer_cannot_regenerate_ai_comments_from_web_route(): void
    {
        /** @var User $viewer */
        $viewer = User::factory()->create(['role' => 'viewer']);
        $campaign = Campaign::factory()->create(['created_by' => $viewer->id]);
        $report = Report::query()->create([
            'campaign_id' => $campaign->id,
            'type' => 'weekly',
            'period_start' => '2026-03-23',
            'period_end' => '2026-03-29',
            'status' => 'draft',
            'created_by' => $viewer->id,
        ]);

        $response = $this->actingAs($viewer)
            ->postJson(route('web.reports.ai-comments.regenerate', $report));

        $response->assertForbidden();
    }

    public function test_web_route_returns_valid_json_when_exception_message_contains_invalid_utf8(): void
    {
        /** @var User $admin */
        $admin = User::factory()->create(['role' => 'admin']);
        $campaign = Campaign::factory()->create(['created_by' => $admin->id]);
        $report = Report::query()->create([
            'campaign_id' => $campaign->id,
            'type' => 'weekly',
            'period_start' => '2026-03-23',
            'period_end' => '2026-03-29',
            'status' => 'draft',
            'created_by' => $admin->id,
        ]);

        $service = Mockery::mock(ReportApiService::class);
        $service->shouldReceive('regenerateAiComments')
            ->once()
            ->andThrow(new RuntimeException("Invalid bytes: \xB1\x31"));
        $this->app->instance(ReportApiService::class, $service);

        $response = $this->actingAs($admin)
            ->postJson(route('web.reports.ai-comments.regenerate', $report));

        $response->assertStatus(500)
            ->assertJsonPath('message', 'Failed to regenerate AI comments.')
            ->assertJsonStructure(['message', 'error']);
    }
}
