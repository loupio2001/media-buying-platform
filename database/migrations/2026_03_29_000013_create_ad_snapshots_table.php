<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement(<<<'SQL'
            CREATE TABLE ad_snapshots (
                id BIGSERIAL,
                ad_id BIGINT NOT NULL REFERENCES ads(id) ON DELETE CASCADE,
                snapshot_date DATE NOT NULL,
                granularity VARCHAR(15) NOT NULL,
                impressions BIGINT DEFAULT 0,
                reach BIGINT,
                frequency DECIMAL(8,4),
                clicks BIGINT DEFAULT 0,
                link_clicks BIGINT,
                landing_page_views BIGINT,
                ctr DECIMAL(8,4),
                spend DECIMAL(12,2) DEFAULT 0,
                cpm DECIMAL(10,4),
                cpc DECIMAL(10,4),
                conversions INT,
                cpa DECIMAL(10,4),
                leads INT,
                cpl DECIMAL(10,4),
                video_views BIGINT,
                video_completions BIGINT,
                vtr DECIMAL(8,4),
                engagement BIGINT,
                engagement_rate DECIMAL(8,4),
                thumb_stop_rate DECIMAL(8,4),
                custom_metrics JSONB,
                raw_response JSONB,
                source VARCHAR(10) NOT NULL DEFAULT 'api',
                pulled_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                CONSTRAINT chk_ad_snapshots_granularity CHECK (granularity IN ('daily', 'cumulative')),
                CONSTRAINT chk_ad_snapshots_source CHECK (source IN ('api', 'manual'))
            ) PARTITION BY RANGE (snapshot_date)
        SQL);

        $start = Carbon::now()->startOfMonth();
        $end = $start->copy()->addMonths(13);
        $this->createMonthlyPartitions($start, $end);

        DB::statement('CREATE UNIQUE INDEX uq_ad_snapshots_ad_id_snapshot_date_granularity ON ad_snapshots (ad_id, snapshot_date, granularity)');
        DB::statement('CREATE INDEX idx_ad_snapshots_ad_id_snapshot_date ON ad_snapshots (ad_id, snapshot_date)');
        DB::statement('CREATE INDEX idx_ad_snapshots_snapshot_date ON ad_snapshots (snapshot_date)');
        DB::statement('CREATE INDEX idx_ad_snapshots_source ON ad_snapshots (source)');
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS ad_snapshots CASCADE');
    }

    private function createMonthlyPartitions(Carbon $start, Carbon $end): void
    {
        $current = $start->copy();

        while ($current->lt($end)) {
            $next = $current->copy()->addMonth();
            $partitionName = 'ad_snapshots_' . $current->format('Y_m');
            $from = $current->format('Y-m-d');
            $to = $next->format('Y-m-d');

            DB::statement(
                "CREATE TABLE IF NOT EXISTS {$partitionName} PARTITION OF ad_snapshots FOR VALUES FROM ('{$from}') TO ('{$to}')"
            );

            $current = $next;
        }
    }
};
