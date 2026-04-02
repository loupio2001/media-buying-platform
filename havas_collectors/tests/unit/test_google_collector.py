from __future__ import annotations

from datetime import date
from typing import Any

import pytest

from havas_collectors.collectors.google_collector import GoogleAdsCollector


class _NoopLaravelClient:
    pass


def test_google_collector_requires_access_token_and_developer_token() -> None:
    collector = GoogleAdsCollector(laravel_client=_NoopLaravelClient())

    with pytest.raises(ValueError, match="access_token and developer_token"):
        collector.authenticate({"extra_credentials": None})

    collector.close()


def test_google_collector_builds_sanitized_headers_and_query(monkeypatch: pytest.MonkeyPatch) -> None:
    collector = GoogleAdsCollector(laravel_client=_NoopLaravelClient())
    collector.authenticate(
        {
            "access_token": "token",
            "developer_token": "dev-token",
            "login_customer_id": "111-222-3333",
        }
    )
    observed: dict[str, Any] = {}

    def fake_request_json(
        method: str,
        url: str,
        *,
        headers: dict[str, str] | None = None,
        params: dict[str, Any] | None = None,
        json_body: dict[str, Any] | None = None,
    ) -> list[dict[str, Any]]:
        observed["method"] = method
        observed["url"] = url
        observed["headers"] = headers
        observed["params"] = params
        observed["json_body"] = json_body
        return [{"results": []}]

    monkeypatch.setattr(collector, "request_json", fake_request_json)

    rows = collector.fetch_ad_level_data(
        account_id="123-456-7890",
        external_campaign_id="987-654-321",
        date_from=date(2026, 3, 1),
        date_to=date(2026, 3, 3),
    )

    assert rows == []
    assert observed["method"] == "POST"
    assert observed["url"] == "https://googleads.googleapis.com/v23/customers/1234567890/googleAds:searchStream"
    assert observed["headers"] == {
        "Authorization": "Bearer token",
        "developer-token": "dev-token",
        "Content-Type": "application/json",
        "login-customer-id": "1112223333",
    }
    assert observed["params"] is None
    assert "WHERE campaign.id = 987654321" in observed["json_body"]["query"]
    assert 'segments.date >= "2026-03-01"' in observed["json_body"]["query"]
    assert 'segments.date <= "2026-03-03"' in observed["json_body"]["query"]
    assert "metrics.video_views" not in observed["json_body"]["query"]

    collector.close()


def test_google_collector_normalizes_partial_payload_safely() -> None:
    collector = GoogleAdsCollector(laravel_client=_NoopLaravelClient())

    normalized = collector.normalize_record(
        {
            "campaign": None,
            "ad_group": None,
            "ad_group_ad": None,
            "metrics": None,
            "segments": None,
        }
    )

    assert normalized.ad_set_external_id == ""
    assert normalized.ad_external_id == ""
    assert normalized.impressions == 0
    assert normalized.clicks == 0
    assert normalized.spend == 0.0

    collector.close()


def test_google_collector_normalizes_google_ads_camel_case_payload() -> None:
    collector = GoogleAdsCollector(laravel_client=_NoopLaravelClient())

    normalized = collector.normalize_record(
        {
            "campaign": {
                "advertisingChannelType": "SEARCH",
                "name": "Campaign A",
            },
            "adGroup": {
                "id": "123",
                "name": "Ad Group A",
                "status": "ENABLED",
            },
            "adGroupAd": {
                "status": "ENABLED",
                "ad": {
                    "id": "456",
                    "name": "Ad A",
                },
            },
            "metrics": {
                "impressions": 12,
                "clicks": 3,
                "costMicros": 1234567,
                "conversions": 1,
                "interactions": 4,
            },
            "segments": {
                "date": "2026-04-02",
            },
        }
    )

    assert normalized.ad_set_external_id == "123"
    assert normalized.ad_external_id == "456"
    assert normalized.objective == "SEARCH"
    assert normalized.impressions == 12
    assert normalized.clicks == 3
    assert normalized.spend == 1.234567

    collector.close()