<?php

namespace Tests\Unit;

use App\Models\Campaign;
use App\Models\Platform;
use App\Models\Report;
use App\Models\ReportPlatformSection;
use App\Models\User;
use App\Services\ReportSectionAiCommentaryRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class ReportSectionAiCommentaryRunnerTest extends TestCase
{
    use RefreshDatabase;

    public function test_run_pending_invokes_python_runner_for_sections_missing_ai_summary(): void
    {
        config()->set('app.url', 'https://example.test');
        config()->set('services.internal_api_token', 'test-internal-token');
        config()->set('services.ai_report_commentary.python_binary', 'python3');
        config()->set('services.ai_report_commentary.module', 'havas_collectors.ai.report_platform_section_commentary');
        config()->set('services.ai_report_commentary.api_url', '');

        Process::fake();

        $user = User::factory()->create(['role' => 'admin']);
        $campaign = Campaign::factory()->create(['created_by' => $user->id]);
        $firstPlatform = Platform::factory()->create();
        $secondPlatform = Platform::factory()->create();
        $thirdPlatform = Platform::factory()->create();
        $report = Report::query()->create([
            'campaign_id' => $campaign->id,
            'type' => 'mid',
            'period_start' => '2026-03-01',
            'period_end' => '2026-03-31',
            'status' => 'draft',
            'created_by' => $user->id,
        ]);

        $firstPendingSection = ReportPlatformSection::query()->create([
            'report_id' => $report->id,
            'platform_id' => $firstPlatform->id,
        ]);
        $secondPendingSection = ReportPlatformSection::query()->create([
            'report_id' => $report->id,
            'platform_id' => $secondPlatform->id,
        ]);
        ReportPlatformSection::query()->create([
            'report_id' => $report->id,
            'platform_id' => $thirdPlatform->id,
            'ai_summary' => 'Already generated',
        ]);

        $processed = app(ReportSectionAiCommentaryRunner::class)->runPending();

        $this->assertSame(2, $processed);

        Process::assertRan(fn ($process): bool => $process->command === [
            'python3',
            '-m',
            'havas_collectors.ai.report_platform_section_commentary',
            (string) $firstPendingSection->id,
        ]);

        Process::assertRan(fn ($process): bool => $process->command === [
            'python3',
            '-m',
            'havas_collectors.ai.report_platform_section_commentary',
            (string) $secondPendingSection->id,
        ]);
    }

    public function test_run_report_invokes_python_runner_for_each_section(): void
    {
        config()->set('app.url', 'https://example.test');
        config()->set('services.internal_api_token', 'test-internal-token');
        config()->set('services.ai_report_commentary.python_binary', 'python3');
        config()->set('services.ai_report_commentary.module', 'havas_collectors.ai.report_platform_section_commentary');
        config()->set('services.ai_report_commentary.api_url', '');

        Process::fake();

        $user = User::factory()->create(['role' => 'admin']);
        $campaign = Campaign::factory()->create(['created_by' => $user->id]);
        $platform = Platform::factory()->create();
        $secondPlatform = Platform::factory()->create();
        $otherReportPlatform = Platform::factory()->create();
        $report = Report::query()->create([
            'campaign_id' => $campaign->id,
            'type' => 'mid',
            'period_start' => '2026-03-01',
            'period_end' => '2026-03-31',
            'status' => 'draft',
            'created_by' => $user->id,
        ]);
        $otherReport = Report::query()->create([
            'campaign_id' => $campaign->id,
            'type' => 'end',
            'period_start' => '2026-04-01',
            'period_end' => '2026-04-30',
            'status' => 'draft',
            'created_by' => $user->id,
        ]);

        $firstSection = ReportPlatformSection::query()->create([
            'report_id' => $report->id,
            'platform_id' => $platform->id,
        ]);
        $secondSection = ReportPlatformSection::query()->create([
            'report_id' => $report->id,
            'platform_id' => $secondPlatform->id,
        ]);
        ReportPlatformSection::query()->create([
            'report_id' => $otherReport->id,
            'platform_id' => $otherReportPlatform->id,
        ]);

        $processed = app(ReportSectionAiCommentaryRunner::class)->runReport($report->id);

        $this->assertSame(2, $processed);

        Process::assertRanTimes(function ($process): bool {
            return $process->path === base_path()
                && $process->environment['LARAVEL_API_URL'] === 'https://example.test/api/internal/v1'
                && $process->environment['INTERNAL_API_TOKEN'] === 'test-internal-token'
                && $process->command[0] === 'python3'
                && $process->command[1] === '-m'
                && $process->command[2] === 'havas_collectors.ai.report_platform_section_commentary';
        }, 2);

        Process::assertRan(fn ($process): bool => $process->command === [
            'python3',
            '-m',
            'havas_collectors.ai.report_platform_section_commentary',
            (string) $firstSection->id,
        ]);

        Process::assertRan(fn ($process): bool => $process->command === [
            'python3',
            '-m',
            'havas_collectors.ai.report_platform_section_commentary',
            (string) $secondSection->id,
        ]);
    }
}
