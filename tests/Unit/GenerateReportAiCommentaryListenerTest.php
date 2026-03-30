<?php

namespace Tests\Unit;

use App\Enums\Severity;
use App\Events\ReportCreated;
use App\Listeners\GenerateReportAiCommentary;
use App\Services\NotificationService;
use App\Services\ReportSectionAiCommentaryRunner;
use Illuminate\Support\Facades\Log;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class GenerateReportAiCommentaryListenerTest extends TestCase
{
    public function test_listener_runs_runner_for_created_report(): void
    {
        $runner = Mockery::mock(ReportSectionAiCommentaryRunner::class);
        $runner->shouldReceive('runReport')
            ->once()
            ->with(17);

        $notifier = Mockery::mock(NotificationService::class);
        $notifier->shouldNotReceive('notifyAll');

        $listener = new GenerateReportAiCommentary($runner, $notifier);

        $listener->handle(new ReportCreated(17));

        $this->assertTrue($listener->afterCommit);
        $this->assertSame(3, $listener->tries);
    }

    public function test_failed_logs_and_notifies_internal_users(): void
    {
        Log::shouldReceive('error')
            ->once()
            ->with(
                'GenerateReportAiCommentary job failed permanently.',
                Mockery::on(static fn (array $context): bool =>
                    $context['listener'] === GenerateReportAiCommentary::class
                    && $context['event'] === ReportCreated::class
                    && $context['report_id'] === 42
                    && $context['exception_class'] === RuntimeException::class
                    && $context['exception_message'] === 'Process crashed'
                )
            );

        $runner = Mockery::mock(ReportSectionAiCommentaryRunner::class);
        $notifier = Mockery::mock(NotificationService::class);
        $notifier->shouldReceive('notifyAll')
            ->once()
            ->withArgs(static fn (
                string $type,
                string $severity,
                string $title,
                string $message,
                ?string $entityType,
                ?int $entityId,
                ?array $meta,
                ?string $actionUrl,
                bool $isActionable,
            ): bool =>
                $type === 'report_ai_failure'
                && $severity === Severity::Critical->value
                && $title === 'Echec definitif de generation IA du rapport'
                && str_contains($message, 'rapport #42')
                && $entityType === 'reports'
                && $entityId === 42
                && $meta !== null
                && $meta['report_id'] === 42
                && $actionUrl === '/reports/42'
                && $isActionable
            );

        $listener = new GenerateReportAiCommentary($runner, $notifier);

        $listener->failed(new ReportCreated(42), new RuntimeException('Process crashed'));

        $this->assertTrue(true);
    }

    public function test_failed_does_not_throw_when_notification_fails(): void
    {
        Log::shouldReceive('error')->twice();

        $runner = Mockery::mock(ReportSectionAiCommentaryRunner::class);
        $notifier = Mockery::mock(NotificationService::class);
        $notifier->shouldReceive('notifyAll')
            ->once()
            ->andThrow(new RuntimeException('Database unavailable'));

        $listener = new GenerateReportAiCommentary($runner, $notifier);

        $listener->failed(new ReportCreated(77), new RuntimeException('Primary failure'));

        $this->assertTrue(true);
    }
}