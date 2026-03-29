<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CleanupRawResponses extends Command
{
    protected $signature = 'snapshots:cleanup-raw-responses {--months=6}';
    protected $description = 'NULL out raw_response on old snapshots to save storage';

    public function handle(): int
    {
        $months = (int) $this->option('months');
        $cutoff = now()->subMonths($months)->format('Y-m-d');

        $count = DB::table('ad_snapshots')
            ->where('snapshot_date', '<', $cutoff)
            ->whereNotNull('raw_response')
            ->update(['raw_response' => null]);

        $this->info("Cleared raw_response on {$count} snapshots older than {$months} months.");

        return self::SUCCESS;
    }
}
