<?php

namespace App\Console\Commands;

use App\Services\ReportSectionAiCommentaryRunner;
use Illuminate\Console\Command;
use Illuminate\Process\Exceptions\ProcessFailedException;
use InvalidArgumentException;

class GenerateReportSectionAiComments extends Command
{
    protected $signature = 'reports:generate-ai-comments
                            {report_id? : Report ID to process all its sections}
                            {--section-id=* : Specific report platform section IDs to process}
                            {--pending : Process report sections that do not yet have AI comments}';

    protected $description = 'Run the Python AI commentary runner for report platform sections';

    public function __construct(private ReportSectionAiCommentaryRunner $runner)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $reportId = $this->argument('report_id');
        $sectionIds = (array) $this->option('section-id');
        $pending = (bool) $this->option('pending');

        if (($reportId === null || $reportId === '') && $sectionIds === [] && ! $pending) {
            $this->error('Provide either a report_id argument, at least one --section-id option, or --pending.');

            return self::FAILURE;
        }

        $modes = [
            $reportId !== null && $reportId !== '',
            $sectionIds !== [],
            $pending,
        ];

        if (count(array_filter($modes)) > 1) {
            $this->error('Use only one targeting mode: report_id, --section-id, or --pending.');

            return self::FAILURE;
        }

        try {
            if ($pending) {
                $processed = $this->runner->runPending();

                $this->info("Generated AI comments for {$processed} pending report section(s).");

                return self::SUCCESS;
            }

            if ($reportId !== null && $reportId !== '') {
                $processed = $this->runner->runReport((int) $reportId);

                $this->info("Generated AI comments for {$processed} report section(s) from report {$reportId}.");

                return self::SUCCESS;
            }

            $processed = $this->runner->runSections($sectionIds);

            $this->info("Generated AI comments for {$processed} report section(s).");

            return self::SUCCESS;
        } catch (InvalidArgumentException|ProcessFailedException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}