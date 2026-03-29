<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ArchiveActivityLog extends Command
{
    protected $signature = 'activity-log:archive {--years=2}';
    protected $description = 'Archive and purge old activity log records';

    public function handle(): int
    {
        $years = (int) $this->option('years');
        $cutoff = now()->subYears($years);

        $rows = DB::table('activity_log')->where('created_at', '<', $cutoff)->get();
        if ($rows->isEmpty()) {
            $this->info('No activity log rows to archive.');
            return self::SUCCESS;
        }

        $fileName = 'private/activity-log-archive/activity_log_' . now()->format('Ymd_His') . '.json';
        Storage::put($fileName, $rows->toJson(JSON_PRETTY_PRINT));

        $deleted = DB::table('activity_log')->where('created_at', '<', $cutoff)->delete();
        $this->info("Archived and deleted {$deleted} activity log rows to {$fileName}.");

        return self::SUCCESS;
    }
}
