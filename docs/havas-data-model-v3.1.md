# Havas Media Buying Platform — Data Model v3.1

**Version:** 3.1  
**Database:** PostgreSQL 16+  
**Stack:** Laravel 11 + Python 3.12 (data collectors + AI layer)  
**Timezone Convention:** Africa/Casablanca (GMT+1, no DST) — all `snapshot_date` values use this timezone  
**Date:** 2026-03-28

---

## Changelog from v3.0

| Change | Reason |
|---|---|
| **CRITICAL FIX:** `v_campaign_platform_totals` — ratio metrics now recomputed from sums instead of using AVG() | AVG of ratios is statistically incorrect when volumes differ across ads |
| Restored `kpi_targets` JSONB on campaigns | Client-agreed targets ≠ category benchmarks — needed for AI commentary and flags |
| Restored `pacing_strategy` on campaigns | Drives budget pacing alerts (even vs front-loaded vs back-loaded) |
| Added `archived` to campaign status options | Ended campaigns clutter dashboard; archive hides without deleting |
| Extended campaign objectives: added `reach`, `app_installs`, `video_views` | Standard objectives run at Havas |
| Restored `external_campaign_id` on campaign_platforms | Python scheduler needs to map platform campaign IDs to pull data |
| Restored `budget_type` (lifetime/daily) on campaign_platforms | Fundamentally affects pacing math |
| Restored `custom_metrics` JSONB on ad_snapshots | Catch-all for platform-specific metrics without schema changes |
| Restored `video_completions`, `link_clicks`, `landing_page_views` on ad_snapshots | Meta distinguishes these; dropping them loses data |
| Restored `creative_formats` and `version` on briefs | AI needs to know available assets; briefs get revised |
| Extended report types: added `weekly`, `monthly`, `custom` | Retainer clients need recurring reports beyond mid/end |
| Added CHECK constraints on all status/type VARCHAR fields | Database-level validation of allowed values |
| Changed partition strategy from quarterly to monthly | Better query pruning at ad-level granularity volumes |
| Added UPSERT strategy for ad_snapshots | Handles scheduler retries and data corrections |
| Added data retention policy section | Unbounded tables need documented cleanup strategy |
| Documented `is_connected` automation on platform_connections | Circuit-breaker sets this to false at error_count >= 5 |
| Added `v_ad_set_totals` view | Needed for ad-set-level rollup between ad and platform level |

---

## Key Decisions Locked

| Decision | Choice | Rationale |
|---|---|---|
| Database | PostgreSQL 16+ | Native partitioning, superior JSONB querying, scales to billions of rows |
| Ad snapshot granularity | Always ad-level | Full creative analysis from day one |
| Dashboard rollups | Database views (2 views) | No extra storage, auto-computed at ad_set and platform level |
| Platform references | Lookup table (not ENUM) | Add new platforms via INSERT, zero migrations |
| Benchmarks | Normalized rows | Queryable, individually updatable, auditable |
| API credentials | Separate table + encrypted | One account serves many campaigns |
| LinkedIn data | Manual entry (source flag) | API OAuth refresh not worth the complexity yet |
| Timezone | Africa/Casablanca for snapshot_date | Morocco GMT+1. Python scheduler converts API UTC responses before storing |
| Snapshot UPSERT | ON CONFLICT DO UPDATE | Re-pulls overwrite metrics, preserve `raw_response` history in separate log |
| Data retention | 18 months daily, then aggregate monthly | Balance between analysis depth and storage |

---

## Entity Hierarchy

```
platforms                        (lookup — replaces all platform ENUMs)
platform_connections             (API credentials per platform account)

categories
    └── category_benchmarks      (normalized benchmarks per platform per metric)
    └── category_channel_recommendations

clients
    └── campaigns
            ├── campaign_platforms
            │       └── ad_sets
            │               └── ads
            │                       └── ad_snapshots
            ├── briefs
            └── reports
                    └── report_platform_sections

users
    └── notifications
    └── activity_log

[VIEW] v_ad_set_totals              (rollup: ads → ad_sets)
[VIEW] v_campaign_platform_totals   (rollup: ad_sets → campaign_platforms)
```

---

## 1. `platforms`

Single source of truth for all platforms. Replaces every ENUM in the system.

| Field | Type | Details |
|---|---|---|
| `id` | SERIAL, PK | |
| `name` | VARCHAR(50) | Meta, Google Ads, TikTok, LinkedIn… |
| `slug` | VARCHAR(50) | UNIQUE — meta, google, tiktok, linkedin |
| `icon_url` | VARCHAR(255) | Platform logo path for UI |
| `api_supported` | BOOLEAN | Can data be pulled automatically? |
| `supports_reach` | BOOLEAN | Platform reports reach? |
| `supports_video_metrics` | BOOLEAN | VTR, video views available? |
| `supports_frequency` | BOOLEAN | Frequency metric available? |
| `supports_leads` | BOOLEAN | Lead metric distinct from conversions? |
| `default_metrics` | JSONB | Available metrics schema |
| `rate_limit_config` | JSONB | API rate limits for Python scheduler |
| `is_active` | BOOLEAN DEFAULT true | Hide from UI without deleting |
| `sort_order` | INT DEFAULT 0 | Display order in dropdowns and reports |
| `created_at` | TIMESTAMPTZ | |
| `updated_at` | TIMESTAMPTZ | |

**`default_metrics` JSONB:**
```json
{
  "always": ["impressions", "clicks", "ctr", "spend", "cpm", "cpc"],
  "optional": ["reach", "frequency", "video_views", "vtr", "conversions", "cpa", "leads", "cpl", "engagement"],
  "platform_specific": {
    "thumb_stop_rate": { "label": "Thumb Stop Rate", "unit": "%" },
    "quality_score":   { "label": "Quality Score",   "unit": "int" },
    "engagement_rate": { "label": "Engagement Rate", "unit": "%" }
  }
}
```

**`rate_limit_config` JSONB:**
```json
{
  "requests_per_hour": 200,
  "requests_per_day": 1000,
  "batch_size": 50,
  "cooldown_seconds": 2
}
```

**Seeded platforms:**

| slug | name | api_supported | supports_reach | supports_video | supports_frequency | supports_leads |
|---|---|---|---|---|---|---|
| meta | Meta | true | true | true | true | true |
| google | Google Ads | true | false | true | false | true |
| tiktok | TikTok | true | true | true | false | false |
| linkedin | LinkedIn | false* | false | false | false | true |
| youtube | YouTube | true | false | true | false | false |
| snapchat | Snapchat | false* | true | true | true | false |

*API available, not implemented in v1

---

## 2. `platform_connections`

API credentials and OAuth token management. All sensitive fields encrypted at rest using Laravel's `Crypt` facade.

| Field | Type | Details |
|---|---|---|
| `id` | SERIAL, PK | |
| `platform_id` | FK → platforms | |
| `account_id` | VARCHAR(100) | Platform ad account ID |
| `account_name` | VARCHAR(150) | Human-readable label (e.g. "RAM — Meta BM") |
| `auth_type` | VARCHAR(20) | CHECK: `oauth2`, `api_key`, `service_account` |
| `access_token` | TEXT | Encrypted — current access token |
| `refresh_token` | TEXT | Encrypted — for OAuth2 refresh flow |
| `token_expires_at` | TIMESTAMPTZ | Nullable — API keys don't expire |
| `api_key` | TEXT | Encrypted — for platforms using API key auth |
| `extra_credentials` | JSONB | Encrypted — platform-specific credentials |
| `scopes` | JSONB | OAuth scopes granted |
| `is_connected` | BOOLEAN DEFAULT true | Connection health — set to false automatically when error_count >= 5 |
| `last_sync_at` | TIMESTAMPTZ | Last successful data pull |
| `last_error` | TEXT | Last error message for debugging |
| `error_count` | INT DEFAULT 0 | Consecutive failures |
| `created_by` | FK → users | |
| `created_at` | TIMESTAMPTZ | |
| `updated_at` | TIMESTAMPTZ | |

**Unique constraint:** `platform_id + account_id`

**CHECK constraint:** `auth_type IN ('oauth2', 'api_key', 'service_account')`

**Circuit-breaker behavior:**
- `error_count` increments on each failed pull attempt
- `error_count` resets to 0 on any successful pull
- When `error_count >= 5`: Python scheduler sets `is_connected = false` and triggers `api_error` critical notification
- Manual reconnection via UI resets `error_count` to 0 and `is_connected` to true

**`extra_credentials` JSONB examples:**
```json
// Meta
{
  "app_id": "...",
  "app_secret": "...",
  "business_id": "..."
}

// Google Ads
{
  "developer_token": "...",
  "customer_id": "123-456-7890",
  "login_customer_id": "111-222-3333",
  "manager_account": true
}

// TikTok
{
  "advertiser_id": "...",
  "app_id": "..."
}
```

**Auth types by platform:**

| Platform | auth_type |
|---|---|
| Meta | oauth2 |
| Google Ads | service_account |
| TikTok | oauth2 |
| LinkedIn | oauth2 |

---

## 3. `categories`

Client vertical classification. Drives benchmarks and channel recommendations.

| Field | Type | Details |
|---|---|---|
| `id` | SERIAL, PK | |
| `name` | VARCHAR(100) | Air Travel, Banking / Finance… |
| `slug` | VARCHAR(100) | UNIQUE — air-travel, banking-finance |
| `description` | TEXT | What qualifies a client for this category |
| `is_custom` | BOOLEAN DEFAULT false | false = seeded, true = user-added |
| `created_at` | TIMESTAMPTZ | |
| `updated_at` | TIMESTAMPTZ | |

**Seeded categories:**

| slug | name |
|---|---|
| air-travel | Air Travel |
| banking-finance | Banking / Finance |
| fmcg | FMCG |
| hospitality | Hospitality / Hotels |
| real-estate | Real Estate |
| telecom | Telecom |
| retail-ecommerce | Retail / E-commerce |
| automotive | Automotive |
| education | Education |
| government | Government / Public Sector |

---

## 4. `category_benchmarks`

Normalized benchmark ranges. One row per category × platform × metric.

| Field | Type | Details |
|---|---|---|
| `id` | SERIAL, PK | |
| `category_id` | FK → categories | |
| `platform_id` | FK → platforms | |
| `metric` | VARCHAR(30) | ctr, cpm, cpc, vtr, cpa, cpl, frequency, reach_rate |
| `min_value` | DECIMAL(12,4) | |
| `max_value` | DECIMAL(12,4) | |
| `unit` | VARCHAR(10) | %, MAD, count |
| `sample_size` | INT | Number of campaigns this benchmark is based on |
| `last_reviewed_at` | DATE | When this benchmark was last validated |
| `notes` | TEXT | Context for the range |
| `created_at` | TIMESTAMPTZ | |
| `updated_at` | TIMESTAMPTZ | |

**Unique constraint:** `category_id + platform_id + metric`

**Validation:** `CHECK (max_value >= min_value)`

**Example rows (Air Travel — Meta):**

| metric | min | max | unit |
|---|---|---|---|
| ctr | 0.80 | 1.20 | % |
| cpm | 18.00 | 25.00 | MAD |
| cpc | 0.80 | 2.00 | MAD |
| vtr | 30.00 | 40.00 | % |
| cpa | 80.00 | 200.00 | MAD |
| frequency | 2.00 | 5.00 | count |

---

## 5. `category_channel_recommendations`

Which platforms to recommend per category per campaign objective.

| Field | Type | Details |
|---|---|---|
| `id` | SERIAL, PK | |
| `category_id` | FK → categories | |
| `objective` | VARCHAR(50) | awareness, reach, traffic, leads, conversions, engagement, app_installs, video_views |
| `platform_id` | FK → platforms | |
| `priority` | VARCHAR(20) | CHECK: `primary`, `secondary` |
| `suggested_budget_pct` | DECIMAL(5,2) | Suggested % of total budget for this platform |
| `rationale` | TEXT | Why this platform for this objective in this vertical |
| `created_at` | TIMESTAMPTZ | |
| `updated_at` | TIMESTAMPTZ | |

**Unique constraint:** `category_id + objective + platform_id`

**CHECK constraint:** `priority IN ('primary', 'secondary')`

---

## 6. `clients`

| Field | Type | Details |
|---|---|---|
| `id` | SERIAL, PK | |
| `name` | VARCHAR(150) | Royal Air Maroc |
| `category_id` | FK → categories | |
| `logo_url` | VARCHAR(255) | |
| `primary_contact` | VARCHAR(150) | Client-side contact name |
| `contact_email` | VARCHAR(150) | |
| `contact_phone` | VARCHAR(30) | |
| `agency_lead` | VARCHAR(150) | Havas account manager name |
| `country` | VARCHAR(50) DEFAULT 'Morocco' | |
| `currency` | VARCHAR(10) DEFAULT 'MAD' | |
| `contract_start` | DATE | Nullable |
| `contract_end` | DATE | Nullable |
| `billing_type` | VARCHAR(20) DEFAULT 'project' | CHECK: `retainer`, `project`, `performance` |
| `notes` | TEXT | Internal notes |
| `is_active` | BOOLEAN DEFAULT true | Soft toggle — preserves historical data |
| `created_at` | TIMESTAMPTZ | |
| `updated_at` | TIMESTAMPTZ | |

**CHECK constraint:** `billing_type IN ('retainer', 'project', 'performance')`

---

## 7. `campaigns`

Central entity. Everything hangs off this.

| Field | Type | Details |
|---|---|---|
| `id` | SERIAL, PK | |
| `client_id` | FK → clients | |
| `name` | VARCHAR(200) | Ramadan 2025, CAN 2025 |
| `status` | VARCHAR(20) | CHECK: `draft`, `active`, `paused`, `ended`, `archived` |
| `objective` | VARCHAR(30) | CHECK: `awareness`, `reach`, `traffic`, `leads`, `conversions`, `engagement`, `app_installs`, `video_views` |
| `start_date` | DATE | |
| `end_date` | DATE | |
| `total_budget` | DECIMAL(12,2) | Sum of all platform budgets |
| `currency` | VARCHAR(10) | Inherits from client, overridable |
| `kpi_targets` | JSONB | Client-agreed targets — separate from category benchmarks |
| `pacing_strategy` | VARCHAR(20) DEFAULT 'even' | CHECK: `even`, `front_loaded`, `back_loaded`, `custom` |
| `sheet_id` | VARCHAR(100) | Google Sheet ID — nullable |
| `sheet_url` | VARCHAR(255) | Full Sheet URL for quick access |
| `brief_raw` | TEXT | Original client brief pasted as-is |
| `internal_notes` | TEXT | Team notes — never shown in exports |
| `created_by` | FK → users | |
| `created_at` | TIMESTAMPTZ | |
| `updated_at` | TIMESTAMPTZ | |

**CHECK constraints:**
- `status IN ('draft', 'active', 'paused', 'ended', 'archived')`
- `objective IN ('awareness', 'reach', 'traffic', 'leads', 'conversions', 'engagement', 'app_installs', 'video_views')`
- `pacing_strategy IN ('even', 'front_loaded', 'back_loaded', 'custom')`

**`kpi_targets` JSONB:**
```json
{
  "cpa": { "target": 50, "unit": "MAD", "priority": "primary" },
  "ctr": { "target": 1.2, "unit": "%", "priority": "secondary" },
  "leads": { "target": 500, "unit": "count", "priority": "primary" }
}
```

**Indexes:**
- `client_id`
- `status`
- `start_date, end_date` (date range queries)

**Design decisions:**
- `kpi_targets` is distinct from `category_benchmarks`. The category benchmark for banking CPA is 80–200 MAD, but a client may agree to a target of 50 MAD. AI commentary and flags use BOTH: targets for "are we meeting the client's goal" and benchmarks for "are we performing within industry norms."
- `pacing_strategy` drives budget alerts. `even` = linear spend expected. `front_loaded` = 60%+ in first half is normal, not an overspend alert.
- `archived` status hides ended campaigns from dashboard without deleting data.

---

## 8. `campaign_platforms`

One row per platform per campaign. Budgets, account links, and pull configuration.

| Field | Type | Details |
|---|---|---|
| `id` | SERIAL, PK | |
| `campaign_id` | FK → campaigns | |
| `platform_id` | FK → platforms | |
| `platform_connection_id` | FK → platform_connections | Which account credentials to use — nullable for manual platforms |
| `external_campaign_id` | VARCHAR(100) | Platform's own campaign/IO ID — needed for API mapping |
| `budget` | DECIMAL(12,2) | Budget allocated to this platform |
| `budget_type` | VARCHAR(10) DEFAULT 'lifetime' | CHECK: `lifetime`, `daily` |
| `currency` | VARCHAR(10) | Usually MAD |
| `is_active` | BOOLEAN DEFAULT true | Pause data pull per platform independently |
| `last_sync_at` | TIMESTAMPTZ | Last successful snapshot pull |
| `notes` | TEXT | Platform-specific notes |
| `created_at` | TIMESTAMPTZ | |
| `updated_at` | TIMESTAMPTZ | |

**Unique constraint:** `campaign_id + platform_id`

**CHECK constraint:** `budget_type IN ('lifetime', 'daily')`

**Design decisions:**
- `external_campaign_id` — the Python scheduler uses this to query the correct campaign from the platform API. Without it, there's no anchor for data pulls.
- `budget_type` — daily budget × remaining days ≠ lifetime budget. Pacing math depends on this.
- `platform_connection_id` is nullable — LinkedIn (manual entry) has no API connection.

---

## 9. `ad_sets`

Ad set / ad group level — one level below campaign platform.

| Field | Type | Details |
|---|---|---|
| `id` | SERIAL, PK | |
| `campaign_platform_id` | FK → campaign_platforms | |
| `external_id` | VARCHAR(100) | Platform's own ad set ID |
| `name` | VARCHAR(255) | Ad set name from platform |
| `objective` | VARCHAR(100) | Ad set level objective if applicable |
| `targeting_summary` | TEXT | Human-readable targeting description |
| `status` | VARCHAR(30) | CHECK: `active`, `paused`, `deleted`, `archived` |
| `budget` | DECIMAL(12,2) | Nullable — not all platforms have ad set budgets |
| `budget_type` | VARCHAR(10) | Nullable — CHECK: `lifetime`, `daily` |
| `bid_strategy` | VARCHAR(100) | e.g., lowest_cost, cost_cap, target_cpa |
| `start_date` | DATE | Nullable |
| `end_date` | DATE | Nullable |
| `is_tracked` | BOOLEAN DEFAULT true | Toggle off to exclude from reporting |
| `created_at` | TIMESTAMPTZ | |
| `updated_at` | TIMESTAMPTZ | |

**Unique constraint:** `campaign_platform_id + external_id`

**CHECK constraints:**
- `status IN ('active', 'paused', 'deleted', 'archived')`
- `budget_type IN ('lifetime', 'daily')` (when not null)

---

## 10. `ads`

Individual ad / creative level.

| Field | Type | Details |
|---|---|---|
| `id` | SERIAL, PK | |
| `ad_set_id` | FK → ad_sets | |
| `external_id` | VARCHAR(100) | Platform's own ad ID |
| `name` | VARCHAR(255) | Ad name from platform |
| `format` | VARCHAR(50) | single_image, video, carousel, collection, story, responsive_search, responsive_display |
| `creative_url` | VARCHAR(500) | Preview or thumbnail URL |
| `headline` | TEXT | Ad headline text |
| `body` | TEXT | Ad body copy |
| `cta` | VARCHAR(50) | Call to action label |
| `destination_url` | VARCHAR(500) | Landing page URL |
| `status` | VARCHAR(30) | CHECK: `active`, `paused`, `deleted`, `archived` |
| `is_tracked` | BOOLEAN DEFAULT true | Toggle off to exclude from reporting |
| `created_at` | TIMESTAMPTZ | |
| `updated_at` | TIMESTAMPTZ | |

**Unique constraint:** `ad_set_id + external_id`

**CHECK constraint:** `status IN ('active', 'paused', 'deleted', 'archived')`

---

## 11. `ad_snapshots`

The largest table. Every data pull creates rows here — never overwrites (except UPSERT on re-pull for same date).

| Field | Type | Details |
|---|---|---|
| `id` | BIGSERIAL, PK | |
| `ad_id` | FK → ads | |
| `snapshot_date` | DATE | Date this data represents (Africa/Casablanca timezone) |
| `granularity` | VARCHAR(15) | CHECK: `daily`, `cumulative` |
| `impressions` | BIGINT | |
| `reach` | BIGINT | Nullable — not all platforms |
| `frequency` | DECIMAL(8,4) | Nullable |
| `clicks` | BIGINT | |
| `link_clicks` | BIGINT | Nullable — outbound clicks specifically (Meta distinction) |
| `landing_page_views` | BIGINT | Nullable — post-click page views |
| `ctr` | DECIMAL(8,4) | Percentage |
| `spend` | DECIMAL(12,2) | |
| `cpm` | DECIMAL(10,4) | |
| `cpc` | DECIMAL(10,4) | |
| `conversions` | INT | Nullable |
| `cpa` | DECIMAL(10,4) | Nullable |
| `leads` | INT | Nullable — distinct from conversions |
| `cpl` | DECIMAL(10,4) | Nullable |
| `video_views` | BIGINT | Nullable |
| `video_completions` | BIGINT | Nullable — 100% completion views |
| `vtr` | DECIMAL(8,4) | Nullable — percentage |
| `engagement` | BIGINT | Nullable — likes + comments + shares + saves |
| `engagement_rate` | DECIMAL(8,4) | Nullable |
| `thumb_stop_rate` | DECIMAL(8,4) | Nullable — TikTok specific |
| `custom_metrics` | JSONB | Platform-specific extras not in fixed columns |
| `raw_response` | JSONB | Full API response — preserved for debugging |
| `source` | VARCHAR(10) | CHECK: `api`, `manual` |
| `pulled_at` | TIMESTAMPTZ | When the pull happened |

**Unique constraint:** `ad_id + snapshot_date + granularity`

**CHECK constraints:**
- `granularity IN ('daily', 'cumulative')`
- `source IN ('api', 'manual')`

**UPSERT strategy:**
```sql
INSERT INTO ad_snapshots (ad_id, snapshot_date, granularity, impressions, clicks, spend, ...)
VALUES ($1, $2, $3, $4, $5, $6, ...)
ON CONFLICT (ad_id, snapshot_date, granularity)
DO UPDATE SET
    impressions = EXCLUDED.impressions,
    clicks = EXCLUDED.clicks,
    spend = EXCLUDED.spend,
    -- ... all metric columns ...
    raw_response = EXCLUDED.raw_response,
    pulled_at = EXCLUDED.pulled_at;
```
When a re-pull happens (scheduler retry or manual correction), the latest data wins. The previous `raw_response` is overwritten — if you need audit history of raw responses, log them in `activity_log` before overwriting.

**Partitioning strategy (monthly — PostgreSQL native):**
```sql
CREATE TABLE ad_snapshots (
    ...
) PARTITION BY RANGE (snapshot_date);

CREATE TABLE ad_snapshots_2025_01 PARTITION OF ad_snapshots
    FOR VALUES FROM ('2025-01-01') TO ('2025-02-01');
CREATE TABLE ad_snapshots_2025_02 PARTITION OF ad_snapshots
    FOR VALUES FROM ('2025-02-01') TO ('2025-03-01');
-- ... one partition per month, created by scheduled migration or cron
```

**Indexes (per partition, created automatically by PostgreSQL):**
- `ad_id + snapshot_date` (most common query: daily data for all ads in a campaign)
- `snapshot_date` (range scans for date filters)
- `source` (filter manual vs API entries)

**`custom_metrics` JSONB examples:**
```json
// TikTok
{
  "thumb_stop_count": 45000,
  "profile_visits": 1200,
  "follows": 89
}

// Google Ads
{
  "quality_score": 8,
  "search_impression_share": 0.45,
  "top_impression_pct": 0.32
}
```

---

## 12. `v_ad_set_totals` (VIEW — NEW)

Rollup from ad-level to ad-set-level. Used for mid-level analysis.

```sql
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
    -- Ratio metrics recomputed from sums (NEVER averaged)
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
GROUP BY aset.id, aset.campaign_platform_id, aset.name;
```

---

## 13. `v_campaign_platform_totals` (VIEW — FIXED)

**CRITICAL FIX from v3.0:** All ratio metrics are now recomputed from underlying sums. `AVG()` of pre-computed ratios is statistically incorrect when volumes differ across ads.

```sql
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
    -- Budget pacing
    CASE WHEN cp.budget > 0
         THEN ROUND(SUM(s.spend) / cp.budget * 100, 2)
         ELSE 0 END                    AS budget_pct_used,
    -- Ratio metrics — RECOMPUTED FROM SUMS, never averaged
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
GROUP BY cp.id, cp.campaign_id, cp.platform_id, cp.budget, cp.budget_type;
```

**Why `daily` granularity in views, not `cumulative`:**
- Daily rows are additive — SUM of daily spend = actual total spend.
- Cumulative rows represent running totals — SUMming them double-counts.
- If you need "latest cumulative snapshot" for a quick dashboard read, query the most recent `cumulative` row per ad directly, not through the view.

---

## 14. `briefs`

Structured version of the client brief. One per campaign.

| Field | Type | Details |
|---|---|---|
| `id` | SERIAL, PK | |
| `campaign_id` | FK → campaigns | UNIQUE — one brief per campaign |
| `objective` | VARCHAR(200) | As stated by client |
| `kpis_requested` | JSONB | Array of KPIs client wants to track |
| `target_audience` | TEXT | Demographics, interests, behaviors |
| `geo_targeting` | JSONB | Countries, cities, radius targets |
| `budget_total` | DECIMAL(12,2) | As stated in brief (may differ from campaign budget) |
| `channels_requested` | JSONB | What client asked for |
| `channels_recommended` | JSONB | What you recommend after analysis |
| `creative_formats` | JSONB | What creative assets client can provide |
| `flight_start` | DATE | |
| `flight_end` | DATE | |
| `constraints` | TEXT | Brand safety, competitor exclusions, etc. |
| `version` | SMALLINT DEFAULT 1 | Brief revision counter |
| `ai_brief_quality_score` | SMALLINT | 1–10 — how complete the brief is |
| `ai_missing_info` | JSONB | Fields the AI flagged as missing |
| `ai_kpi_challenges` | JSONB | KPIs flagged as unrealistic vs benchmarks |
| `ai_questions_for_client` | JSONB | Clarifying questions to ask before committing |
| `ai_channel_rationale` | TEXT | Why these channels were recommended |
| `ai_budget_split` | JSONB | Suggested % per platform |
| `ai_media_plan_draft` | JSONB | Full draft media plan structure |
| `status` | VARCHAR(20) | CHECK: `draft`, `reviewed`, `approved`, `revision_requested` |
| `reviewed_by` | FK → users | Nullable |
| `reviewed_at` | TIMESTAMPTZ | Nullable |
| `created_at` | TIMESTAMPTZ | |
| `updated_at` | TIMESTAMPTZ | |

**CHECK constraint:** `status IN ('draft', 'reviewed', 'approved', 'revision_requested')`

**`creative_formats` JSONB:**
```json
{
  "available": ["static_image", "video_15s", "video_30s", "carousel"],
  "dimensions": {
    "feed": "1080x1080",
    "story": "1080x1920",
    "banner": "728x90"
  },
  "notes": "Client will provide 3 video cuts and 5 static visuals"
}
```

**`ai_budget_split` JSONB:**
```json
{
  "meta":     { "pct": 45, "amount": 22500, "rationale": "Broad reach for awareness phase" },
  "google":   { "pct": 35, "amount": 17500, "rationale": "Intent capture — search + display" },
  "tiktok":   { "pct": 20, "amount": 10000, "rationale": "Young travellers, video storytelling" }
}
```

**`ai_media_plan_draft` JSONB:**
```json
{
  "phases": [
    {
      "name": "Awareness",
      "duration_days": 14,
      "platforms": ["meta", "tiktok"],
      "formats": ["video", "story"],
      "kpis": ["reach", "vtr", "cpm"]
    },
    {
      "name": "Conversion",
      "duration_days": 16,
      "platforms": ["google", "meta"],
      "formats": ["search", "retargeting"],
      "kpis": ["ctr", "cpa", "conversions"]
    }
  ]
}
```

---

## 15. `reports`

Campaign reports — multiple types supported.

| Field | Type | Details |
|---|---|---|
| `id` | SERIAL, PK | |
| `campaign_id` | FK → campaigns | |
| `type` | VARCHAR(10) | CHECK: `weekly`, `monthly`, `mid`, `end`, `custom` |
| `period_start` | DATE | |
| `period_end` | DATE | |
| `title` | VARCHAR(200) | Auto-generated or manual |
| `executive_summary` | TEXT | AI-drafted, human-edited |
| `overall_performance` | VARCHAR(20) | CHECK: `on_track`, `underperforming`, `overperforming` |
| `ai_recommendations` | JSONB | Next steps suggested by AI |
| `status` | VARCHAR(20) | CHECK: `draft`, `reviewed`, `exported` |
| `version` | SMALLINT DEFAULT 1 | Increments on re-export |
| `exported_file_path` | VARCHAR(500) | Nullable — path to generated PPTX/PDF |
| `exported_at` | TIMESTAMPTZ | Nullable |
| `export_format` | VARCHAR(10) | CHECK: `pptx`, `pdf`, `both` |
| `created_by` | FK → users | |
| `reviewed_by` | FK → users | Nullable |
| `reviewed_at` | TIMESTAMPTZ | Nullable |
| `created_at` | TIMESTAMPTZ | |
| `updated_at` | TIMESTAMPTZ | |

**CHECK constraints:**
- `type IN ('weekly', 'monthly', 'mid', 'end', 'custom')`
- `overall_performance IN ('on_track', 'underperforming', 'overperforming')`
- `status IN ('draft', 'reviewed', 'exported')`
- `export_format IN ('pptx', 'pdf', 'both')`

---

## 16. `report_platform_sections`

One row per platform per report. AI commentary lives independently per platform.

**Note on intentional denormalization:** This table stores metric values at report generation time. This is deliberate — a report should reflect data as it was when generated, not change if later snapshots are corrected or re-pulled.

| Field | Type | Details |
|---|---|---|
| `id` | SERIAL, PK | |
| `report_id` | FK → reports | |
| `platform_id` | FK → platforms | |
| `spend` | DECIMAL(12,2) | Actual spend for this period (frozen at generation) |
| `budget` | DECIMAL(12,2) | Allocated budget for this period |
| `impressions` | BIGINT | |
| `reach` | BIGINT | Nullable |
| `clicks` | BIGINT | |
| `link_clicks` | BIGINT | Nullable |
| `ctr` | DECIMAL(8,4) | |
| `cpm` | DECIMAL(10,4) | |
| `cpc` | DECIMAL(10,4) | |
| `conversions` | INT | Nullable |
| `cpa` | DECIMAL(10,4) | Nullable |
| `leads` | INT | Nullable |
| `cpl` | DECIMAL(10,4) | Nullable |
| `video_views` | BIGINT | Nullable |
| `video_completions` | BIGINT | Nullable |
| `vtr` | DECIMAL(8,4) | Nullable |
| `frequency` | DECIMAL(8,4) | Nullable |
| `engagement` | BIGINT | Nullable |
| `performance_vs_benchmark` | VARCHAR(20) | CHECK: `above`, `within`, `below` |
| `ai_summary` | TEXT | AI-generated platform summary |
| `ai_highlights` | JSONB | Array of positive highlights |
| `ai_concerns` | JSONB | Array of flagged issues |
| `ai_suggested_action` | TEXT | Recommended action for remaining flight |
| `top_performing_ads` | JSONB | Best ads by primary KPI |
| `worst_performing_ads` | JSONB | Worst ads by primary KPI |
| `human_notes` | TEXT | Manual edits / overrides |
| `performance_flags` | JSONB | Detailed per-metric flag objects |
| `created_at` | TIMESTAMPTZ | |
| `updated_at` | TIMESTAMPTZ | |

**Unique constraint:** `report_id + platform_id`

**CHECK constraint:** `performance_vs_benchmark IN ('above', 'within', 'below')`

**`top_performing_ads` / `worst_performing_ads` JSONB:**
```json
[
  {
    "ad_id": 142,
    "ad_name": "RAM_Video_30s_Beach_V2",
    "format": "video",
    "primary_kpi": "cpa",
    "value": 32.50,
    "spend": 4500,
    "impressions": 125000
  }
]
```

**`performance_flags` JSONB:**
```json
[
  {
    "metric": "ctr",
    "value": 0.40,
    "benchmark_min": 0.80,
    "benchmark_max": 1.20,
    "kpi_target": 1.00,
    "status": "below",
    "severity": "high",
    "deviation_pct": -50
  }
]
```

---

## 17. `users`

| Field | Type | Details |
|---|---|---|
| `id` | SERIAL, PK | |
| `name` | VARCHAR(150) | |
| `email` | VARCHAR(150) | UNIQUE |
| `role` | VARCHAR(20) | CHECK: `admin`, `manager`, `viewer` |
| `password` | VARCHAR(255) | Hashed (bcrypt) |
| `password_reset_token` | VARCHAR(100) | Nullable |
| `password_reset_expires` | TIMESTAMPTZ | Nullable |
| `notification_preferences` | JSONB | What alerts each user receives |
| `is_active` | BOOLEAN DEFAULT true | Soft disable without deleting |
| `last_login_at` | TIMESTAMPTZ | |
| `created_at` | TIMESTAMPTZ | |
| `updated_at` | TIMESTAMPTZ | |

**CHECK constraint:** `role IN ('admin', 'manager', 'viewer')`

**`notification_preferences` JSONB:**
```json
{
  "performance_flag": true,
  "budget_warning":   true,
  "api_error":        true,
  "status_change":    false,
  "report_ready":     true,
  "brief_update":     false,
  "frequency": "realtime"
}
```

**Roles:**

| Role | Can do |
|---|---|
| `admin` | Everything — manage users, categories, benchmarks, platform connections |
| `manager` | Manage clients, campaigns, briefs, reports |
| `viewer` | Read-only — for clients or junior team members |

---

## 18. `notifications`

Persistent alert system. Performance flags, budget pacing, API failures, workflow updates.

| Field | Type | Details |
|---|---|---|
| `id` | BIGSERIAL, PK | High volume |
| `user_id` | FK → users | Who receives this |
| `type` | VARCHAR(50) | `performance_flag`, `budget_warning`, `api_error`, `status_change`, `report_ready`, `brief_update` |
| `severity` | VARCHAR(10) | CHECK: `info`, `warning`, `critical` |
| `title` | VARCHAR(200) | Short summary |
| `message` | TEXT | Full notification text |
| `entity_type` | VARCHAR(50) | campaigns, reports, briefs, platform_connections |
| `entity_id` | INT | ID of the relevant record |
| `meta` | JSONB | Additional context data |
| `is_read` | BOOLEAN DEFAULT false | |
| `read_at` | TIMESTAMPTZ | Nullable |
| `is_dismissed` | BOOLEAN DEFAULT false | |
| `is_actionable` | BOOLEAN DEFAULT false | Does this need a response? |
| `action_url` | VARCHAR(500) | Deep link to relevant page |
| `expires_at` | TIMESTAMPTZ | Nullable — auto-dismiss old alerts |
| `created_at` | TIMESTAMPTZ | |

**CHECK constraint:** `severity IN ('info', 'warning', 'critical')`

**Index:** `user_id + is_read + created_at DESC` (unread notifications query)

**`meta` JSONB examples:**
```json
// Performance flag
{
  "campaign_id": 12,
  "platform_slug": "meta",
  "metric": "ctr",
  "current_value": 0.40,
  "benchmark_min": 0.80,
  "kpi_target": 1.00,
  "deviation_pct": -50
}

// Budget pacing warning
{
  "campaign_id": 12,
  "platform_slug": "google",
  "budget": 50000,
  "spent": 42000,
  "days_elapsed": 15,
  "days_remaining": 15,
  "projected_overspend": 8000,
  "pacing_strategy": "even"
}

// API error
{
  "platform_connection_id": 3,
  "platform_slug": "meta",
  "error_count": 5,
  "last_error": "Token expired — refresh failed"
}
```

**Notification triggers:**

| Trigger | Type | Severity |
|---|---|---|
| KPI drops below category benchmark | performance_flag | warning |
| KPI critically below (>30% deviation) | performance_flag | critical |
| KPI drops below client's kpi_target | performance_flag | warning |
| Spend pace exceeds budget trajectory by >15% | budget_warning | warning |
| Spend pace exceeds budget trajectory by >30% | budget_warning | critical |
| API token refresh fails | api_error | warning |
| platform_connection.error_count >= 5 | api_error | critical |
| Campaign status changes | status_change | info |
| Brief status changes | brief_update | info |
| Report exported | report_ready | info |
| Client contract expiring within 30 days | status_change | warning |

---

## 19. `activity_log`

Full audit trail. Every create, update, delete, export, status change — by user or by system.

| Field | Type | Details |
|---|---|---|
| `id` | BIGSERIAL, PK | |
| `user_id` | FK → users | Nullable — system/scheduled jobs use null |
| `action` | VARCHAR(30) | `created`, `updated`, `deleted`, `exported`, `reviewed`, `approved`, `status_changed`, `data_pulled`, `login`, `logout` |
| `entity_type` | VARCHAR(50) | campaigns, clients, reports, briefs, platform_connections… |
| `entity_id` | INT | ID of the relevant record |
| `entity_name` | VARCHAR(200) | Denormalized — avoids joins for display |
| `changes` | JSONB | Before/after diff for updates |
| `ip_address` | VARCHAR(45) | IPv4 or IPv6 |
| `user_agent` | VARCHAR(500) | |
| `created_at` | TIMESTAMPTZ | Indexed |

**Indexes:**
- `entity_type + entity_id` — all changes to a specific record
- `user_id + created_at DESC` — what did this user do recently
- `created_at DESC` — global activity feed

**`changes` JSONB:**
```json
{
  "status": {
    "old": "draft",
    "new": "active"
  },
  "total_budget": {
    "old": 50000,
    "new": 75000
  }
}
```

**Laravel implementation:** `HasActivityLog` trait on all audited models, hooked into Eloquent `created`, `updated`, `deleted` events with automatic `getOriginal()` vs `getAttributes()` diff.

---

## Full Relationships

```
platforms              ──< platform_connections
platforms              ──< category_benchmarks
platforms              ──< category_channel_recommendations
platforms              ──< campaign_platforms
platforms              ──< report_platform_sections

categories             ──< category_benchmarks
categories             ──< category_channel_recommendations
categories             ──< clients

clients                ──< campaigns

campaigns              ──< campaign_platforms
campaigns              ──1 briefs              (UNIQUE on campaign_id)
campaigns              ──< reports

campaign_platforms     ──1 platform_connections (nullable FK)
campaign_platforms     ──< ad_sets

ad_sets                ──< ads
ads                    ──< ad_snapshots

reports                ──< report_platform_sections

users                  ──< campaigns           (created_by)
users                  ──< reports             (created_by, reviewed_by)
users                  ──< briefs              (reviewed_by)
users                  ──< notifications
users                  ──< activity_log
users                  ──< platform_connections (created_by)

[VIEW] v_ad_set_totals
    aggregates: ad_snapshots → ads → ad_sets

[VIEW] v_campaign_platform_totals
    aggregates: ad_snapshots → ads → ad_sets → campaign_platforms
```

---

## Data Retention Policy

| Table | Retention | Action |
|---|---|---|
| `ad_snapshots` (daily) | 18 months | After 18 months, aggregate to monthly summary rows and drop daily partitions |
| `ad_snapshots` (cumulative) | Indefinite | Last cumulative row per ad is the final truth — never delete |
| `raw_response` in ad_snapshots | 6 months | Set to NULL after 6 months to reclaim storage (metrics are already extracted) |
| `notifications` | 90 days if dismissed | Cron job: delete where `is_dismissed = true AND created_at < NOW() - INTERVAL '90 days'` |
| `activity_log` | 2 years | Archive to cold storage (S3/GCS) after 2 years, delete from PostgreSQL |
| Partition creation | Automated | Cron job or Laravel command creates next month's partition on the 1st of each month |

---

## Migration Order

| Phase | Tables | Depends on |
|---|---|---|
| 1 | `users` | Nothing |
| 2 | `platforms` | Nothing |
| 3 | `platform_connections` | users, platforms |
| 4 | `categories` | Nothing |
| 5 | `category_benchmarks` | categories, platforms |
| 6 | `category_channel_recommendations` | categories, platforms |
| 7 | `clients` | categories |
| 8 | `campaigns` | clients, users |
| 9 | `campaign_platforms` | campaigns, platforms, platform_connections |
| 10 | `ad_sets` | campaign_platforms |
| 11 | `ads` | ad_sets |
| 12 | `ad_snapshots` + monthly partitions | ads |
| 13 | `v_ad_set_totals` (VIEW) | ad_sets, ads, ad_snapshots |
| 14 | `v_campaign_platform_totals` (VIEW) | campaign_platforms, ad_sets, ads, ad_snapshots |
| 15 | `briefs` | campaigns, users |
| 16 | `reports` | campaigns, users |
| 17 | `report_platform_sections` | reports, platforms |
| 18 | `notifications` | users |
| 19 | `activity_log` | users |

---

## Architecture: Laravel + Python Responsibility Split

| Layer | Stack | Handles |
|---|---|---|
| Web app, auth, CRUD, UI | Laravel 11 | All user-facing operations |
| Data collectors, scheduling | Python 3.12 + Celery + Redis | API integrations, snapshot creation |
| AI analysis | Python + Claude API | Brief analysis, benchmark flagging, report commentary |
| Background jobs | Laravel Queues (Redis driver) | Notifications, exports, emails |

### Python ↔ Laravel Communication

**Reads:** Python reads directly from PostgreSQL (read-only connection, separate DB user `havas_reader`) for AI analysis and data transformation.

**Writes:** Python POSTs to Laravel's internal API (`/internal/v1/snapshots`) to create `ad_snapshots`. Laravel handles validation, fires Eloquent observers (audit log, notifications, benchmark checks). Internal API authenticated via shared secret in `X-Internal-Token` header.

### Data Pull Flow

```
Python Celery Beat Scheduler (runs every 6 hours for active campaigns)
    → For each active campaign_platform where campaign.status = 'active':
        → Check platform_connection.is_connected == true
        → Check platform_connection.error_count < 5
        → Check platform.rate_limit_config
        → Query platform API using external_campaign_id
        → For each ad in response:
            → UPSERT ad_set (by external_id)
            → UPSERT ad (by external_id)
            → POST ad_snapshot to Laravel /internal/v1/snapshots
                → Laravel validates payload
                → UPSERT ad_snapshots row
                → Fires SnapshotCreated event:
                    → BenchmarkChecker → compares vs category_benchmarks AND kpi_targets
                        → Creates notifications if thresholds breached
                    → PacingChecker → compares spend trajectory vs budget + pacing_strategy
                        → Creates budget_warning notifications if needed
                    → ActivityLogger → logs data_pulled to activity_log
                    → SyncUpdater → updates campaign_platform.last_sync_at
        → On success: reset platform_connection.error_count = 0
        → On failure: increment platform_connection.error_count
            → If error_count >= 5: set is_connected = false, create critical notification
```

---

## What This Model Enables

| Capability | How |
|---|---|
| Multi-platform dashboard | `v_campaign_platform_totals` per campaign |
| Spend vs budget pacing | `budget_pct_used` from view + `pacing_strategy` for alert thresholds |
| Benchmark flagging | View metrics vs `category_benchmarks` — auto notifications |
| KPI target tracking | View metrics vs `campaigns.kpi_targets` — separate from benchmarks |
| Ad creative analysis | `ad_snapshots` grouped by `ad_id` — which creative wins |
| Ad set comparison | `v_ad_set_totals` — which targeting strategy performs best |
| AI brief analysis | `briefs` + `category_channel_recommendations` + `creative_formats` → Claude |
| Brief quality scoring | `ai_brief_quality_score` + `ai_missing_info` |
| KPI challenge before committing | `ai_kpi_challenges` vs `category_benchmarks` |
| Report AI commentary | `report_platform_sections` ← Claude reads views, writes per platform |
| Top/worst ad identification | `report_platform_sections.top_performing_ads` / `worst_performing_ads` |
| PPTX export | `reports` + `report_platform_sections` → python-pptx template |
| Token refresh management | `platform_connections.token_expires_at` + circuit breaker |
| Adding new platforms | Single INSERT into `platforms` — zero migrations |
| Adding new categories | Single INSERT into `categories` + benchmark rows |
| Full audit trail | `activity_log` on all entities |
| Per-user alert preferences | `users.notification_preferences` JSONB |
| Manual LinkedIn entry | `ad_snapshots.source = 'manual'` |
| Multi-currency support | `clients.currency` + `campaigns.currency` |
| Contract renewal alerts | `clients.contract_end` approaching → notification |
| Data retention automation | Monthly partition management + cleanup cron |

---

*End of document — v3.1*
