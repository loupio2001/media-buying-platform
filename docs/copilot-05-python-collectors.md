# Copilot Instructions — Part 5: Python Data Collectors & AI Layer

**Project:** Havas Media Buying Platform  
**Reference:** `havas-data-model-v3.1.md`  
**This file covers:** Python project structure, Celery scheduler, platform API collectors, Laravel API client, AI analysis

---

## 1. Python Project Structure

```
havas-collectors/
├── pyproject.toml               # Dependencies + config
├── .env                         # Environment variables
├── config/
│   ├── __init__.py
│   └── settings.py              # Loads .env, database URLs, API config
├── db/
│   ├── __init__.py
│   └── reader.py                # Read-only PostgreSQL connection
├── api/
│   ├── __init__.py
│   └── laravel_client.py        # HTTP client for Laravel internal API
├── collectors/
│   ├── __init__.py
│   ├── base.py                  # Abstract collector class
│   ├── meta_collector.py        # Meta (Facebook) Ads API
│   ├── google_collector.py      # Google Ads API
│   ├── tiktok_collector.py      # TikTok Marketing API
│   └── youtube_collector.py     # YouTube via Google Ads API
├── ai/
│   ├── __init__.py
│   ├── brief_analyzer.py        # Analyze briefs via Claude API
│   ├── report_commentator.py    # Generate report commentary via Claude API
│   └── prompts/
│       ├── brief_analysis.txt
│       └── report_commentary.txt
├── tasks/
│   ├── __init__.py
│   ├── celery_app.py            # Celery configuration
│   ├── pull_tasks.py            # Data pull tasks
│   └── ai_tasks.py              # AI analysis tasks
└── utils/
    ├── __init__.py
    ├── timezone.py              # Timezone conversion helpers
    └── metrics.py               # Metric calculation helpers
```

---

## 2. Dependencies

**`pyproject.toml`:**

```toml
[project]
name = "havas-collectors"
version = "1.0.0"
requires-python = ">=3.12"
dependencies = [
    "celery[redis]>=5.4",
    "redis>=5.0",
    "psycopg2-binary>=2.9",
    "sqlalchemy>=2.0",
    "httpx>=0.27",             # HTTP client for Laravel API + platform APIs
    "python-dotenv>=1.0",
    "anthropic>=0.40",          # Claude API SDK
    "facebook-business>=20.0",  # Meta Marketing API
    "google-ads>=25.0",         # Google Ads API
    "pydantic>=2.5",            # Data validation
    "tenacity>=9.0",            # Retry logic
]
```

---

## 3. Configuration

**`.env`:**

```env
# Database (read-only)
DB_HOST=127.0.0.1
DB_PORT=5432
DB_NAME=havas_media
DB_USER=havas_reader
DB_PASSWORD=<reader-password>

# Laravel Internal API
LARAVEL_API_URL=http://127.0.0.1:8000/internal/v1
INTERNAL_API_TOKEN=<same-token-as-laravel-env>

# Redis (Celery broker)
REDIS_URL=redis://127.0.0.1:6379/0

# Claude API
ANTHROPIC_API_KEY=<your-key>

# Timezone
APP_TIMEZONE=Africa/Casablanca
```

**`config/settings.py`:**

```python
import os
from dotenv import load_dotenv

load_dotenv()

DATABASE_URL = (
    f"postgresql://{os.getenv('DB_USER')}:{os.getenv('DB_PASSWORD')}"
    f"@{os.getenv('DB_HOST')}:{os.getenv('DB_PORT')}/{os.getenv('DB_NAME')}"
)

LARAVEL_API_URL = os.getenv("LARAVEL_API_URL")
INTERNAL_API_TOKEN = os.getenv("INTERNAL_API_TOKEN")
REDIS_URL = os.getenv("REDIS_URL", "redis://127.0.0.1:6379/0")
ANTHROPIC_API_KEY = os.getenv("ANTHROPIC_API_KEY")
APP_TIMEZONE = os.getenv("APP_TIMEZONE", "Africa/Casablanca")
```

---

## 4. Database Reader (Read-Only)

**`db/reader.py`:**

```python
from sqlalchemy import create_engine, text
from config.settings import DATABASE_URL

engine = create_engine(DATABASE_URL, pool_size=5, max_overflow=2, echo=False)


def get_active_campaign_platforms() -> list[dict]:
    """Get all campaign_platforms that should be pulled."""
    query = text("""
        SELECT
            cp.id AS campaign_platform_id,
            cp.external_campaign_id,
            cp.platform_id,
            cp.budget,
            cp.budget_type,
            p.slug AS platform_slug,
            p.rate_limit_config,
            pc.id AS connection_id,
            pc.account_id,
            pc.auth_type,
            pc.is_connected,
            pc.error_count,
            c.id AS campaign_id,
            c.name AS campaign_name,
            c.status AS campaign_status,
            c.start_date,
            c.end_date
        FROM campaign_platforms cp
        JOIN platforms p ON p.id = cp.platform_id
        LEFT JOIN platform_connections pc ON pc.id = cp.platform_connection_id
        JOIN campaigns c ON c.id = cp.campaign_id
        WHERE c.status = 'active'
          AND cp.is_active = true
          AND (pc.is_connected = true OR pc.id IS NULL)
          AND (pc.error_count < 5 OR pc.id IS NULL)
        ORDER BY p.slug, cp.id
    """)

    with engine.connect() as conn:
        rows = conn.execute(query).mappings().all()
        return [dict(row) for row in rows]


def get_connection_credentials(connection_id: int) -> dict | None:
    """
    Get decrypted credentials for a platform connection.
    NOTE: Credentials are encrypted by Laravel's Crypt facade.
    Python must either:
      (a) Call a Laravel endpoint to get decrypted credentials, OR
      (b) Use the same APP_KEY + compatible decryption (complex)

    RECOMMENDED: Add a Laravel endpoint: GET /internal/v1/connections/{id}/credentials
    """
    # For now, return raw — implement decryption strategy
    query = text("""
        SELECT account_id, auth_type, access_token, refresh_token, api_key, extra_credentials
        FROM platform_connections WHERE id = :id
    """)
    with engine.connect() as conn:
        row = conn.execute(query, {"id": connection_id}).mappings().first()
        return dict(row) if row else None


def get_category_benchmarks(category_id: int, platform_id: int) -> list[dict]:
    """Get benchmarks for AI analysis."""
    query = text("""
        SELECT metric, min_value, max_value, unit
        FROM category_benchmarks
        WHERE category_id = :cat_id AND platform_id = :plat_id
    """)
    with engine.connect() as conn:
        rows = conn.execute(query, {"cat_id": category_id, "plat_id": platform_id}).mappings().all()
        return [dict(row) for row in rows]
```

---

## 5. Laravel API Client

**`api/laravel_client.py`:**

```python
import httpx
from config.settings import LARAVEL_API_URL, INTERNAL_API_TOKEN
from tenacity import retry, stop_after_attempt, wait_exponential


class LaravelClient:
    def __init__(self):
        self.base_url = LARAVEL_API_URL
        self.headers = {
            "X-Internal-Token": INTERNAL_API_TOKEN,
            "Content-Type": "application/json",
            "Accept": "application/json",
        }
        self.client = httpx.Client(timeout=30, headers=self.headers)

    @retry(stop=stop_after_attempt(3), wait=wait_exponential(min=1, max=10))
    def upsert_ad_set(self, data: dict) -> dict:
        resp = self.client.post(f"{self.base_url}/ad-sets/upsert", json=data)
        resp.raise_for_status()
        return resp.json()

    @retry(stop=stop_after_attempt(3), wait=wait_exponential(min=1, max=10))
    def upsert_ad(self, data: dict) -> dict:
        resp = self.client.post(f"{self.base_url}/ads/upsert", json=data)
        resp.raise_for_status()
        return resp.json()

    @retry(stop=stop_after_attempt(3), wait=wait_exponential(min=1, max=10))
    def post_snapshots(self, snapshots: list[dict]) -> dict:
        resp = self.client.post(
            f"{self.base_url}/snapshots/batch",
            json={"snapshots": snapshots},
        )
        resp.raise_for_status()
        return resp.json()

    @retry(stop=stop_after_attempt(3), wait=wait_exponential(min=1, max=10))
    def update_connection_status(self, connection_id: int, success: bool, error_msg: str = None):
        resp = self.client.patch(
            f"{self.base_url}/platform-connections/{connection_id}/sync-status",
            json={"success": success, "error_msg": error_msg},
        )
        resp.raise_for_status()
        return resp.json()

    def close(self):
        self.client.close()
```

---

## 6. Base Collector

**`collectors/base.py`:**

```python
from abc import ABC, abstractmethod
from datetime import date
from api.laravel_client import LaravelClient
from utils.timezone import to_casablanca_date
import logging

logger = logging.getLogger(__name__)


class BaseCollector(ABC):
    """
    Abstract base class for all platform collectors.
    Each collector must implement:
      - authenticate()
      - fetch_campaign_data()
      - transform_to_snapshots()
    """

    def __init__(self, laravel: LaravelClient):
        self.laravel = laravel

    @abstractmethod
    def authenticate(self, credentials: dict) -> None:
        """Initialize API client with credentials."""
        pass

    @abstractmethod
    def fetch_campaign_data(
        self,
        account_id: str,
        external_campaign_id: str,
        date_from: date,
        date_to: date,
    ) -> list[dict]:
        """
        Fetch ad-level data from the platform API.
        Returns raw API response data grouped by ad.
        """
        pass

    @abstractmethod
    def transform_to_snapshots(
        self,
        raw_data: list[dict],
        campaign_platform_id: int,
    ) -> tuple[list[dict], list[dict], list[dict]]:
        """
        Transform raw API data into:
        - ad_sets: list of dicts for upsert
        - ads: list of dicts for upsert
        - snapshots: list of dicts for batch insert

        Each snapshot dict must match the AdSnapshot schema.
        """
        pass

    def pull(
        self,
        credentials: dict,
        account_id: str,
        external_campaign_id: str,
        campaign_platform_id: int,
        date_from: date,
        date_to: date,
    ) -> dict:
        """
        Full pull pipeline:
        1. Authenticate
        2. Fetch raw data
        3. Transform
        4. Upsert ad_sets → ads → snapshots via Laravel API
        5. Report success/failure
        """
        try:
            self.authenticate(credentials)

            raw_data = self.fetch_campaign_data(
                account_id, external_campaign_id, date_from, date_to
            )

            if not raw_data:
                logger.info(f"No data returned for campaign {external_campaign_id}")
                return {"status": "empty", "count": 0}

            ad_sets, ads, snapshots = self.transform_to_snapshots(
                raw_data, campaign_platform_id
            )

            # 1. Upsert ad sets
            ad_set_id_map = {}
            for ad_set_data in ad_sets:
                result = self.laravel.upsert_ad_set(ad_set_data)
                ad_set_id_map[ad_set_data["external_id"]] = result["id"]

            # 2. Upsert ads (replace ad_set external_id with internal id)
            ad_id_map = {}
            for ad_data in ads:
                ad_set_ext_id = ad_data.pop("_ad_set_external_id")
                ad_data["ad_set_id"] = ad_set_id_map[ad_set_ext_id]
                result = self.laravel.upsert_ad(ad_data)
                ad_id_map[ad_data["external_id"]] = result["id"]

            # 3. Post snapshots (replace ad external_id with internal id)
            for snap in snapshots:
                ad_ext_id = snap.pop("_ad_external_id")
                snap["ad_id"] = ad_id_map[ad_ext_id]

            if snapshots:
                self.laravel.post_snapshots(snapshots)

            return {"status": "ok", "count": len(snapshots)}

        except Exception as e:
            logger.error(f"Pull failed: {e}", exc_info=True)
            raise
```

---

## 7. Example Collector: Meta

**`collectors/meta_collector.py`:**

```python
from datetime import date
from collectors.base import BaseCollector
from utils.timezone import to_casablanca_date
import logging

logger = logging.getLogger(__name__)


class MetaCollector(BaseCollector):
    """
    Collects ad-level data from Meta (Facebook) Marketing API.
    Uses the facebook-business SDK.
    """

    def authenticate(self, credentials: dict) -> None:
        from facebook_business.api import FacebookAdsApi
        from facebook_business.adobjects.adaccount import AdAccount

        FacebookAdsApi.init(
            app_id=credentials.get("app_id"),
            app_secret=credentials.get("app_secret"),
            access_token=credentials.get("access_token"),
        )
        self.api = FacebookAdsApi.get_default_api()

    def fetch_campaign_data(
        self,
        account_id: str,
        external_campaign_id: str,
        date_from: date,
        date_to: date,
    ) -> list[dict]:
        from facebook_business.adobjects.adaccount import AdAccount

        account = AdAccount(f"act_{account_id}")

        params = {
            "level": "ad",
            "time_range": {
                "since": date_from.isoformat(),
                "until": date_to.isoformat(),
            },
            "time_increment": 1,  # Daily breakdown
            "filtering": [
                {
                    "field": "campaign.id",
                    "operator": "EQUAL",
                    "value": external_campaign_id,
                }
            ],
        }

        fields = [
            "campaign_id", "campaign_name",
            "adset_id", "adset_name",
            "ad_id", "ad_name",
            "impressions", "reach", "frequency",
            "clicks", "outbound_clicks",
            "spend", "cpm", "cpc", "ctr",
            "actions", "cost_per_action_type",
            "video_p100_watched_actions", "video_play_actions",
            "date_start",
        ]

        insights = account.get_insights(params=params, fields=fields)
        return [dict(insight) for insight in insights]

    def transform_to_snapshots(
        self,
        raw_data: list[dict],
        campaign_platform_id: int,
    ) -> tuple[list[dict], list[dict], list[dict]]:

        ad_sets_seen = {}
        ads_seen = {}
        snapshots = []

        for row in raw_data:
            adset_id = row.get("adset_id")
            ad_id = row.get("ad_id")
            snapshot_date = to_casablanca_date(row.get("date_start"))

            # Collect unique ad sets
            if adset_id and adset_id not in ad_sets_seen:
                ad_sets_seen[adset_id] = {
                    "campaign_platform_id": campaign_platform_id,
                    "external_id": adset_id,
                    "name": row.get("adset_name", "Unknown"),
                    "status": "active",
                }

            # Collect unique ads
            if ad_id and ad_id not in ads_seen:
                ads_seen[ad_id] = {
                    "_ad_set_external_id": adset_id,
                    "external_id": ad_id,
                    "name": row.get("ad_name", "Unknown"),
                    "status": "active",
                }

            # Extract metrics
            impressions = int(row.get("impressions", 0))
            reach = int(row.get("reach", 0))
            clicks = int(row.get("clicks", 0))
            spend = float(row.get("spend", 0))

            # Outbound clicks (Meta-specific: link clicks)
            outbound = row.get("outbound_clicks") or []
            link_clicks = sum(
                int(oc.get("value", 0))
                for oc in outbound
                if oc.get("action_type") == "outbound_click"
            )

            # Conversions from actions
            actions = row.get("actions") or []
            conversions = sum(
                int(a.get("value", 0))
                for a in actions
                if a.get("action_type") in ("lead", "offsite_conversion.fb_pixel_purchase", "complete_registration")
            )
            leads = sum(
                int(a.get("value", 0))
                for a in actions
                if a.get("action_type") == "lead"
            )

            # Video
            video_views_list = row.get("video_play_actions") or []
            video_views = sum(int(v.get("value", 0)) for v in video_views_list)
            video_completions_list = row.get("video_p100_watched_actions") or []
            video_completions = sum(int(v.get("value", 0)) for v in video_completions_list)

            # Build snapshot
            snapshots.append({
                "_ad_external_id": ad_id,
                "snapshot_date": snapshot_date,
                "granularity": "daily",
                "impressions": impressions,
                "reach": reach,
                "frequency": round(impressions / reach, 4) if reach > 0 else None,
                "clicks": clicks,
                "link_clicks": link_clicks or None,
                "landing_page_views": None,  # Requires separate query
                "ctr": round(clicks / impressions * 100, 4) if impressions > 0 else 0,
                "spend": round(spend, 2),
                "cpm": round(spend / impressions * 1000, 4) if impressions > 0 else 0,
                "cpc": round(spend / clicks, 4) if clicks > 0 else 0,
                "conversions": conversions or None,
                "cpa": round(spend / conversions, 4) if conversions > 0 else None,
                "leads": leads or None,
                "cpl": round(spend / leads, 4) if leads > 0 else None,
                "video_views": video_views or None,
                "video_completions": video_completions or None,
                "vtr": round(video_views / impressions * 100, 4) if impressions > 0 and video_views > 0 else None,
                "engagement": None,  # Requires separate query for reactions
                "engagement_rate": None,
                "thumb_stop_rate": None,  # Not a Meta metric
                "custom_metrics": None,
                "raw_response": row,
                "source": "api",
            })

        return list(ad_sets_seen.values()), list(ads_seen.values()), snapshots
```

---

## 8. Celery Configuration

**`tasks/celery_app.py`:**

```python
from celery import Celery
from celery.schedules import crontab
from config.settings import REDIS_URL

app = Celery("havas", broker=REDIS_URL, backend=REDIS_URL)

app.conf.update(
    task_serializer="json",
    accept_content=["json"],
    result_serializer="json",
    timezone="Africa/Casablanca",
    enable_utc=False,
    task_track_started=True,
    task_acks_late=True,
    worker_prefetch_multiplier=1,
)

# Schedule: pull data 4x/day for active campaigns
app.conf.beat_schedule = {
    "pull-all-platforms-morning": {
        "task": "tasks.pull_tasks.pull_all_active_campaigns",
        "schedule": crontab(hour="7", minute="0"),
    },
    "pull-all-platforms-midday": {
        "task": "tasks.pull_tasks.pull_all_active_campaigns",
        "schedule": crontab(hour="12", minute="0"),
    },
    "pull-all-platforms-evening": {
        "task": "tasks.pull_tasks.pull_all_active_campaigns",
        "schedule": crontab(hour="18", minute="0"),
    },
    "pull-all-platforms-night": {
        "task": "tasks.pull_tasks.pull_all_active_campaigns",
        "schedule": crontab(hour="23", minute="30"),
    },
}
```

---

## 9. Pull Tasks

**`tasks/pull_tasks.py`:**

```python
from datetime import date, timedelta
from tasks.celery_app import app
from api.laravel_client import LaravelClient
from db.reader import get_active_campaign_platforms
from collectors.meta_collector import MetaCollector
from collectors.google_collector import GoogleCollector
from collectors.tiktok_collector import TikTokCollector
import logging

logger = logging.getLogger(__name__)

# Map platform slugs to collector classes
COLLECTOR_MAP = {
    "meta": MetaCollector,
    "google": GoogleCollector,
    "tiktok": TikTokCollector,
    # "youtube": YouTubeCollector,  # Implement when needed
}


@app.task(name="tasks.pull_tasks.pull_all_active_campaigns")
def pull_all_active_campaigns():
    """
    Master task: query all active campaign_platforms and dispatch
    individual pull tasks per platform.
    """
    platforms = get_active_campaign_platforms()
    logger.info(f"Found {len(platforms)} active campaign platforms to pull")

    for cp in platforms:
        slug = cp["platform_slug"]
        if slug not in COLLECTOR_MAP:
            logger.debug(f"Skipping {slug} — no collector implemented (manual entry)")
            continue

        if not cp.get("external_campaign_id"):
            logger.warning(
                f"Campaign platform {cp['campaign_platform_id']} "
                f"({cp['campaign_name']}/{slug}) has no external_campaign_id — skipping"
            )
            continue

        # Dispatch individual pull task
        pull_single_campaign_platform.delay(
            campaign_platform_id=cp["campaign_platform_id"],
            platform_slug=slug,
            account_id=cp["account_id"],
            external_campaign_id=cp["external_campaign_id"],
            connection_id=cp["connection_id"],
        )


@app.task(
    name="tasks.pull_tasks.pull_single_campaign_platform",
    bind=True,
    max_retries=2,
    default_retry_delay=60,
)
def pull_single_campaign_platform(
    self,
    campaign_platform_id: int,
    platform_slug: str,
    account_id: str,
    external_campaign_id: str,
    connection_id: int | None,
):
    """Pull ad-level data for a single campaign platform."""
    laravel = LaravelClient()

    try:
        collector_class = COLLECTOR_MAP[platform_slug]
        collector = collector_class(laravel)

        # TODO: Get decrypted credentials from Laravel
        # For now, this needs the credential decryption endpoint
        credentials = {}  # Placeholder — implement credential fetch

        # Pull last 3 days (handles timezone lag and data delays)
        date_to = date.today()
        date_from = date_to - timedelta(days=3)

        result = collector.pull(
            credentials=credentials,
            account_id=account_id,
            external_campaign_id=external_campaign_id,
            campaign_platform_id=campaign_platform_id,
            date_from=date_from,
            date_to=date_to,
        )

        # Report success to Laravel
        if connection_id:
            laravel.update_connection_status(connection_id, success=True)

        logger.info(
            f"Pull OK: {platform_slug} campaign {external_campaign_id} "
            f"— {result.get('count', 0)} snapshots"
        )

    except Exception as e:
        logger.error(f"Pull FAILED: {platform_slug} campaign {external_campaign_id}: {e}")

        # Report failure to Laravel (triggers circuit breaker)
        if connection_id:
            laravel.update_connection_status(
                connection_id, success=False, error_msg=str(e)[:500]
            )

        # Retry
        raise self.retry(exc=e)

    finally:
        laravel.close()
```

---

## 10. Timezone Utility

**`utils/timezone.py`:**

```python
from datetime import date, datetime
from zoneinfo import ZoneInfo

CASABLANCA = ZoneInfo("Africa/Casablanca")


def to_casablanca_date(date_str: str | date | datetime) -> str:
    """
    Convert a date string (from API, usually UTC) to Africa/Casablanca date.
    Returns ISO format string (YYYY-MM-DD).
    """
    if isinstance(date_str, date) and not isinstance(date_str, datetime):
        return date_str.isoformat()

    if isinstance(date_str, str):
        # Handle common formats
        for fmt in ("%Y-%m-%d", "%Y-%m-%dT%H:%M:%S", "%Y-%m-%dT%H:%M:%SZ", "%Y-%m-%dT%H:%M:%S%z"):
            try:
                dt = datetime.strptime(date_str, fmt)
                break
            except ValueError:
                continue
        else:
            return date_str[:10]  # Fallback: take first 10 chars
    else:
        dt = date_str

    # Convert to Casablanca timezone and extract date
    if dt.tzinfo is None:
        dt = dt.replace(tzinfo=ZoneInfo("UTC"))

    return dt.astimezone(CASABLANCA).date().isoformat()
```

---

## 11. AI Brief Analyzer (Claude API)

**`ai/brief_analyzer.py`:**

```python
import anthropic
from config.settings import ANTHROPIC_API_KEY
from db.reader import get_category_benchmarks
import json
import logging

logger = logging.getLogger(__name__)

client = anthropic.Anthropic(api_key=ANTHROPIC_API_KEY)


def analyze_brief(
    brief_raw: str,
    category_name: str,
    category_id: int,
    platform_ids: list[int],
    budget: float,
    currency: str = "MAD",
) -> dict:
    """
    Send brief to Claude for analysis.
    Returns structured JSON matching the briefs table AI columns.
    """
    # Gather benchmarks for context
    benchmarks_by_platform = {}
    for pid in platform_ids:
        benchmarks = get_category_benchmarks(category_id, pid)
        if benchmarks:
            benchmarks_by_platform[str(pid)] = benchmarks

    prompt = f"""You are a senior media buying strategist at Havas Morocco.
Analyze this client brief and provide a structured assessment.

## Client Brief
{brief_raw}

## Context
- Industry vertical: {category_name}
- Total budget: {budget:,.2f} {currency}
- Available benchmarks by platform: {json.dumps(benchmarks_by_platform, default=str)}

## Required Output (JSON only, no markdown)
Return ONLY a JSON object with these keys:
{{
  "brief_quality_score": <int 1-10>,
  "missing_information": [<list of missing fields>],
  "kpi_challenges": [<list of KPIs that seem unrealistic vs benchmarks>],
  "questions_for_client": [<list of clarifying questions>],
  "channel_rationale": "<paragraph explaining recommended channels>",
  "budget_split": {{
    "<platform_slug>": {{
      "pct": <int>,
      "amount": <float>,
      "rationale": "<why>"
    }}
  }},
  "media_plan_draft": {{
    "phases": [
      {{
        "name": "<phase name>",
        "duration_days": <int>,
        "platforms": [<slugs>],
        "formats": [<ad formats>],
        "kpis": [<metric names>]
      }}
    ]
  }}
}}
"""

    response = client.messages.create(
        model="claude-sonnet-4-20250514",
        max_tokens=4000,
        messages=[{"role": "user", "content": prompt}],
    )

    text = response.content[0].text

    # Parse JSON (handle potential markdown fencing)
    cleaned = text.strip()
    if cleaned.startswith("```"):
        cleaned = cleaned.split("\n", 1)[1]
        cleaned = cleaned.rsplit("```", 1)[0]

    try:
        return json.loads(cleaned)
    except json.JSONDecodeError:
        logger.error(f"Failed to parse Claude response as JSON: {text[:200]}")
        return {"error": "Failed to parse AI response", "raw": text[:500]}
```

---

## 12. Running the Collectors

### Development:

```bash
# Terminal 1: Celery worker
cd havas-collectors
celery -A tasks.celery_app worker --loglevel=info --concurrency=4

# Terminal 2: Celery beat (scheduler)
celery -A tasks.celery_app beat --loglevel=info

# Terminal 3: Manual trigger (testing)
python -c "from tasks.pull_tasks import pull_all_active_campaigns; pull_all_active_campaigns()"
```

### Production (systemd):

Create `/etc/systemd/system/havas-worker.service` and `/etc/systemd/system/havas-beat.service` with appropriate configurations.

---

## Credential Decryption Note

Laravel encrypts `platform_connections` credentials using the `APP_KEY`. Python cannot decrypt these directly without reimplementing Laravel's encryption (AES-256-CBC with HMAC).

**Recommended solution:** Add a Laravel endpoint that returns decrypted credentials for a given connection ID, authenticated via the internal API token:

```php
// In SnapshotController or a dedicated CredentialController
Route::get('/platform-connections/{id}/credentials', function (int $id) {
    $conn = PlatformConnection::findOrFail($id);
    return response()->json([
        'account_id'        => $conn->account_id,
        'auth_type'         => $conn->auth_type,
        'access_token'      => $conn->access_token,       // Auto-decrypted by trait
        'refresh_token'     => $conn->refresh_token,
        'api_key'           => $conn->api_key,
        'extra_credentials' => $conn->extra_credentials,
    ]);
})->middleware('internal.auth');
```

This keeps decryption in Laravel where the key lives, and Python just fetches the plaintext values over the secured internal API.

---

## Next Steps

- Implement `GoogleCollector` and `TikTokCollector` following the same pattern as `MetaCollector`
- Build the `report_commentator.py` AI module for generating report commentary
- Create Laravel controllers for the user-facing CRUD operations
- Build the frontend (Blade, Livewire, or separate SPA)
