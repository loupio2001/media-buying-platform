<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\DB;
use stdClass;
use Tests\TestCase;

class CreateMonthlyPartitionCommandTest extends TestCase
{
    public function test_command_accepts_backfill_and_fill_forward_options(): void
    {
        DB::shouldReceive('getDriverName')
            ->once()
            ->andReturn('sqlite');

        $this->artisan('partitions:create-monthly', [
            '--past-months' => 2,
            '--future-months' => 4,
        ])->assertExitCode(0);
    }

    public function test_check_only_returns_failure_when_expected_partition_is_missing(): void
    {
        DB::shouldReceive('getDriverName')
            ->once()
            ->andReturn('pgsql');

        DB::shouldReceive('selectOne')
            ->once()
            ->with('SELECT to_regclass(?) AS regclass', ['public.ad_snapshots'])
            ->andReturn($this->regclassResult('ad_snapshots'));

        DB::shouldReceive('selectOne')
            ->once()
            ->withArgs(function (string $query, array $bindings): bool {
                return $query === 'SELECT to_regclass(?) AS regclass'
                    && count($bindings) === 1
                    && str_starts_with($bindings[0], 'public.ad_snapshots_');
            })
            ->andReturn($this->regclassResult(null));

        DB::shouldReceive('statement')->never();

        $this->artisan('partitions:create-monthly', [
            '--past-months' => 0,
            '--future-months' => 0,
            '--check-only' => true,
        ])->assertExitCode(1);
    }

    public function test_non_pgsql_check_only_exits_cleanly_without_db_work(): void
    {
        DB::shouldReceive('getDriverName')
            ->once()
            ->andReturn('sqlite');

        DB::shouldReceive('selectOne')->never();
        DB::shouldReceive('statement')->never();

        $this->artisan('partitions:create-monthly', [
            '--check-only' => true,
        ])->assertExitCode(0);
    }

    public function test_check_only_returns_success_when_expected_partition_exists(): void
    {
        DB::shouldReceive('getDriverName')
            ->once()
            ->andReturn('pgsql');

        DB::shouldReceive('selectOne')
            ->once()
            ->with('SELECT to_regclass(?) AS regclass', ['public.ad_snapshots'])
            ->andReturn($this->regclassResult('ad_snapshots'));

        DB::shouldReceive('selectOne')
            ->once()
            ->withArgs(function (string $query, array $bindings): bool {
                return $query === 'SELECT to_regclass(?) AS regclass'
                    && count($bindings) === 1
                    && str_starts_with($bindings[0], 'public.ad_snapshots_');
            })
            ->andReturn($this->regclassResult('ad_snapshots_2026_03'));

        DB::shouldReceive('statement')->never();

        $this->artisan('partitions:create-monthly', [
            '--past-months' => 0,
            '--future-months' => 0,
            '--check-only' => true,
        ])->assertExitCode(0);
    }

    private function regclassResult(?string $value): stdClass
    {
        $result = new stdClass();
        $result->regclass = $value;

        return $result;
    }
}
