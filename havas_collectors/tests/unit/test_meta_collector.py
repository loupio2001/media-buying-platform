from __future__ import annotations

from datetime import date

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