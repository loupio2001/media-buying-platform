# Remaining Tasks — Havas Media Buying Platform

## Context
Comprehensive task list of all remaining work vs the spec in `.github/copilot-instructions.md` and docs (`copilot-01` through `copilot-05` + `havas-data-model-v3.1.md`). Backend is ~85-90% complete. These are the gaps to close.

**Date:** 2026-03-31
**Completed so far:** 22 migrations, 17 models, 7 enums, 18 controllers, 17 form requests, 19 services, 4 events/listeners, 5 artisan commands, 2 middleware, 3 collectors (Meta/Google/TikTok), multi-provider AI system, 35+ tests, 8 Blade views.

---

## PYTHON COLLECTORS — Missing Modules

### TASK 1: `havas_collectors/config/settings.py`
- **Spec:** `docs/copilot-05-python-collectors.md` section 3
- **What:** Create centralized settings module that loads `.env` and exposes: `DATABASE_URL`, `LARAVEL_API_URL`, `INTERNAL_API_TOKEN`, `REDIS_URL`, `ANTHROPIC_API_KEY`, `APP_TIMEZONE`
- **Pattern:** See exact code in `copilot-05` section 3. Uses `python-dotenv`
- **Note:** Check if existing code loads config a different way (via `__init__.py` or inline) — if so, refactor to match spec or skip

### TASK 2: `havas_collectors/utils/metrics.py`
- **Spec:** `docs/copilot-05-python-collectors.md` section 1 (file tree)
- **What:** Metric calculation helpers. Must follow the critical math rules from `.github/copilot-instructions.md`:
  - CTR = SUM(clicks) / SUM(impressions) * 100
  - CPM = SUM(spend) / SUM(impressions) * 1000
  - CPC = SUM(spend) / SUM(clicks)
  - CPA = SUM(spend) / SUM(conversions)
  - CPL = SUM(spend) / SUM(leads)
  - VTR = SUM(video_views) / SUM(impressions) * 100
  - Frequency = SUM(impressions) / SUM(reach)
- **Rule:** Ratio metrics must NEVER be averaged — always recomputed from sums
- **Tests:** Add `havas_collectors/tests/unit/test_metrics.py`

### TASK 3: `havas_collectors/ai/brief_analyzer.py`
- **Spec:** `docs/copilot-05-python-collectors.md` section 11
- **What:** AI-powered brief analysis using Claude API. Sends brief text + category benchmarks + budget to Claude, returns structured JSON with:
  - `brief_quality_score` (1-10)
  - `missing_information` list
  - `kpi_challenges` list
  - `questions_for_client` list
  - `channel_rationale` paragraph
  - `budget_split` per platform
  - `media_plan_draft` with phases
- **Pattern:** Exact code provided in `copilot-05` section 11. Uses `anthropic` SDK + `db/reader.py` for benchmarks
- **Adaptation:** Should use the existing multi-provider pattern in `havas_collectors/ai/providers/` rather than hardcoding Anthropic directly
- **Tests:** Add `havas_collectors/tests/unit/test_brief_analyzer.py`

### TASK 4: `havas_collectors/ai/prompts/brief_analysis.txt`
- **Spec:** `docs/copilot-05-python-collectors.md` section 1 (file tree)
- **What:** Extract the brief analysis prompt from the inline code in `brief_analyzer.py` into a separate template file
- **Pattern:** Template with placeholders for `{brief_raw}`, `{category_name}`, `{budget}`, `{currency}`, `{benchmarks}`

### TASK 5: `havas_collectors/ai/prompts/report_commentary.txt`
- **Spec:** `docs/copilot-05-python-collectors.md` section 1 (file tree)
- **What:** Extract the report commentary prompt into a separate template file
- **Note:** Check if `ai/report_commentator.py` already has inline prompts — if so, extract to file

### TASK 6: `havas_collectors/tasks/ai_tasks.py`
- **Spec:** `docs/copilot-05-python-collectors.md` section 1 (file tree)
- **What:** Celery tasks for AI analysis dispatch:
  - `analyze_brief_task(brief_id)` — fetches brief data, runs `brief_analyzer.analyze_brief()`, posts results back to Laravel
  - `generate_report_commentary_task(report_id)` — fetches report data, runs commentary generation, posts back
- **Pattern:** Follow same task structure as `havas_collectors/tasks/pull_tasks.py` (Celery `@app.task` decorators, retry logic, error handling)
- **Dependencies:** `ai/brief_analyzer.py`, `ai/report_commentator.py`, `api/laravel_client.py`

### TASK 7: `havas_collectors/collectors/youtube_collector.py` (LOW PRIORITY)
- **Spec:** `docs/copilot-05-python-collectors.md` section 1 (file tree)
- **What:** YouTube data collection via Google Ads API
- **Note:** Docs say "Implement when needed" — this is optional/deferred
- **Pattern:** Follow `collectors/base_collector.py` abstract class + `collectors/google_collector.py` patterns

---

## LARAVEL FRONTEND — Missing Blade Views

### TASK 8: Reports Management UI
- **Files to create:**
  - `resources/views/reports/index.blade.php` — List reports by campaign
  - `resources/views/reports/show.blade.php` — View report with platform sections + AI commentary
  - `resources/views/reports/create.blade.php` — Create report form (select campaign, type, period)
- **Backend already exists:** `app/Http/Controllers/Api/ReportController.php` + `app/Services/Api/ReportApiService.php` + `app/Services/ReportGenerator.php`
- **Routes to add in `routes/web.php`:**
  - `GET /reports` — list
  - `GET /reports/create` — create form
  - `POST /reports` — store
  - `GET /reports/{report}` — show
- **Controllers to add:** `app/Http/Controllers/Web/ReportWebController.php`
- **Key feature:** Display AI commentary per platform section, with "Regenerate AI Comments" button that calls the existing `reports.ai-comments.regenerate` API route
- **Layout:** Extend `resources/views/layouts/app.blade.php`

### TASK 9: Briefs Management UI
- **Files to create:**
  - `resources/views/briefs/index.blade.php` — List briefs
  - `resources/views/briefs/show.blade.php` — View brief + AI analysis results
  - `resources/views/briefs/create.blade.php` — Upload/create brief form
- **Backend already exists:** `app/Http/Controllers/Api/BriefController.php` + `app/Services/Api/BriefApiService.php`
- **Routes to add in `routes/web.php`:**
  - `GET /briefs` — list
  - `GET /briefs/create` — create form
  - `POST /briefs` — store
  - `GET /briefs/{brief}` — show with AI analysis display
- **Controllers to add:** `app/Http/Controllers/Web/BriefWebController.php`
- **Key feature:** Display AI analysis JSON (quality score, missing info, budget split, media plan draft) in structured UI cards

### TASK 10: Client Management UI
- **Files to create:**
  - `resources/views/clients/index.blade.php` — List clients
  - `resources/views/clients/create.blade.php` — Create/edit client form
  - `resources/views/clients/show.blade.php` — Client detail with campaigns
- **Backend already exists:** `app/Http/Controllers/Api/ClientController.php` + `app/Services/Api/ClientApiService.php`
- **Routes to add in `routes/web.php`:**
  - `GET /clients` — list
  - `GET /clients/create` — create form
  - `POST /clients` — store
  - `GET /clients/{client}` — show
- **Controllers to add:** `app/Http/Controllers/Web/ClientWebController.php`

### TASK 11: Notification Center UI
- **Files to create:**
  - `resources/views/components/notification-bell.blade.php` — Navbar notification dropdown
  - `resources/views/notifications/index.blade.php` — Full notification list page
- **Backend already exists:** `app/Http/Controllers/Api/NotificationController.php` + `app/Services/Api/UserNotificationApiService.php`
- **Routes to add in `routes/web.php`:**
  - `GET /notifications` — full list page
- **Controllers to add:** `app/Http/Controllers/Web/NotificationWebController.php`
- **Key feature:** Mark as read/dismiss actions, filter by type (performance_flag, budget_warning), severity badges
- **Integration:** Add notification bell component to `resources/views/layouts/app.blade.php` navbar

### TASK 12: User/Admin Management UI (ADMIN ONLY)
- **Files to create:**
  - `resources/views/admin/users/index.blade.php` — List users
  - `resources/views/admin/users/create.blade.php` — Create/edit user
- **Backend needed:** Create `app/Services/Api/UserApiService.php` + `app/Http/Controllers/Web/Admin/UserController.php`
- **Routes to add in `routes/web.php`:** `GET /admin/users`, `GET /admin/users/create`, `POST /admin/users`, `GET /admin/users/{user}/edit`, `PATCH /admin/users/{user}` — gated with `role:admin` middleware
- **Note:** Only 3 roles (admin, manager, viewer) per `app/Enums/UserRole.php`

### TASK 13: Category & Benchmark Management UI (ADMIN ONLY)
- **Files to create:**
  - `resources/views/admin/categories/index.blade.php` — List categories with benchmarks
  - `resources/views/admin/categories/edit.blade.php` — Edit category benchmarks per platform per metric
- **Backend needed:** Create `app/Http/Controllers/Web/Admin/CategoryController.php`
- **Key feature:** Editable benchmark table (metric x platform matrix with min/max values)
- **Models to reference:** `app/Models/Category.php`, `app/Models/CategoryBenchmark.php`, `app/Models/Platform.php`

---

## LARAVEL BACKEND — Minor Gaps

### TASK 14: Web Controllers for New Views
- Create `app/Http/Controllers/Web/ReportWebController.php`
- Create `app/Http/Controllers/Web/BriefWebController.php`
- Create `app/Http/Controllers/Web/ClientWebController.php`
- Create `app/Http/Controllers/Web/NotificationWebController.php`
- Create `app/Http/Controllers/Web/Admin/UserController.php`
- Create `app/Http/Controllers/Web/Admin/CategoryController.php`
- **Pattern:** Thin controllers — validate input, call existing API services, return Blade views. Follow the pattern of existing `app/Http/Controllers/CampaignListController.php` and `app/Http/Controllers/DashboardController.php`
- **Existing services to reuse:** `ReportApiService`, `BriefApiService`, `ClientApiService`, `UserNotificationApiService`, `PlatformApiService`

### TASK 15: Register Laravel Scheduled Commands
- **File:** `routes/console.php`
- **What:** Register the artisan commands to run on schedule:
  - `notifications:cleanup` — daily at 02:00
  - `snapshots:cleanup-raw-responses` — weekly (Sunday 03:00)
  - `partitions:create-monthly` — monthly (1st of month at 00:05)
  - `activity-log:archive` — weekly (Sunday 04:00)
- **Spec:** `docs/copilot-04-services-logic.md` section 10
- **Commands already exist in:** `app/Console/Commands/`

### TASK 16: Google & TikTok OAuth Services
- **Files to create:**
  - `app/Services/PlatformOAuth/GoogleOAuthService.php`
  - `app/Services/PlatformOAuth/TikTokOAuthService.php`
- **What:** OAuth authorize/callback flows for Google Ads and TikTok
- **Pattern:** Follow the existing `app/Services/PlatformOAuth/MetaOAuthService.php`
- **Update:** `app/Http/Controllers/PlatformConnectionOAuthController.php` to dispatch to the correct provider based on platform slug

---

## TESTING GAPS

### TASK 17: Missing Test Coverage
- **Python tests to add:**
  - `havas_collectors/tests/unit/test_brief_analyzer.py`
  - `havas_collectors/tests/unit/test_ai_tasks.py`
  - `havas_collectors/tests/unit/test_metrics.py`
- **Laravel tests to verify/add:**
  - Report creation + AI commentary regeneration feature test
  - Brief creation + AI analysis feature test
  - Notification read/dismiss flow feature test
  - Integration test: end-to-end snapshot ingestion -> benchmark check -> notification creation
- **Existing test patterns:** `tests/Feature/`, `tests/Unit/`, `tests/Integration/`, `havas_collectors/tests/unit/`

---

## INFRASTRUCTURE / CONFIG

### TASK 18: Laravel Task Scheduling (Production)
- **File:** `routes/console.php`
- Register all scheduled tasks (overlaps with Task 15)
- Ensure `CreateMonthlyPartition` runs on 1st of each month
- Set up `php artisan schedule:run` in system cron

### TASK 19: Production Deployment Config (LOW PRIORITY)
- Systemd service files for Celery worker + beat (referenced in `docs/copilot-05-python-collectors.md` section 12)
- Queue worker configuration for Laravel (Redis)
- Supervisor or systemd for `php artisan queue:work`

---

## Priority Order (Recommended)

| Priority | Tasks | Description |
|----------|-------|-------------|
| 1 | Tasks 1-6 | Complete Python collector modules to match spec |
| 2 | Tasks 8-9 | Reports + Briefs UI (highest user value) |
| 3 | Task 11 | Notification center (important for monitoring) |
| 4 | Task 14 | Web controllers for new views |
| 5 | Task 15 | Schedule artisan commands |
| 6 | Tasks 10, 12, 13 | Client/User/Category admin UI |
| 7 | Task 16 | Google/TikTok OAuth |
| 8 | Task 17 | Test coverage gaps |
| 9 | Tasks 7, 18, 19 | Low priority / deferred |

---

## Reference Files
- `.github/copilot-instructions.md` — Global rules and coding standards
- `docs/havas-data-model-v3.1.md` — Complete schema (19 tables, 2 views)
- `docs/copilot-01-project-setup.md` — Laravel setup, packages, config
- `docs/copilot-02-migrations.md` — All migration code
- `docs/copilot-03-models-relationships.md` — All model code
- `docs/copilot-04-services-logic.md` — Services, events, listeners, commands
- `docs/copilot-05-python-collectors.md` — Python structure, collectors, AI, Celery
