from __future__ import annotations

from datetime import date
from typing import Any

import pytest

from havas_collectors.collectors.tiktok_collector import TikTokCollector


class _NoopLaravelClient:
    pass


def test_tiktok_collector_requires_access_token() -> None:
    collector = TikTokCollector(laravel_client=_NoopLaravelClient())

    with pytest.raises(ValueError, match="access_token"):
        collector.authenticate({})

    collector.close()


def test_tiktok_collector_raises_on_api_error_in_200_response(monkeypatch: pytest.MonkeyPatch) -> None:
    collector = TikTokCollector(laravel_client=_NoopLaravelClient())
    collector.authenticate({"access_token": "secret"})

    monkeypatch.setattr(
        collector,
        "request_json",
        lambda *args, **kwargs: {"code": 40100, "message": "invalid access token", "request_id": "req-1"},
    )

    with pytest.raises(RuntimeError, match="TikTok API error code=40100"):
        collector.fetch_ad_level_data(
            account_id="acc-1",
            external_campaign_id="cmp-1",
            date_from=date(2026, 3, 1),
            date_to=date(2026, 3, 2),
        )

    collector.close()


def test_tiktok_collector_paginates_and_builds_request_body(monkeypatch: pytest.MonkeyPatch) -> None:
    collector = TikTokCollector(laravel_client=_NoopLaravelClient())
    collector.authenticate({"access_token": "secret"})
    observed_bodies: list[dict[str, Any]] = []
    responses = iter(
        [
            {
                "code": 0,
                "data": {
                    "list": [{"id": 1}],
                    "page_info": {"page": 1, "total_page": 2, "has_next_page": True},
                },
            },
            {
                "code": 0,
                "data": {
                    "list": [{"id": 2}],
                    "page_info": {"page": 2, "total_page": 2, "has_next_page": False},
                },
            },
        ]
    )

    def fake_request_json(
        method: str,
        url: str,
        *,
        headers: dict[str, str] | None = None,
        params: dict[str, Any] | None = None,
        json_body: dict[str, Any] | None = None,
    ) -> dict[str, Any]:
        observed_bodies.append(json_body or {})
        return next(responses)

    monkeypatch.setattr(collector, "request_json", fake_request_json)

    rows = collector.fetch_ad_level_data(
        account_id="acc-1",
        external_campaign_id="cmp-1",
        date_from=date(2026, 3, 1),
        date_to=date(2026, 3, 2),
    )

    assert rows == [{"id": 1}, {"id": 2}]
    assert observed_bodies[0]["page"] == 1
    assert observed_bodies[1]["page"] == 2
    assert observed_bodies[0]["filtering"][0]["filter_value"] == "cmp-1"

    collector.close()


def test_tiktok_collector_normalizes_partial_payload_safely() -> None:
    collector = TikTokCollector(laravel_client=_NoopLaravelClient())

    normalized = collector.normalize_record(
        {
            "dimensions": None,
            "metrics": None,
            "ad_name": None,
        }
    )

    assert normalized.ad_set_external_id == ""
    assert normalized.ad_external_id == ""
    assert normalized.impressions == 0
    assert normalized.reach == 0
    assert normalized.engagement == 0

    collector.close()