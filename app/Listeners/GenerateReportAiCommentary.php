<?php

namespace App\Listeners;

use App\Enums\Severity;
use App\Events\ReportCreated;
use App\Services\NotificationService;
use App\Services\ReportSectionAiCommentaryRunner;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

class GenerateReportAiCommentary implements ShouldQueue
{
    use InteractsWithQueue;

    public int $tries = 3;

    public int $timeout = 300;

    public int $backoff = 30;

    public bool $afterCommit = true;

    public function __construct(
        private ReportSectionAiCommentaryRunner $runner,
        private NotificationService $notifier,
    ) {
    }

    public function handle(ReportCreated $event): void
    {
        $this->runner->runReport($event->reportId);
    }

    public function failed(ReportCreated $event, Throwable $exception): void
    {
        $context = [
            'listener' => self::class,
            'event' => ReportCreated::class,
            'report_id' => $event->reportId,
            'queue' => $this->job?->getQueue(),
            'attempts' => $this->job?->attempts(),
            'exception_class' => $exception::class,
            'exception_message' => $exception->getMessage(),
            'exception_code' => $exception->getCode(),
        ];

        Log::error('GenerateReportAiCommentary job failed permanently.', $context);

        try {
            $this->notifier->notifyAll(
                type: 'report_ai_failure',
                severity: Severity::Critical->value,
                title: 'Echec definitif de generation IA du rapport',
                message: "Le job de generation IA a echoue pour le rapport #{$event->reportId}.",
                entityType: 'reports',
                entityId: $event->reportId,
                meta: $context,
                actionUrl: "/reports/{$event->reportId}",
                isActionable: true,
            );
        } catch (Throwable $notificationException) {
            Log::error('Unable to dispatch internal failure notification for GenerateReportAiCommentary.', [
                ...$context,
                'notification_exception_class' => $notificationException::class,
                'notification_exception_message' => $notificationException->getMessage(),
            ]);
        }
    }
}