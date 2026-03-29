<?php

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
            CREATE OR REPLACE VIEW v_ad_set_totals AS
            SELECT
                aset.id                            AS ad_set_id,
                aset.campaign_platform_id,
                aset.name                          AS ad_set_name,
                COUNT(DISTINCT a.id)               AS ad_count,
                SUM(s.impressions)                 AS total_impressions,
                SUM(s.reach)                       AS total_reach,
                SUM(s.clicks)                      AS total_clicks,
                SUM(s.link_clicks)                 AS total_link_clicks,
                SUM(s.spend)                       AS total_spend,
                SUM(s.conversions)                 AS total_conversions,
                SUM(s.leads)                       AS total_leads,
                SUM(s.video_views)                 AS total_video_views,
                SUM(s.video_completions)           AS total_video_completions,
                SUM(s.engagement)                  AS total_engagement,
                CASE WHEN SUM(s.impressions) > 0
                     THEN ROUND(SUM(s.clicks)::numeric / SUM(s.impressions) * 100, 4)
                     ELSE 0 END                    AS calc_ctr,
                CASE WHEN SUM(s.impressions) > 0
                     THEN ROUND(SUM(s.spend) / SUM(s.impressions) * 1000, 4)
                     ELSE 0 END                    AS calc_cpm,
                CASE WHEN SUM(s.clicks) > 0
                     THEN ROUND(SUM(s.spend) / SUM(s.clicks), 4)
                     ELSE 0 END                    AS calc_cpc,
                CASE WHEN SUM(s.conversions) > 0
                     THEN ROUND(SUM(s.spend) / SUM(s.conversions), 4)
                     ELSE 0 END                    AS calc_cpa,
                CASE WHEN SUM(s.leads) > 0
                     THEN ROUND(SUM(s.spend) / SUM(s.leads), 4)
                     ELSE 0 END                    AS calc_cpl,
                CASE WHEN SUM(s.impressions) > 0 AND SUM(s.video_views) > 0
                     THEN ROUND(SUM(s.video_views)::numeric / SUM(s.impressions) * 100, 4)
                     ELSE NULL END                 AS calc_vtr,
                CASE WHEN SUM(s.reach) > 0
                     THEN ROUND(SUM(s.impressions)::numeric / SUM(s.reach), 4)
                     ELSE NULL END                 AS calc_frequency,
                MAX(s.pulled_at)                   AS last_synced_at
            FROM ad_sets aset
            JOIN ads a          ON a.ad_set_id = aset.id AND a.is_tracked = true
            JOIN ad_snapshots s ON s.ad_id = a.id AND s.granularity = 'daily'
            WHERE aset.is_tracked = true
            GROUP BY aset.id, aset.campaign_platform_id, aset.name
        SQL);

        DB::statement(<<<'SQL'
            CREATE OR REPLACE VIEW v_campaign_platform_totals AS
            SELECT
                cp.id                              AS campaign_platform_id,
                cp.campaign_id,
                cp.platform_id,
                cp.budget,
                cp.budget_type,
                COUNT(DISTINCT a.id)               AS ad_count,
                COUNT(DISTINCT aset.id)            AS ad_set_count,
                SUM(s.impressions)                 AS total_impressions,
                SUM(s.reach)                       AS total_reach,
                SUM(s.clicks)                      AS total_clicks,
                SUM(s.link_clicks)                 AS total_link_clicks,
                SUM(s.landing_page_views)          AS total_landing_page_views,
                SUM(s.spend)                       AS total_spend,
                CASE WHEN cp.budget > 0
                     THEN ROUND(SUM(s.spend) / cp.budget * 100, 2)
                     ELSE 0 END                    AS budget_pct_used,
                CASE WHEN SUM(s.impressions) > 0
                     THEN ROUND(SUM(s.clicks)::numeric / SUM(s.impressions) * 100, 4)
                     ELSE 0 END                    AS calc_ctr,
                CASE WHEN SUM(s.impressions) > 0
                     THEN ROUND(SUM(s.spend) / SUM(s.impressions) * 1000, 4)
                     ELSE 0 END                    AS calc_cpm,
                CASE WHEN SUM(s.clicks) > 0
                     THEN ROUND(SUM(s.spend) / SUM(s.clicks), 4)
                     ELSE 0 END                    AS calc_cpc,
                SUM(s.conversions)                 AS total_conversions,
                CASE WHEN SUM(s.conversions) > 0
                     THEN ROUND(SUM(s.spend) / SUM(s.conversions), 4)
                     ELSE 0 END                    AS calc_cpa,
                SUM(s.leads)                       AS total_leads,
                CASE WHEN SUM(s.leads) > 0
                     THEN ROUND(SUM(s.spend) / SUM(s.leads), 4)
                     ELSE 0 END                    AS calc_cpl,
                SUM(s.video_views)                 AS total_video_views,
                SUM(s.video_completions)           AS total_video_completions,
                CASE WHEN SUM(s.impressions) > 0 AND SUM(s.video_views) > 0
                     THEN ROUND(SUM(s.video_views)::numeric / SUM(s.impressions) * 100, 4)
                     ELSE NULL END                 AS calc_vtr,
                CASE WHEN SUM(s.reach) > 0
                     THEN ROUND(SUM(s.impressions)::numeric / SUM(s.reach), 4)
                     ELSE NULL END                 AS calc_frequency,
                SUM(s.engagement)                  AS total_engagement,
                MAX(s.pulled_at)                   AS last_synced_at
            FROM campaign_platforms cp
            JOIN ad_sets aset   ON aset.campaign_platform_id = cp.id AND aset.is_tracked = true
            JOIN ads a          ON a.ad_set_id = aset.id AND a.is_tracked = true
            JOIN ad_snapshots s ON s.ad_id = a.id AND s.granularity = 'daily'
            GROUP BY cp.id, cp.campaign_id, cp.platform_id, cp.budget, cp.budget_type
        SQL);
    }

    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS v_campaign_platform_totals');
        DB::statement('DROP VIEW IF EXISTS v_ad_set_totals');
    }
};
