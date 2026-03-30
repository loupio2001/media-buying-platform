from __future__ import annotations

from datetime import date
from typing import Any

from havas_collectors.collectors.base_collector import BaseCollector
from havas_collectors.collectors.schemas import NormalizedAdRecord


class _DummyCollector(BaseCollector):
    @property
    def platform_name(self) -> str:
        return "dummy"

    def authenticate(self, credentials: dict[str, Any]) -> None:
        return None

    def fetch_ad_level_data(
        self,
        account_id: str,
        external_campaign_id: str,
        date_from: date,
        date_to: date,
    ) -> list[dict[str, Any]]:
        return []

    def normalize_record(self, raw_row: dict[str, Any]) -> NormalizedAdRecord:
        return NormalizedAdRecord(
            snapshot_date=date.today(),
            ad_set_external_id="aset",
            ad_set_name="Ad Set",
            ad_external_id="ad",
            ad_name="Ad",
        )


def _make_record(**overrides: Any) -> NormalizedAdRecord:
    defaults: dict[str, Any] = {
        "snapshot_date": date(2026, 3, 30),
        "ad_set_external_id": "aset-1",
        "ad_set_name": "Ad Set 1",
        "ad_external_id": "ad-1",
        "ad_name": "Ad 1",
        "impressions": 1000,
        "reach": 200,
        "clicks": 25,
        "link_clicks": 15,
        "landing_page_views": 10,
        "spend": 123.45,
        "conversions": 5,
        "leads": 3,
        "video_views": 120,
        "video_completions": 60,
        "engagement": 80,
    }
    defaults.update(overrides)
    return NormalizedAdRecord(**defaults)


def test_build_snapshot_payload_recomputes_all_ratio_metrics() -> None:
    collector = _DummyCollector(laravel_client=object())
    record = _make_record()

    payload = collector._build_snapshot_payload(ad_id=42, record=record)

    assert payload["frequency"] == 5.0
    assert payload["ctr"] == 2.5
    assert payload["cpm"] == 123.45
    assert payload["cpc"] == 4.938
    assert payload["cpa"] == 24.69
    assert payload["cpl"] == 41.15
    assert payload["vtr"] == 12.0
    assert payload["engagement_rate"] == 8.0


def test_build_snapshot_payload_sets_ratios_to_none_when_denominator_is_zero() -> None:
    collector = _DummyCollector(laravel_client=object())
    record = _make_record(
        impressions=0,
        reach=0,
        clicks=0,
        conversions=0,
        leads=0,
        spend=100.0,
        video_views=0,
        engagement=0,
    )

    payload = collector._build_snapshot_payload(ad_id=42, record=record)

    assert "frequency" not in payload
    assert "ctr" not in payload
    assert "cpm" not in payload
    assert "cpc" not in payload
    assert "cpa" not in payload
    assert "cpl" not in payload
    assert "vtr" not in payload
    assert "engagement_rate" not in payload
