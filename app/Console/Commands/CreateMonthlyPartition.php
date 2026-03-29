<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CreateMonthlyPartition extends Command
{
    protected $signature = 'partitions:create-monthly {--months=3 : Number of future months to create}';
    protected $description = 'Create monthly ad_snapshots partitions for upcoming months';

    public function handle(): int
    {
        $months = (int) $this->option('months');
        $current = Carbon::now()->startOfMonth();

        for ($i = 0; $i < $months; $i++) {
            $target = $current->copy()->addMonths($i);
            $next = $target->copy()->addMonth();
            $name = 'ad_snapshots_' . $target->format('Y_m');
            $from = $target->format('Y-m-d');
            $to = $next->format('Y-m-d');

            try {
                DB::statement(
                    "CREATE TABLE IF NOT EXISTS {$name} PARTITION OF ad_snapshots FOR VALUES FROM ('{$from}') TO ('{$to}')"
                );
                $this->info("Created partition: {$name}");
            } catch (\Exception $e) {
                if (str_contains($e->getMessage(), 'already exists')) {
                    $this->comment("Partition already exists: {$name}");
                } else {
                    $this->error("Failed: {$name} - {$e->getMessage()}");
                }
            }
        }

        return self::SUCCESS;
    }
}
