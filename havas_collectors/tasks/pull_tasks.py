from __future__ import annotations

import logging
import os
from datetime import date, timedelta
from typing import Any

from havas_collectors.api.laravel_client import LaravelInternalClient
from havas_collectors.collectors import GoogleAdsCollector, MetaCollector, TikTokCollector
from havas_collectors.db.reader import get_active_campaign_platforms
from havas_collectors.tasks.celery_app import app

LOGGER = logging.getLogger(__name__)

COLLECTOR_MAP = {
    "google": GoogleAdsCollector,
    "meta": MetaCollector,
    "tiktok": TikTokCollector,
}


def _build_laravel_client() -> LaravelInternalClient:
    base_url = os.getenv("LARAVEL_API_URL", "http://127.0.0.1:8000/api/internal/v1")
    internal_token = os.getenv("INTERNAL_API_TOKEN", "")
    if not internal_token:
        raise ValueError("INTERNAL_API_TOKEN must be configured")

    return LaravelInternalClient(base_url=base_url, internal_token=internal_token)


def _merge_credentials(credentials: dict[str, Any]) -> dict[str, Any]:
    extra_credentials = credentials.get("extra_credentials") or {}
    if not isinstance(extra_credentials, dict):
        extra_credentials = {}

    return {**extra_credentials, **credentials, "extra_credentials": extra_credentials}


@app.task(name="havas_collectors.tasks.pull_tasks.pull_all_active_campaigns")
def pull_all_active_campaigns() -> dict[str, int]:
    campaign_platforms = get_active_campaign_platforms()
    dispatched = 0
    skipped = 0

    for campaign_platform in campaign_platforms:
        platform_slug = str(campaign_platform.get("platform_slug") or "")
        if platform_slug not in COLLECTOR_MAP:
            skipped += 1
            LOGGER.info(
                "Skipping campaign_platform_id=%s unsupported platform=%s",
                campaign_platform.get("campaign_platform_id"),
                platform_slug,
            )
            continue

        pull_single_campaign_platform.delay(
            campaign_platform_id=int(campaign_platform["campaign_platform_id"]),
            platform_slug=platform_slug,
            external_campaign_id=str(campaign_platform["external_campaign_id"]),
            connection_id=int(campaign_platform["connection_id"])
            if campaign_platform.get("connection_id") is not None
            else None,
            account_id=str(campaign_platform.get("account_id") or ""),
        )
        dispatched += 1

    return {"dispatched": dispatched, "skipped": skipped}


@app.task(
    bind=True,
    name="havas_collectors.tasks.pull_tasks.pull_single_campaign_platform",
    max_retries=2,
    default_retry_delay=60,
)
def pull_single_campaign_platform(
    self: Any,
    *,
    campaign_platform_id: int,
    platform_slug: str,
    external_campaign_id: str,
    connection_id: int | None,
    account_id: str,
) -> dict[str, int]:
    laravel_client = _build_laravel_client()
    collector = None

    try:
        collector_class = COLLECTOR_MAP[platform_slug]
        collector = collector_class(laravel_client=laravel_client)

        credentials: dict[str, Any] = {}
        resolved_account_id = account_id
        if connection_id is not None:
            credentials = _merge_credentials(laravel_client.get_connection_credentials(connection_id))
            resolved_account_id = str(credentials.get("account_id") or account_id)

        if not resolved_account_id:
            raise ValueError(
                f"No account_id available for campaign_platform_id={campaign_platform_id}"
            )

        date_to = date.today()
        date_from = date_to - timedelta(days=3)

        summary = collector.collect(
            credentials=credentials,
            account_id=resolved_account_id,
            external_campaign_id=external_campaign_id,
            date_from=date_from,
            date_to=date_to,
            campaign_platform_id=campaign_platform_id,
        )

        if summary["failed_rows"] > 0:
            raise RuntimeError(
                f"Collector had failed rows for campaign_platform_id={campaign_platform_id}"
            )

        if summary["snapshots"] <= 0 and summary["processed_rows"] > 0:
            raise RuntimeError(
                f"Collector produced rows but no snapshots for campaign_platform_id={campaign_platform_id}"
            )

        if connection_id is not None:
            laravel_client.update_connection_sync_status(connection_id, success=True)

        LOGGER.info(
            "Scheduled pull completed campaign_platform_id=%s platform=%s snapshots=%s",
            campaign_platform_id,
            platform_slug,
            summary["snapshots"],
        )
        return summary
    except Exception as error:
        LOGGER.exception(
            "Scheduled pull failed campaign_platform_id=%s platform=%s",
            campaign_platform_id,
            platform_slug,
        )
        if connection_id is not None:
            laravel_client.update_connection_sync_status(
                connection_id,
                success=False,
                error_msg=str(error)[:500],
            )
        raise self.retry(exc=error)
    finally:
        if collector is not None:
            collector.close()
        laravel_client.close()