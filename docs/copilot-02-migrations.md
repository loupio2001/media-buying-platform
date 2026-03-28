# Copilot Instructions — Part 2: Database Migrations

**Project:** Havas Media Buying Platform  
**Reference:** `havas-data-model-v3.1.md`  
**This file covers:** All 19 Laravel migration files in dependency order

---

## IMPORTANT RULES FOR ALL MIGRATIONS

1. **PostgreSQL only** — use `TIMESTAMPTZ`, `JSONB`, `BIGSERIAL`, `SERIAL`, `DECIMAL`
2. **No ENUM columns** — use `string()` + `CHECK` constraint via `DB::statement()`
3. **All timestamps use `timestampTz()`** — not `timestamp()`
4. **Every CHECK constraint** has a named constraint for clean error messages
5. **Run migrations in exact order** — dependency chain matters

---

## Migration 1: `create_users_table`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{Schema, DB};

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('email', 150)->unique();
            $table->string('role', 20)->default('manager');
            $table->string('password', 255);
            $table->string('password_reset_token', 100)->nullable();
            $table->timestampTz('password_reset_expires')->nullable();
            $table->jsonb('notification_preferences')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestampTz('last_login_at')->nullable();
            $table->timestampsTz();
        });

        DB::statement("ALTER TABLE users ADD CONSTRAINT chk_users_role CHECK (role IN ('admin', 'manager', 'viewer'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
```

---

## Migration 2: `create_platforms_table`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{Schema, DB};

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platforms', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50);
            $table->string('slug', 50)->unique();
            $table->string('icon_url', 255)->nullable();
            $table->boolean('api_supported')->default(false);
            $table->boolean('supports_reach')->default(false);
            $table->boolean('supports_video_metrics')->default(false);
            $table->boolean('supports_frequency')->default(false);
            $table->boolean('supports_leads')->default(false);
            $table->jsonb('default_metrics')->nullable();
            $table->jsonb('rate_limit_config')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestampsTz();
        });

        // Seed default platforms
        DB::table('platforms')->insert([
            [
                'name' => 'Meta', 'slug' => 'meta', 'api_supported' => true,
                'supports_reach' => true, 'supports_video_metrics' => true,
                'supports_frequency' => true, 'supports_leads' => true,
                'sort_order' => 1, 'created_at' => now(), 'updated_at' => now(),
                'default_metrics' => json_encode([
                    'always' => ['impressions','clicks','ctr','spend','cpm','cpc'],
                    'optional' => ['reach','frequency','video_views','vtr','conversions','cpa','leads','cpl','engagement'],
                    'platform_specific' => ['engagement_rate' => ['label' => 'Engagement Rate', 'unit' => '%']]
                ]),
                'rate_limit_config' => json_encode(['requests_per_hour' => 200, 'requests_per_day' => 1000, 'batch_size' => 50, 'cooldown_seconds' => 2]),
            ],
            [
                'name' => 'Google Ads', 'slug' => 'google', 'api_supported' => true,
                'supports_reach' => false, 'supports_video_metrics' => true,
                'supports_frequency' => false, 'supports_leads' => true,
                'sort_order' => 2, 'created_at' => now(), 'updated_at' => now(),
                'default_metrics' => json_encode([
                    'always' => ['impressions','clicks','ctr','spend','cpm','cpc'],
                    'optional' => ['conversions','cpa','leads','cpl','video_views','vtr'],
                    'platform_specific' => ['quality_score' => ['label' => 'Quality Score', 'unit' => 'int']]
                ]),
                'rate_limit_config' => json_encode(['requests_per_hour' => 100, 'requests_per_day' => 500, 'batch_size' => 25, 'cooldown_seconds' => 3]),
            ],
            [
                'name' => 'TikTok', 'slug' => 'tiktok', 'api_supported' => true,
                'supports_reach' => true, 'supports_video_metrics' => true,
                'supports_frequency' => false, 'supports_leads' => false,
                'sort_order' => 3, 'created_at' => now(), 'updated_at' => now(),
                'default_metrics' => json_encode([
                    'always' => ['impressions','clicks','ctr','spend','cpm','cpc'],
                    'optional' => ['reach','video_views','vtr','conversions','cpa','engagement'],
                    'platform_specific' => ['thumb_stop_rate' => ['label' => 'Thumb Stop Rate', 'unit' => '%']]
                ]),
                'rate_limit_config' => json_encode(['requests_per_hour' => 60, 'requests_per_day' => 300, 'batch_size' => 20, 'cooldown_seconds' => 5]),
            ],
            [
                'name' => 'LinkedIn', 'slug' => 'linkedin', 'api_supported' => false,
                'supports_reach' => false, 'supports_video_metrics' => false,
                'supports_frequency' => false, 'supports_leads' => true,
                'sort_order' => 4, 'created_at' => now(), 'updated_at' => now(),
                'default_metrics' => json_encode([
                    'always' => ['impressions','clicks','ctr','spend','cpm','cpc'],
                    'optional' => ['conversions','cpa','leads','cpl','engagement'],
                    'platform_specific' => ['engagement_rate' => ['label' => 'Engagement Rate', 'unit' => '%']]
                ]),
                'rate_limit_config' => null,
            ],
            [
                'name' => 'YouTube', 'slug' => 'youtube', 'api_supported' => true,
                'supports_reach' => false, 'supports_video_metrics' => true,
                'supports_frequency' => false, 'supports_leads' => false,
                'sort_order' => 5, 'created_at' => now(), 'updated_at' => now(),
                'default_metrics' => json_encode([
                    'always' => ['impressions','clicks','ctr','spend','cpm','cpc'],
                    'optional' => ['video_views','vtr'],
                    'platform_specific' => []
                ]),
                'rate_limit_config' => json_encode(['requests_per_hour' => 100, 'requests_per_day' => 500, 'batch_size' => 25, 'cooldown_seconds' => 3]),
            ],
            [
                'name' => 'Snapchat', 'slug' => 'snapchat', 'api_supported' => false,
                'supports_reach' => true, 'supports_video_metrics' => true,
                'supports_frequency' => true, 'supports_leads' => false,
                'sort_order' => 6, 'created_at' => now(), 'updated_at' => now(),
                'default_metrics' => json_encode([
                    'always' => ['impressions','clicks','ctr','spend','cpm','cpc'],
                    'optional' => ['reach','frequency','video_views','vtr'],
                    'platform_specific' => []
                ]),
                'rate_limit_config' => null,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('platforms');
    }
};
```

---

## Migration 3: `create_platform_connections_table`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{Schema, DB};

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('platform_id')->constrained('platforms');
            $table->string('account_id', 100);
            $table->string('account_name', 150)->nullable();
            $table->string('auth_type', 20);
            $table->text('access_token')->nullable();
            $table->text('refresh_token')->nullable();
            $table->timestampTz('token_expires_at')->nullable();
            $table->text('api_key')->nullable();
            $table->jsonb('extra_credentials')->nullable();
            $table->jsonb('scopes')->nullable();
            $table->boolean('is_connected')->default(true);
            $table->timestampTz('last_sync_at')->nullable();
            $table->text('last_error')->nullable();
            $table->integer('error_count')->default(0);
            $table->foreignId('created_by')->constrained('users');
            $table->timestampsTz();

            $table->unique(['platform_id', 'account_id']);
        });

        DB::statement("ALTER TABLE platform_connections ADD CONSTRAINT chk_pc_auth_type CHECK (auth_type IN ('oauth2', 'api_key', 'service_account'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_connections');
    }
};
```

---

## Migration 4: `create_categories_table`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{Schema, DB};

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('slug', 100)->unique();
            $table->text('description')->nullable();
            $table->boolean('is_custom')->default(false);
            $table->timestampsTz();
        });

        // Seed default categories
        $categories = [
            ['name' => 'Air Travel', 'slug' => 'air-travel'],
            ['name' => 'Banking / Finance', 'slug' => 'banking-finance'],
            ['name' => 'FMCG', 'slug' => 'fmcg'],
            ['name' => 'Hospitality / Hotels', 'slug' => 'hospitality'],
            ['name' => 'Real Estate', 'slug' => 'real-estate'],
            ['name' => 'Telecom', 'slug' => 'telecom'],
            ['name' => 'Retail / E-commerce', 'slug' => 'retail-ecommerce'],
            ['name' => 'Automotive', 'slug' => 'automotive'],
            ['name' => 'Education', 'slug' => 'education'],
            ['name' => 'Government / Public Sector', 'slug' => 'government'],
        ];

        foreach ($categories as $cat) {
            DB::table('categories')->insert(array_merge($cat, [
                'is_custom' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
```

---

## Migration 5: `create_category_benchmarks_table`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{Schema, DB};

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('category_benchmarks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('categories')->cascadeOnDelete();
            $table->foreignId('platform_id')->constrained('platforms')->cascadeOnDelete();
            $table->string('metric', 30);
            $table->decimal('min_value', 12, 4);
            $table->decimal('max_value', 12, 4);
            $table->string('unit', 10);
            $table->integer('sample_size')->nullable();
            $table->date('last_reviewed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestampsTz();

            $table->unique(['category_id', 'platform_id', 'metric']);
        });

        DB::statement("ALTER TABLE category_benchmarks ADD CONSTRAINT chk_cb_range CHECK (max_value >= min_value)");
    }

    public function down(): void
    {
        Schema::dropIfExists('category_benchmarks');
    }
};
```

---

## Migration 6: `create_category_channel_recommendations_table`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{Schema, DB};

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('category_channel_recommendations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('categories')->cascadeOnDelete();
            $table->string('objective', 50);
            $table->foreignId('platform_id')->constrained('platforms')->cascadeOnDelete();
            $table->string('priority', 20);
            $table->decimal('suggested_budget_pct', 5, 2)->nullable();
            $table->text('rationale')->nullable();
            $table->timestampsTz();

            $table->unique(['category_id', 'objective', 'platform_id']);
        });

        DB::statement("ALTER TABLE category_channel_recommendations ADD CONSTRAINT chk_ccr_priority CHECK (priority IN ('primary', 'secondary'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('category_channel_recommendations');
    }
};
```

---

## Migration 7: `create_clients_table`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{Schema, DB};

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->foreignId('category_id')->constrained('categories');
            $table->string('logo_url', 255)->nullable();
            $table->string('primary_contact', 150)->nullable();
            $table->string('contact_email', 150)->nullable();
            $table->string('contact_phone', 30)->nullable();
            $table->string('agency_lead', 150)->nullable();
            $table->string('country', 50)->default('Morocco');
            $table->string('currency', 10)->default('MAD');
            $table->date('contract_start')->nullable();
            $table->date('contract_end')->nullable();
            $table->string('billing_type', 20)->default('project');
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
        });

        DB::statement("ALTER TABLE clients ADD CONSTRAINT chk_clients_billing CHECK (billing_type IN ('retainer', 'project', 'performance'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};
```

---

## Migration 8: `create_campaigns_table`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{Schema, DB};

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients');
            $table->string('name', 200);
            $table->string('status', 20)->default('draft');
            $table->string('objective', 30);
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('total_budget', 12, 2);
            $table->string('currency', 10)->default('MAD');
            $table->jsonb('kpi_targets')->nullable();
            $table->string('pacing_strategy', 20)->default('even');
            $table->string('sheet_id', 100)->nullable();
            $table->string('sheet_url', 255)->nullable();
            $table->text('brief_raw')->nullable();
            $table->text('internal_notes')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestampsTz();

            $table->index('client_id');
            $table->index('status');
            $table->index(['start_date', 'end_date']);
        });

        DB::statement("ALTER TABLE campaigns ADD CONSTRAINT chk_campaigns_status CHECK (status IN ('draft', 'active', 'paused', 'ended', 'archived'))");
        DB::statement("ALTER TABLE campaigns ADD CONSTRAINT chk_campaigns_objective CHECK (objective IN ('awareness', 'reach', 'traffic', 'leads', 'conversions', 'engagement', 'app_installs', 'video_views'))");
        DB::statement("ALTER TABLE campaigns ADD CONSTRAINT chk_campaigns_pacing CHECK (pacing_strategy IN ('even', 'front_loaded', 'back_loaded', 'custom'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('campaigns');
    }
};
```

---

## Migration 9: `create_campaign_platforms_table`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{Schema, DB};

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaign_platforms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('campaigns')->cascadeOnDelete();
            $table->foreignId('platform_id')->constrained('platforms');
            $table->foreignId('platform_connection_id')->nullable()->constrained('platform_connections')->nullOnDelete();
            $table->string('external_campaign_id', 100)->nullable();
            $table->decimal('budget', 12, 2);
            $table->string('budget_type', 10)->default('lifetime');
            $table->string('currency', 10)->default('MAD');
            $table->boolean('is_active')->default(true);
            $table->timestampTz('last_sync_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestampsTz();

            $table->unique(['campaign_id', 'platform_id']);
        });

        DB::statement("ALTER TABLE campaign_platforms ADD CONSTRAINT chk_cp_budget_type CHECK (budget_type IN ('lifetime', 'daily'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_platforms');
    }
};
```

---

## Migration 10: `create_ad_sets_table`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{Schema, DB};

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ad_sets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_platform_id')->constrained('campaign_platforms')->cascadeOnDelete();
            $table->string('external_id', 100);
            $table->string('name', 255);
            $table->string('objective', 100)->nullable();
            $table->text('targeting_summary')->nullable();
            $table->string('status', 30)->default('active');
            $table->decimal('budget', 12, 2)->nullable();
            $table->string('budget_type', 10)->nullable();
            $table->string('bid_strategy', 100)->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->boolean('is_tracked')->default(true);
            $table->timestampsTz();

            $table->unique(['campaign_platform_id', 'external_id']);
        });

        DB::statement("ALTER TABLE ad_sets ADD CONSTRAINT chk_adsets_status CHECK (status IN ('active', 'paused', 'deleted', 'archived'))");
        DB::statement("ALTER TABLE ad_sets ADD CONSTRAINT chk_adsets_budget_type CHECK (budget_type IS NULL OR budget_type IN ('lifetime', 'daily'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('ad_sets');
    }
};
```

---

## Migration 11: `create_ads_table`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{Schema, DB};

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ad_set_id')->constrained('ad_sets')->cascadeOnDelete();
            $table->string('external_id', 100);
            $table->string('name', 255);
            $table->string('format', 50)->nullable();
            $table->string('creative_url', 500)->nullable();
            $table->text('headline')->nullable();
            $table->text('body')->nullable();
            $table->string('cta', 50)->nullable();
            $table->string('destination_url', 500)->nullable();
            $table->string('status', 30)->default('active');
            $table->boolean('is_tracked')->default(true);
            $table->timestampsTz();

            $table->unique(['ad_set_id', 'external_id']);
        });

        DB::statement("ALTER TABLE ads ADD CONSTRAINT chk_ads_status CHECK (status IN ('active', 'paused', 'deleted', 'archived'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('ads');
    }
};
```

---

## Migration 12: `create_ad_snapshots_table`

**This is the most critical migration — partitioned table.**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // MUST use raw SQL for partitioned tables — Laravel Schema builder doesn't support PARTITION BY
        DB::statement("
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
                CONSTRAINT chk_snapshots_granularity CHECK (granularity IN ('daily', 'cumulative')),
                CONSTRAINT chk_snapshots_source CHECK (source IN ('api', 'manual'))
            ) PARTITION BY RANGE (snapshot_date)
        ");

        // Create partitions for current year + next quarter
        $this->createMonthlyPartitions(2025, 1, 2026, 6);

        // Create unique constraint (must include partition key)
        DB::statement("CREATE UNIQUE INDEX uq_snapshots_ad_date_gran ON ad_snapshots (ad_id, snapshot_date, granularity)");

        // Performance indexes
        DB::statement("CREATE INDEX idx_snapshots_ad_date ON ad_snapshots (ad_id, snapshot_date)");
        DB::statement("CREATE INDEX idx_snapshots_date ON ad_snapshots (snapshot_date)");
        DB::statement("CREATE INDEX idx_snapshots_source ON ad_snapshots (source)");
    }

    public function down(): void
    {
        DB::statement("DROP TABLE IF EXISTS ad_snapshots CASCADE");
    }

    private function createMonthlyPartitions(int $fromYear, int $fromMonth, int $toYear, int $toMonth): void
    {
        $current = \Carbon\Carbon::create($fromYear, $fromMonth, 1);
        $end = \Carbon\Carbon::create($toYear, $toMonth, 1);

        while ($current->lt($end)) {
            $next = $current->copy()->addMonth();
            $partitionName = 'ad_snapshots_' . $current->format('Y_m');
            $from = $current->format('Y-m-d');
            $to = $next->format('Y-m-d');

            DB::statement("
                CREATE TABLE IF NOT EXISTS {$partitionName}
                PARTITION OF ad_snapshots
                FOR VALUES FROM ('{$from}') TO ('{$to}')
            ");

            $current = $next;
        }
    }
};
```

---

## Migration 13: `create_views`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // View 1: Ad Set level rollup
        DB::statement("
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
        ");

        // View 2: Campaign Platform level rollup
        DB::statement("
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
        ");
    }

    public function down(): void
    {
        DB::statement("DROP VIEW IF EXISTS v_campaign_platform_totals");
        DB::statement("DROP VIEW IF EXISTS v_ad_set_totals");
    }
};
```

---

## Migration 14: `create_briefs_table`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{Schema, DB};

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('briefs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->unique()->constrained('campaigns')->cascadeOnDelete();
            $table->string('objective', 200)->nullable();
            $table->jsonb('kpis_requested')->nullable();
            $table->text('target_audience')->nullable();
            $table->jsonb('geo_targeting')->nullable();
            $table->decimal('budget_total', 12, 2)->nullable();
            $table->jsonb('channels_requested')->nullable();
            $table->jsonb('channels_recommended')->nullable();
            $table->jsonb('creative_formats')->nullable();
            $table->date('flight_start')->nullable();
            $table->date('flight_end')->nullable();
            $table->text('constraints')->nullable();
            $table->smallInteger('version')->default(1);
            $table->smallInteger('ai_brief_quality_score')->nullable();
            $table->jsonb('ai_missing_info')->nullable();
            $table->jsonb('ai_kpi_challenges')->nullable();
            $table->jsonb('ai_questions_for_client')->nullable();
            $table->text('ai_channel_rationale')->nullable();
            $table->jsonb('ai_budget_split')->nullable();
            $table->jsonb('ai_media_plan_draft')->nullable();
            $table->string('status', 20)->default('draft');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('reviewed_at')->nullable();
            $table->timestampsTz();
        });

        DB::statement("ALTER TABLE briefs ADD CONSTRAINT chk_briefs_status CHECK (status IN ('draft', 'reviewed', 'approved', 'revision_requested'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('briefs');
    }
};
```

---

## Migration 15: `create_reports_table`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{Schema, DB};

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('campaigns');
            $table->string('type', 10);
            $table->date('period_start');
            $table->date('period_end');
            $table->string('title', 200)->nullable();
            $table->text('executive_summary')->nullable();
            $table->string('overall_performance', 20)->nullable();
            $table->jsonb('ai_recommendations')->nullable();
            $table->string('status', 20)->default('draft');
            $table->smallInteger('version')->default(1);
            $table->string('exported_file_path', 500)->nullable();
            $table->timestampTz('exported_at')->nullable();
            $table->string('export_format', 10)->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('reviewed_at')->nullable();
            $table->timestampsTz();
        });

        DB::statement("ALTER TABLE reports ADD CONSTRAINT chk_reports_type CHECK (type IN ('weekly', 'monthly', 'mid', 'end', 'custom'))");
        DB::statement("ALTER TABLE reports ADD CONSTRAINT chk_reports_perf CHECK (overall_performance IS NULL OR overall_performance IN ('on_track', 'underperforming', 'overperforming'))");
        DB::statement("ALTER TABLE reports ADD CONSTRAINT chk_reports_status CHECK (status IN ('draft', 'reviewed', 'exported'))");
        DB::statement("ALTER TABLE reports ADD CONSTRAINT chk_reports_format CHECK (export_format IS NULL OR export_format IN ('pptx', 'pdf', 'both'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
```

---

## Migration 16: `create_report_platform_sections_table`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{Schema, DB};

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_platform_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_id')->constrained('reports')->cascadeOnDelete();
            $table->foreignId('platform_id')->constrained('platforms');
            $table->decimal('spend', 12, 2)->default(0);
            $table->decimal('budget', 12, 2)->default(0);
            $table->bigInteger('impressions')->default(0);
            $table->bigInteger('reach')->nullable();
            $table->bigInteger('clicks')->default(0);
            $table->bigInteger('link_clicks')->nullable();
            $table->decimal('ctr', 8, 4)->nullable();
            $table->decimal('cpm', 10, 4)->nullable();
            $table->decimal('cpc', 10, 4)->nullable();
            $table->integer('conversions')->nullable();
            $table->decimal('cpa', 10, 4)->nullable();
            $table->integer('leads')->nullable();
            $table->decimal('cpl', 10, 4)->nullable();
            $table->bigInteger('video_views')->nullable();
            $table->bigInteger('video_completions')->nullable();
            $table->decimal('vtr', 8, 4)->nullable();
            $table->decimal('frequency', 8, 4)->nullable();
            $table->bigInteger('engagement')->nullable();
            $table->string('performance_vs_benchmark', 20)->nullable();
            $table->text('ai_summary')->nullable();
            $table->jsonb('ai_highlights')->nullable();
            $table->jsonb('ai_concerns')->nullable();
            $table->text('ai_suggested_action')->nullable();
            $table->jsonb('top_performing_ads')->nullable();
            $table->jsonb('worst_performing_ads')->nullable();
            $table->text('human_notes')->nullable();
            $table->jsonb('performance_flags')->nullable();
            $table->timestampsTz();

            $table->unique(['report_id', 'platform_id']);
        });

        DB::statement("ALTER TABLE report_platform_sections ADD CONSTRAINT chk_rps_perf CHECK (performance_vs_benchmark IS NULL OR performance_vs_benchmark IN ('above', 'within', 'below'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('report_platform_sections');
    }
};
```

---

## Migration 17: `create_notifications_table`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{Schema, DB};

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('type', 50);
            $table->string('severity', 10);
            $table->string('title', 200);
            $table->text('message')->nullable();
            $table->string('entity_type', 50)->nullable();
            $table->integer('entity_id')->nullable();
            $table->jsonb('meta')->nullable();
            $table->boolean('is_read')->default(false);
            $table->timestampTz('read_at')->nullable();
            $table->boolean('is_dismissed')->default(false);
            $table->boolean('is_actionable')->default(false);
            $table->string('action_url', 500)->nullable();
            $table->timestampTz('expires_at')->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['user_id', 'is_read', 'created_at']);
        });

        DB::statement("ALTER TABLE notifications ADD CONSTRAINT chk_notif_severity CHECK (severity IN ('info', 'warning', 'critical'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
```

---

## Migration 18: `create_activity_log_table`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 30);
            $table->string('entity_type', 50);
            $table->integer('entity_id');
            $table->string('entity_name', 200)->nullable();
            $table->jsonb('changes')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['entity_type', 'entity_id']);
            $table->index(['user_id', 'created_at']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_log');
    }
};
```

---

## Migration 19: `create_partition_management_command`

This is not a migration — it's an Artisan command. Create `app/Console/Commands/CreateMonthlyPartition.php`:

```php
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
                DB::statement("
                    CREATE TABLE IF NOT EXISTS {$name}
                    PARTITION OF ad_snapshots
                    FOR VALUES FROM ('{$from}') TO ('{$to}')
                ");
                $this->info("Created partition: {$name}");
            } catch (\Exception $e) {
                if (str_contains($e->getMessage(), 'already exists')) {
                    $this->comment("Partition already exists: {$name}");
                } else {
                    $this->error("Failed: {$name} — {$e->getMessage()}");
                }
            }
        }

        return self::SUCCESS;
    }
}
```

---

## Running Migrations

```bash
php artisan migrate

# Verify all tables and views created
php artisan tinker
>>> \DB::select("SELECT tablename FROM pg_tables WHERE schemaname = 'public' ORDER BY tablename");
>>> \DB::select("SELECT viewname FROM pg_views WHERE schemaname = 'public'");
```

---

## Next Steps

Proceed to **Part 3:** Eloquent models, relationships, and traits.
