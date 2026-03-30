from __future__ import annotations

from datetime import date
from typing import Any

import pytest

from havas_collectors.collectors.meta_collector import MetaCollector


class _NoopLaravelClient:
    pass


def test_meta_collector_requires_access_token() -> None:
    collector = MetaCollector(laravel_client=_NoopLaravelClient())

    with pytest.raises(ValueError, match="access_token"):
        collector.authenticate({})

    collector.close()


def test_meta_collector_normalizes_actions_and_video_metrics() -> None:
    collector = MetaCollector(laravel_client=_NoopLaravelClient())
    normalized = collector.normalize_record(
        {
            "date_start": "2026-03-30",
            "adset_id": "aset-1",
            "adset_name": "Ad Set 1",
            "ad_id": "ad-1",
            "ad_name": "Ad 1",
            "objective": "OUTCOME_LEADS",
            "impressions": "1000",
            "reach": "400",
            "clicks": "35",
            "spend": "123.45",
            "outbound_clicks": [{"action_type": "outbound_click", "value": "12"}],
            "actions": [
                {"action_type": "lead", "value": "4"},
                {"action_type": "purchase", "value": "2"},
            ],
            "video_play_actions": [{"action_type": "video_view", "value": "80"}],
            "video_p100_watched_actions": [{"action_type": "video_view", "value": "25"}],
        }
    )

    assert normalized.snapshot_date == date(2026, 3, 30)
    assert normalized.link_clicks == 12
    assert normalized.conversions == 6
    assert normalized.leads == 4
    assert normalized.video_views == 80
    assert normalized.video_completions == 25

    collector.close()


def test_meta_collector_serializes_graph_query_params(monkeypatch: pytest.MonkeyPatch) -> None:
    collector = MetaCollector(laravel_client=_NoopLaravelClient())
    collector.authenticate({"access_token": "secret-token"})
    observed: dict[str, Any] = {}

    def fake_request_json(
        method: str,
        url: str,
        *,
        headers: dict[str, str] | None = None,
        params: dict[str, Any] | None = None,
        json_body: dict[str, Any] | None = None,
    ) -> dict[str, Any]:
        observed["method"] = method
        observed["url"] = url
        observed["headers"] = headers
        observed["params"] = params
        observed["json_body"] = json_body
        return {"data": []}

    monkeypatch.setattr(collector, "request_json", fake_request_json)

    rows = collector.fetch_ad_level_data(
        account_id="12345",
        external_campaign_id="67890",
        date_from=date(2026, 3, 1),
        date_to=date(2026, 3, 3),
    )

    assert rows == []
    assert observed["method"] == "GET"
    assert observed["url"] == "https://graph.facebook.com/v22.0/act_12345/insights"
    assert observed["headers"] == {"Content-Type": "application/json"}
    assert observed["json_body"] is None
    assert observed["params"]["access_token"] == "secret-token"
    assert observed["params"]["time_range"] == '{"since": "2026-03-01", "until": "2026-03-03"}'
    assert observed["params"]["filtering"] == '[{"field": "campaign.id", "operator": "EQUAL", "value": "67890"}]'

    collector.close()