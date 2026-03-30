<?php

namespace Tests\Unit;

use App\Services\ReportSectionAiCommentaryRunner;
use Mockery;
use Tests\TestCase;

class GenerateReportSectionAiCommentsCommandTest extends TestCase
{
    public function test_command_fails_when_no_target_is_provided(): void
    {
        $this->artisan('reports:generate-ai-comments')
            ->expectsOutput('Provide either a report_id argument, at least one --section-id option, or --pending.')
            ->assertExitCode(1);
    }

    public function test_command_runs_specific_report_sections(): void
    {
        $runner = Mockery::mock(ReportSectionAiCommentaryRunner::class);
        $runner->shouldReceive('runSections')
            ->once()
            ->with([12, 44])
            ->andReturn(2);

        $this->app->instance(ReportSectionAiCommentaryRunner::class, $runner);

        $this->artisan('reports:generate-ai-comments', [
            '--section-id' => [12, 44],
        ])
            ->expectsOutput('Generated AI comments for 2 report section(s).')
            ->assertExitCode(0);
    }

    public function test_command_runs_all_sections_for_a_report(): void
    {
        $runner = Mockery::mock(ReportSectionAiCommentaryRunner::class);
        $runner->shouldReceive('runReport')
            ->once()
            ->with(17)
            ->andReturn(3);

        $this->app->instance(ReportSectionAiCommentaryRunner::class, $runner);

        $this->artisan('reports:generate-ai-comments', [
            'report_id' => 17,
        ])
            ->expectsOutput('Generated AI comments for 3 report section(s) from report 17.')
            ->assertExitCode(0);
    }

    public function test_command_rejects_mixed_target_modes(): void
    {
        $this->artisan('reports:generate-ai-comments', [
            'report_id' => 17,
            '--section-id' => [12],
        ])
            ->expectsOutput('Use only one targeting mode: report_id, --section-id, or --pending.')
            ->assertExitCode(1);
    }

    public function test_command_runs_pending_report_sections(): void
    {
        $runner = Mockery::mock(ReportSectionAiCommentaryRunner::class);
        $runner->shouldReceive('runPending')
            ->once()
            ->andReturn(4);

        $this->app->instance(ReportSectionAiCommentaryRunner::class, $runner);

        $this->artisan('reports:generate-ai-comments', [
            '--pending' => true,
        ])
            ->expectsOutput('Generated AI comments for 4 pending report section(s).')
            ->assertExitCode(0);
    }

    public function test_command_rejects_pending_with_other_target_mode(): void
    {
        $this->artisan('reports:generate-ai-comments', [
            '--pending' => true,
            '--section-id' => [12],
        ])
            ->expectsOutput('Use only one targeting mode: report_id, --section-id, or --pending.')
            ->assertExitCode(1);
    }
}