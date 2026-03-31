from __future__ import annotations

import logging
import os
from typing import Any

from havas_collectors.ai.brief_analyzer import BriefAnalyzer
from havas_collectors.ai.report_commentator import CommentaryRequest, ReportCommentator
from havas_collectors.api.laravel_client import LaravelInternalClient
from havas_collectors.db.reader import get_category_benchmarks
from havas_collectors.tasks.celery_app import app

LOGGER = logging.getLogger(__name__)


def _build_laravel_client() -> LaravelInternalClient:
    base_url = os.getenv("LARAVEL_API_URL", "http://127.0.0.1:8000/api/internal/v1")
    internal_token = os.getenv("INTERNAL_API_TOKEN", "")
    if not internal_token:
        raise ValueError("INTERNAL_API_TOKEN must be configured")
    return LaravelInternalClient(base_url=base_url, internal_token=internal_token)


@app.task(
    bind=True,
    name="havas_collectors.tasks.ai_tasks.analyze_brief_task",
    max_retries=2,
    default_retry_delay=60,
)
def analyze_brief_task(self: Any, brief_id: int) -> dict[str, Any]:
    """Fetch brief data, run AI analysis, and POST results back to Laravel.

    Args:
        brief_id: Primary key of the brief record in Laravel.
    """
    laravel_client = _build_laravel_client()

    try:
        brief_data: dict[str, Any] = laravel_client.get_brief(brief_id)

        # Collect benchmarks for all relevant platforms
        category_id: int | None = brief_data.get("category_id")
        platform_ids: list[int] = brief_data.get("platform_ids") or []

        benchmarks: list[dict[str, Any]] = []
        if category_id:
            for platform_id in platform_ids:
                benchmarks.extend(get_category_benchmarks(category_id, platform_id))

        analyzer = BriefAnalyzer()
        analysis = analyzer.analyze_brief(brief_data, benchmarks)

        result = laravel_client.post_brief_ai_analysis(brief_id, analysis)

        LOGGER.info("Brief analysis completed brief_id=%s score=%s", brief_id, analysis.get("brief_quality_score"))
        return result

    except Exception as error:
        LOGGER.exception("Brief analysis failed brief_id=%s", brief_id)
        raise self.retry(exc=error)
    finally:
        laravel_client.close()


@app.task(
    bind=True,
    name="havas_collectors.tasks.ai_tasks.generate_report_commentary_task",
    max_retries=2,
    default_retry_delay=60,
)
def generate_report_commentary_task(self: Any, report_id: int) -> dict[str, Any]:
    """Fetch report data, run AI commentary generation, and POST results back to Laravel.

    Args:
        report_id: Primary key of the report record in Laravel.
    """
    laravel_client = _build_laravel_client()

    try:
        report_data: dict[str, Any] = laravel_client.get_report(report_id)

        metrics: dict[str, Any] = report_data.get("metrics") or {}
        campaign_context: dict[str, Any] = report_data.get("campaign_context") or {}
        period: str = str(report_data.get("period") or "")
        language: str = str(report_data.get("language") or "fr")

        commentator = ReportCommentator()
        commentary = commentator.generate_commentary(
            CommentaryRequest(
                metrics=metrics,
                campaign_context=campaign_context,
                period=period,
                language=language,  # type: ignore[arg-type]
            )
        )

        payload = commentary.model_dump()
        result = laravel_client.post_report_commentary(report_id, payload)

        LOGGER.info(
            "Report commentary generated report_id=%s confidence=%s",
            report_id,
            commentary.confidence,
        )
        return result

    except Exception as error:
        LOGGER.exception("Report commentary failed report_id=%s", report_id)
        raise self.retry(exc=error)
    finally:
        laravel_client.close()
