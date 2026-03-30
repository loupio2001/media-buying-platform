# Havas Python Collectors Scaffold

This folder contains an executable Python scaffold for platform collectors:

- Google Ads collector
- TikTok collector
- Laravel internal API client for ad/ad_set/snapshot upserts

## Requirements

- Python 3.12+
- Internal API token configured in environment

## Setup

```bash
cd havas_collectors
python -m venv .venv
.venv\\Scripts\\activate
pip install -r requirements.txt
```

## Environment variables

```env
LARAVEL_API_URL=http://127.0.0.1:8000/api/internal/v1
INTERNAL_API_TOKEN=your-internal-token

GOOGLE_ADS_API_URL=https://googleads.googleapis.com/v17/customers/{account_id}/googleAds:searchStream
TIKTOK_API_URL=https://business-api.tiktok.com/open_api/v1.3/report/integrated/get/
ANTHROPIC_API_KEY=your-anthropic-api-key
```

## Quick import check

```bash
python -c "from havas_collectors.collectors import GoogleAdsCollector, TikTokCollector; print('ok')"
```

## Minimal usage

```python
from havas_collectors.api.laravel_client import LaravelInternalClient
from havas_collectors.collectors.google_collector import GoogleAdsCollector

laravel = LaravelInternalClient(
    base_url="http://127.0.0.1:8000/api/internal/v1",
    internal_token="your-internal-token",
)

collector = GoogleAdsCollector(laravel)
summary = collector.collect(
    credentials={"access_token": "...", "developer_token": "..."},
    account_id="1234567890",
    external_campaign_id="987654321",
    date_from="2026-03-01",
    date_to="2026-03-30",
    campaign_platform_id=1,
)
print(summary)
collector.close()
laravel.close()
```

Persist AI comments for a report platform section:

```python
laravel.update_report_platform_section_ai_comments(
    report_platform_section_id=12,
    payload={
        "ai_summary": "Meta depasse l'objectif CTR, mais la portee reste contrainte.",
        "ai_highlights": ["CTR +18%", "CPC sous benchmark"],
        "ai_concerns": ["Reach faible sur la semaine 2"],
        "ai_suggested_action": "Reallouer 15% du budget vers les ensembles les plus efficaces.",
        "performance_flags": ["strong_ctr", "low_reach"],
        "top_performing_ads": ["Ad A", "Ad B"],
        "worst_performing_ads": ["Ad Z"],
        "human_notes": None,
    },
)
```

## AI report commentary

```python
from havas_collectors.ai import CommentaryRequest, ReportCommentator

payload = CommentaryRequest(
    metrics={
        "impressions": 125000,
        "clicks": 2200,
        "spend": 1850.0,
        "conversions": 74,
        "leads": 31,
    },
    campaign_context={
        "campaign_name": "Spring Lead Gen",
        "platform": "meta",
        "target_ctr": 1.8,
        "target_cpa": 30.0,
    },
    period="2026-03-01 to 2026-03-30",
    language="fr",
    tone="analytical",
)

commentator = ReportCommentator()
result = commentator.generate_commentary(payload)

structured_result = result.model_dump()
```

Notes:
- If Anthropic SDK or key is unavailable, the module returns a validated local fallback commentary.
- Output schema is always structured: summary, highlights, risks, recommendations, confidence.

Generate and persist AI commentary for a report platform section:

```bash
python -m havas_collectors.ai.report_platform_section_commentary 12
```

Expected internal API contract for the context fetch:

```json
{
    "data": {
        "metrics": {
            "impressions": 125000,
            "clicks": 2200,
            "spend": 1850.0,
            "conversions": 74,
            "leads": 31
        },
        "campaign_context": {
            "campaign_name": "Spring Lead Gen",
            "platform": "meta",
            "target_ctr": 1.8,
            "target_cpa": 30.0
        },
        "period": "2026-03-01 to 2026-03-30",
        "language": "fr",
        "tone": "analytical"
    }
}
```

The CLI also accepts period_start and period_end if period is not returned by the API.

## Tests

Install development dependencies:

```bash
cd havas_collectors
pip install -r requirements-dev.txt
```

Run all unit tests:

```bash
python -m pytest tests/unit -q
```

Run only mandatory risk-focused tests:

```bash
python -m pytest tests/unit/test_timezone_utils.py tests/unit/test_base_collector_payload_math.py tests/unit/test_laravel_client_retryable.py tests/unit/test_report_commentator_fallback.py -q
```

Run the AI commentary flow tests:

```bash
python -m pytest tests/unit/test_laravel_client_retryable.py tests/unit/test_report_platform_section_commentary.py tests/unit/test_report_commentator_fallback.py -q
```
