from __future__ import annotations

import os
from datetime import date
from typing import Any

from havas_collectors.collectors.base_collector import BaseCollector
from havas_collectors.collectors.schemas import NormalizedAdRecord
from havas_collectors.utils.timezone import to_casablanca_date


def _as_int(value: Any) -> int:
    if value is None or value == "":
        return 0
    return int(float(value))


def _as_float(value: Any) -> float:
    if value is None or value == "":
        return 0.0
    return float(value)


def _sum_actions(actions: list[dict[str, Any]] | None, supported_types: set[str]) -> int:
    if not actions:
        return 0

    return sum(
        _as_int(action.get("value"))
        for action in actions
        if str(action.get("action_type", "")) in supported_types
    )


class MetaCollector(BaseCollector):
    @property
    def platform_name(self) -> str:
        return "meta"

    def __init__(self, *args: Any, **kwargs: Any) -> None:
        super().__init__(*args, **kwargs)
        self._api_url = os.getenv(
            "META_API_URL",
            "https://graph.facebook.com/v22.0/act_{account_id}/insights",
        )
        self._headers: dict[str, str] = {}
        self._access_token = ""

    def authenticate(self, credentials: dict[str, Any]) -> None:
        access_token = str(credentials.get("access_token") or "")
        if not access_token:
            raise ValueError("Meta credentials must include access_token")

        self._access_token = access_token
        self._headers = {"Content-Type": "application/json"}

    def fetch_ad_level_data(
        self,
        account_id: str,
        external_campaign_id: str,
        date_from: date,
        date_to: date,
    ) -> list[dict[str, Any]]:
        url = self._api_url.format(account_id=account_id)
        params = {
            "access_token": self._access_token,
            "level": "ad",
            "time_increment": 1,
            "time_range": {
                "since": date_from.isoformat(),
                "until": date_to.isoformat(),
            },
            "filtering": [
                {
                    "field": "campaign.id",
                    "operator": "EQUAL",
                    "value": external_campaign_id,
                }
            ],
            "fields": ",".join(
                [
                    "campaign_id",
                    "campaign_name",
                    "objective",
                    "adset_id",
                    "adset_name",
                    "ad_id",
                    "ad_name",
                    "impressions",
                    "reach",
                    "clicks",
                    "spend",
                    "outbound_clicks",
                    "actions",
                    "video_play_actions",
                    "video_p100_watched_actions",
                    "date_start",
                ]
            ),
        }

        rows: list[dict[str, Any]] = []
        next_url: str | None = url
        next_params: dict[str, Any] | None = params

        while next_url:
            response = self.request_json(
                "GET",
                next_url,
                headers=self._headers,
                params=next_params,
            )
            if not isinstance(response, dict):
                break

            rows.extend(list(response.get("data", [])))
            paging = response.get("paging", {})
            next_url = paging.get("next")
            next_params = None

        return rows

    def normalize_record(self, raw_row: dict[str, Any]) -> NormalizedAdRecord:
        outbound_clicks = raw_row.get("outbound_clicks") or []
        actions = raw_row.get("actions") or []
        video_plays = raw_row.get("video_play_actions") or []
        video_completions = raw_row.get("video_p100_watched_actions") or []

        conversions = _sum_actions(
            actions,
            {
                "lead",
                "offsite_conversion.fb_pixel_purchase",
                "purchase",
                "complete_registration",
            },
        )
        leads = _sum_actions(actions, {"lead"})

        return NormalizedAdRecord(
            snapshot_date=to_casablanca_date(raw_row.get("date_start", date.today())),
            ad_set_external_id=str(raw_row.get("adset_id", "")),
            ad_set_name=str(raw_row.get("adset_name", "Unknown ad set")),
            ad_external_id=str(raw_row.get("ad_id", "")),
            ad_name=str(raw_row.get("ad_name", "Unknown ad")),
            objective=str(raw_row.get("objective", "")) or None,
            impressions=_as_int(raw_row.get("impressions")),
            reach=_as_int(raw_row.get("reach")),
            clicks=_as_int(raw_row.get("clicks")),
            link_clicks=_sum_actions(outbound_clicks, {"outbound_click"}),
            spend=_as_float(raw_row.get("spend")),
            conversions=conversions,
            leads=leads,
            video_views=_sum_actions(video_plays, {"video_view", "video_play"}),
            video_completions=_sum_actions(video_completions, {"video_view", "video_play"}),
            custom_metrics={},
            raw_response=raw_row,
        )