# Havas Media Buying Platform — Global Copilot Instructions

## Project Overview
This is a media buying campaign management platform for Havas Morocco.
- **Backend:** Laravel 13 (PHP 8.3) + PostgreSQL 17
- **Data Collectors:** Python 3.12 + Celery + Redis
- **AI Layer:** Claude API via Anthropic Python SDK
- **OS:** Windows (Laragon local dev environment)

## Architecture Rules
- PostgreSQL only — use TIMESTAMPTZ, JSONB, SERIAL/BIGSERIAL
- No database ENUMs — use VARCHAR + CHECK constraints via DB::statement()
- Platforms are a lookup table (platforms table), never hardcoded strings or ENUMs
- All timestamps use timezone Africa/Casablanca (GMT+1, no DST)
- API credentials encrypted via Laravel Crypt facade (EncryptsAttributes trait)
- Python reads DB directly (read-only user havas_reader), writes via Laravel internal API at /internal/v1/
- Internal API authenticated via X-Internal-Token header

## Coding Standards — Laravel / PHP
- PHP 8.3 features: backed enums, typed properties, match expressions, readonly where appropriate
- Every model defines explicit $fillable — NEVER use $guarded = []
- JSONB fields cast to 'array' in $casts
- Status/type fields cast to PHP backed enums (in app/Enums/)
- Use Form Requests for validation, not inline validation in controllers
- Services contain business logic, not models or controllers
- Controllers are thin — they validate input, call a service, return response
- Use HasActivityLog trait on all models EXCEPT AdSnapshot, Notification, ActivityLog
- Use EncryptsAttributes trait on PlatformConnection model
- Consistent JSON API response: { "data": ..., "meta": ... }

## Coding Standards — Python
- Python 3.12+, type hints on ALL function signatures
- Pydantic for data validation and schema definitions
- httpx for HTTP client (NOT requests library)
- tenacity for retry logic with exponential backoff
- Celery + Redis for task scheduling and background jobs
- No print() statements — use logging module exclusively
- All dates converted to Africa/Casablanca timezone before storing as snapshot_date

## Database Rules
- Ad snapshots use UPSERT: ON CONFLICT (ad_id, snapshot_date, granularity) DO UPDATE
- Monthly partitions on ad_snapshots table (not quarterly)
- Views use daily granularity rows for aggregation (not cumulative — summing cumulative double-counts)
- Foreign keys reference the platforms lookup table, never hardcoded platform strings
- CHECK constraints have named constraints: e.g. chk_campaigns_status
- Unique constraints: campaign_id + platform_id on campaign_platforms, ad_id + snapshot_date + granularity on ad_snapshots

## Critical Math Rule
Ratio metrics (CTR, CPM, CPC, CPA, CPL, VTR, frequency) must NEVER be averaged with AVG().
Always recompute from underlying sums:
- CTR = SUM(clicks) / SUM(impressions) * 100
- CPM = SUM(spend) / SUM(impressions) * 1000
- CPC = SUM(spend) / SUM(clicks)
- CPA = SUM(spend) / SUM(conversions)
- CPL = SUM(spend) / SUM(leads)
- VTR = SUM(video_views) / SUM(impressions) * 100
- Frequency = SUM(impressions) / SUM(reach)

## Data Model Reference
The complete schema (19 tables, 2 views) is documented in docs/havas-data-model-v3.1.md.
Always consult this file before creating or modifying any database-related code.

## File Structure Reference
- docs/copilot-01-project-setup.md — Laravel project init, packages, middleware, folder structure
- docs/copilot-02-migrations.md — All 19 migration files with exact code
- docs/copilot-03-models-relationships.md — All Eloquent models, traits, relationships
- docs/copilot-04-services-logic.md — Services, observers, events, listeners, commands
- docs/copilot-05-python-collectors.md — Python project structure, Celery, collectors, AI

## Workflow Rule
- Maintain and update a task TODO list while working.
- Every time a unit of work is finished, immediately commit the completed changes with a clear scoped message.
- After each commit, re-check and update the TODO list before starting the next unit of work.
