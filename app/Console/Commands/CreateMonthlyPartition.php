<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CreateMonthlyPartition extends Command
{
    protected $signature = 'partitions:create-monthly
                            {--past-months=1 : Number of previous months to ensure}
                            {--future-months=3 : Number of upcoming months to ensure}
                            {--months= : Legacy alias for future-months}
                            {--check-only : Verify expected partitions without creating them}';
    protected $description = 'Ensure monthly ad_snapshots partitions exist (backfill + fill-forward)';

    public function handle(): int
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->comment('Skipping partition maintenance: command is PostgreSQL-only.');

            return self::SUCCESS;
        }

        $pastMonths = max(0, (int) $this->option('past-months'));
        $futureMonths = max(0, (int) $this->option('future-months'));

        $legacyMonths = $this->option('months');
        if ($legacyMonths !== null && $legacyMonths !== '') {
            $futureMonths = max(0, (int) $legacyMonths);
            $this->comment('Option --months is deprecated, use --future-months instead.');
        }

        if (! $this->parentTableExists()) {
            $this->error('Missing parent table: ad_snapshots. Run migrations first.');

            return self::FAILURE;
        }

        $isCheckOnly = (bool) $this->option('check-only');
        $currentMonth = Carbon::now('Africa/Casablanca')->startOfMonth();
        $expectedPartitions = [];

        for ($offset = -$pastMonths; $offset <= $futureMonths; $offset++) {
            $target = $currentMonth->copy()->addMonths($offset);
            $next = $target->copy()->addMonth();

            $expectedPartitions[] = [
                'name' => 'ad_snapshots_' . $target->format('Y_m'),
                'from' => $target->format('Y-m-d'),
                'to' => $next->format('Y-m-d'),
            ];
        }

        if ($isCheckOnly) {
            $missingPartitions = array_filter(
                $expectedPartitions,
                fn (array $partition): bool => ! $this->partitionExists($partition['name'])
            );

            if (empty($missingPartitions)) {
                $this->info('Partition check passed: all expected monthly partitions exist.');

                return self::SUCCESS;
            }

            foreach ($missingPartitions as $partition) {
                $this->error('Missing partition: ' . $partition['name']);
            }

            return self::FAILURE;
        }

        foreach ($expectedPartitions as $partition) {
            if ($this->partitionExists($partition['name'])) {
                $this->comment('Partition already exists: ' . $partition['name']);

                continue;
            }

            try {
                DB::statement(
                    "CREATE TABLE IF NOT EXISTS {$partition['name']} PARTITION OF ad_snapshots FOR VALUES FROM ('{$partition['from']}') TO ('{$partition['to']}')"
                );
                $this->info('Created partition: ' . $partition['name']);
            } catch (\Throwable $e) {
                $this->error('Failed partition ' . $partition['name'] . ': ' . $e->getMessage());

                return self::FAILURE;
            }
        }

        $this->info('Partition maintenance completed successfully.');

        return self::SUCCESS;
    }

    private function parentTableExists(): bool
    {
        $result = DB::selectOne('SELECT to_regclass(?) AS regclass', ['public.ad_snapshots']);

        return ! empty($result?->regclass);
    }

    private function partitionExists(string $partitionName): bool
    {
        $result = DB::selectOne('SELECT to_regclass(?) AS regclass', ['public.' . $partitionName]);

        return ! empty($result?->regclass);
    }
}
