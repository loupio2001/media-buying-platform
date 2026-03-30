from __future__ import annotations

import logging
from abc import ABC, abstractmethod
from datetime import date
from typing import Any

import httpx
from tenacity import retry, retry_if_exception, stop_after_attempt, wait_exponential

from havas_collectors.api.laravel_client import LaravelInternalClient
from havas_collectors.collectors.schemas import (
    AdSetUpsertPayload,
    AdUpsertPayload,
    NormalizedAdRecord,
    SnapshotPayload,
)
from havas_collectors.utils.timezone import normalize_date_range, to_casablanca_date

LOGGER = logging.getLogger(__name__)

VALID_STATUSES = {"active", "paused", "deleted", "archived"}


def _safe_ratio(numerator: float, denominator: float, factor: float = 1.0) -> float | None:
    if denominator <= 0:
        return None
    return round((numerator / denominator) * factor, 6)


def _is_retryable_error(error: BaseException) -> bool:
    if isinstance(error, httpx.HTTPStatusError):
        status_code = error.response.status_code
        return status_code == 429 or 500 <= status_code < 600

    return isinstance(
        error,
        (httpx.TimeoutException, httpx.NetworkError, httpx.RemoteProtocolError),
    )


class BaseCollector(ABC):
    """Base executable flow for platform collectors."""

    def __init__(
        self,
        laravel_client: LaravelInternalClient,
        *,
        timeout_seconds: float = 30.0,
        snapshot_batch_size: int = 250,
    ) -> None:
        self.laravel_client = laravel_client
        self.timeout_seconds = timeout_seconds
        self.snapshot_batch_size = snapshot_batch_size
        self._http_client = httpx.Client(timeout=httpx.Timeout(timeout_seconds))

    @property
    @abstractmethod
    def platform_name(self) -> str:
        raise NotImplementedError

    @abstractmethod
    def authenticate(self, credentials: dict[str, Any]) -> None:
        raise NotImplementedError

    @abstractmethod
    def fetch_ad_level_data(
        self,
        account_id: str,
        external_campaign_id: str,
        date_from: date,
        date_to: date,
    ) -> list[dict[str, Any]]:
        raise NotImplementedError

    @abstractmethod
    def normalize_record(self, raw_row: dict[str, Any]) -> NormalizedAdRecord:
        raise NotImplementedError

    @retry(
        reraise=True,
        stop=stop_after_attempt(3),
        wait=wait_exponential(min=1, max=10),
        retry=retry_if_exception(_is_retryable_error),
    )
    def request_json(
        self,
        method: str,
        url: str,
        *,
        headers: dict[str, str] | None = None,
        params: dict[str, Any] | None = None,
        json_body: dict[str, Any] | None = None,
    ) -> dict[str, Any] | list[Any]:
        response = self._http_client.request(
            method=method,
            url=url,
            headers=headers,
            params=params,
            json=json_body,
        )
        if response.status_code >= 400:
            LOGGER.warning(
                "Platform API error platform=%s method=%s status=%s url=%s",
                self.platform_name,
                method,
                response.status_code,
                url,
            )
            response.raise_for_status()
        return response.json()

    def collect(
        self,
        *,
        credentials: dict[str, Any],
        account_id: str,
        external_campaign_id: str,
        date_from: date | str,
        date_to: date | str,
        campaign_platform_id: int,
    ) -> dict[str, int]:
        start_date, end_date = normalize_date_range(date_from, date_to)
        LOGGER.info(
            "Collector start platform=%s account_id=%s external_campaign_id=%s from=%s to=%s",
            self.platform_name,
            account_id,
            external_campaign_id,
            start_date,
            end_date,
        )

        self.authenticate(credentials)
        raw_rows = self.fetch_ad_level_data(account_id, external_campaign_id, start_date, end_date)

        if not raw_rows:
            LOGGER.info(
                "Collector finished with no rows platform=%s campaign_platform_id=%s",
                self.platform_name,
                campaign_platform_id,
            )
            return {"ad_sets": 0, "ads": 0, "snapshots": 0}

        snapshot_payloads: list[dict[str, Any]] = []
        ad_set_count = 0
        ad_count = 0

        for raw_row in raw_rows:
            try:
                normalized = self.normalize_record(raw_row)
                if not normalized.ad_set_external_id or not normalized.ad_external_id:
                    LOGGER.warning(
                        "Skipping row with missing ids platform=%s campaign_platform_id=%s",
                        self.platform_name,
                        campaign_platform_id,
                    )
                    continue

                ad_set_id = self.laravel_client.upsert_ad_set(
                    self._build_ad_set_payload(campaign_platform_id, normalized)
                )
                ad_set_count += 1

                ad_id = self.laravel_client.upsert_ad(self._build_ad_payload(ad_set_id, normalized))
                ad_count += 1

                snapshot_payloads.append(self._build_snapshot_payload(ad_id, normalized))
            except Exception:
                LOGGER.exception(
                    "Failed to normalize/upsert row platform=%s campaign_platform_id=%s",
                    self.platform_name,
                    campaign_platform_id,
                )

        inserted_ids: list[int] = []
        if snapshot_payloads:
            for index in range(0, len(snapshot_payloads), self.snapshot_batch_size):
                chunk = snapshot_payloads[index : index + self.snapshot_batch_size]
                inserted_ids.extend(self.laravel_client.post_snapshots_batch(chunk))

        LOGGER.info(
            "Collector success platform=%s campaign_platform_id=%s ad_sets=%s ads=%s snapshots=%s",
            self.platform_name,
            campaign_platform_id,
            ad_set_count,
            ad_count,
            len(inserted_ids),
        )
        return {
            "ad_sets": ad_set_count,
            "ads": ad_count,
            "snapshots": len(inserted_ids),
        }

    def _build_ad_set_payload(
        self,
        campaign_platform_id: int,
        record: NormalizedAdRecord,
    ) -> dict[str, Any]:
        payload = AdSetUpsertPayload(
            campaign_platform_id=campaign_platform_id,
            external_id=record.ad_set_external_id,
            name=record.ad_set_name,
            status=self._normalize_status(record.ad_set_status),
            objective=record.objective,
            budget=record.budget,
            budget_type=record.budget_type,
            is_tracked=True,
        )
        return payload.model_dump(exclude_none=True, mode="json")

    def _build_ad_payload(self, ad_set_id: int, record: NormalizedAdRecord) -> dict[str, Any]:
        payload = AdUpsertPayload(
            ad_set_id=ad_set_id,
            external_id=record.ad_external_id,
            name=record.ad_name,
            format=record.format,
            status=self._normalize_status(record.ad_status),
            headline=record.headline,
            body=record.body,
            cta=record.cta,
            destination_url=record.destination_url,
            creative_url=record.creative_url,
            is_tracked=True,
        )
        return payload.model_dump(exclude_none=True, mode="json")

    def _build_snapshot_payload(self, ad_id: int, record: NormalizedAdRecord) -> dict[str, Any]:
        impressions = int(record.impressions)
        clicks = int(record.clicks)
        reach = int(record.reach)
        spend = float(record.spend)
        conversions = int(record.conversions)
        leads = int(record.leads)
        video_views = int(record.video_views)
        engagement = int(record.engagement)

        payload = SnapshotPayload(
            ad_id=ad_id,
            snapshot_date=to_casablanca_date(record.snapshot_date),
            granularity="daily",
            impressions=impressions,
            reach=reach,
            frequency=_safe_ratio(float(impressions), float(reach)),
            clicks=clicks,
            link_clicks=int(record.link_clicks),
            landing_page_views=int(record.landing_page_views),
            ctr=_safe_ratio(float(clicks), float(impressions), 100.0),
            spend=spend,
            cpm=_safe_ratio(spend, float(impressions), 1000.0),
            cpc=_safe_ratio(spend, float(clicks)),
            conversions=conversions,
            cpa=_safe_ratio(spend, float(conversions)),
            leads=leads,
            cpl=_safe_ratio(spend, float(leads)),
            video_views=video_views,
            video_completions=int(record.video_completions),
            vtr=_safe_ratio(float(video_views), float(impressions), 100.0),
            engagement=engagement,
            engagement_rate=_safe_ratio(float(engagement), float(impressions), 100.0),
            thumb_stop_rate=record.thumb_stop_rate,
            custom_metrics=record.custom_metrics,
            raw_response=record.raw_response,
            source="api",
        )
        return payload.model_dump(exclude_none=True, mode="json")

    def close(self) -> None:
        self._http_client.close()

    def _normalize_status(self, value: str | None) -> str | None:
        if not value:
            return None

        candidate = value.strip().lower()
        aliases = {
            "enabled": "active",
            "running": "active",
            "on": "active",
            "off": "paused",
            "stopped": "paused",
            "removed": "deleted",
            "disable": "paused",
            "disabled": "paused",
        }
        normalized = aliases.get(candidate, candidate)
        return normalized if normalized in VALID_STATUSES else None
