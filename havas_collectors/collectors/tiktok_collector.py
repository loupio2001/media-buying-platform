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


def _as_dict(value: Any) -> dict[str, Any]:
    return value if isinstance(value, dict) else {}


class TikTokCollector(BaseCollector):
    @property
    def platform_name(self) -> str:
        return "tiktok"

    def __init__(self, *args: Any, **kwargs: Any) -> None:
        super().__init__(*args, **kwargs)
        self._headers: dict[str, str] = {}
        self._api_url = os.getenv(
            "TIKTOK_API_URL",
            "https://business-api.tiktok.com/open_api/v1.3/report/integrated/get/",
        )

    def authenticate(self, credentials: dict[str, Any]) -> None:
        access_token = str(credentials.get("access_token") or "")
        if not access_token:
            raise ValueError("TikTok credentials must include access_token")

        self._headers = {
            "Access-Token": access_token,
            "Content-Type": "application/json",
        }

    def fetch_ad_level_data(
        self,
        account_id: str,
        external_campaign_id: str,
        date_from: date,
        date_to: date,
    ) -> list[dict[str, Any]]:
        page = 1
        rows: list[dict[str, Any]] = []

        while True:
            body = {
                "advertiser_id": account_id,
                "report_type": "BASIC",
                "data_level": "AUCTION_AD",
                "dimensions": ["ad_id", "adgroup_id", "stat_time_day"],
                "metrics": [
                    "impressions",
                    "clicks",
                    "spend",
                    "conversions",
                    "reach",
                    "video_views_p25",
                    "video_views_p100",
                    "likes",
                    "comments",
                    "shares",
                ],
                "start_date": date_from.isoformat(),
                "end_date": date_to.isoformat(),
                "page": page,
                "page_size": 1000,
                "filtering": [
                    {
                        "field_name": "campaign_id",
                        "filter_type": "EQUAL",
                        "filter_value": external_campaign_id,
                    }
                ],
            }

            response = self.request_json(
                "POST",
                self._api_url,
                headers=self._headers,
                json_body=body,
            )
            if not isinstance(response, dict):
                raise RuntimeError("TikTok API returned a non-object response")

            code = response.get("code", 0)
            if code not in (0, "0", None):
                message = response.get("message") or response.get("msg") or "Unknown TikTok API error"
                request_id = response.get("request_id") or "n/a"
                raise RuntimeError(f"TikTok API error code={code} request_id={request_id}: {message}")

            data = _as_dict(response.get("data"))
            page_rows = data.get("list", [])
            if isinstance(page_rows, list):
                rows.extend(page_rows)

            page_info = _as_dict(data.get("page_info"))
            total_pages = int(page_info.get("total_page", page) or page)
            has_next_page = bool(page_info.get("has_next_page", page < total_pages))
            if not has_next_page or page >= total_pages:
                break

            page += 1

        return rows

    def normalize_record(self, raw_row: dict[str, Any]) -> NormalizedAdRecord:
        dimensions = _as_dict(raw_row.get("dimensions"))
        metrics = _as_dict(raw_row.get("metrics"))

        likes = _as_int(metrics.get("likes"))
        comments = _as_int(metrics.get("comments"))
        shares = _as_int(metrics.get("shares"))

        return NormalizedAdRecord(
            snapshot_date=to_casablanca_date(dimensions.get("stat_time_day", date.today())),
            ad_set_external_id=str(dimensions.get("adgroup_id", "")),
            ad_set_name=str(raw_row.get("adgroup_name", "TikTok ad group")),
            ad_external_id=str(dimensions.get("ad_id", "")),
            ad_name=str(raw_row.get("ad_name", "TikTok ad")),
            ad_status=str(raw_row.get("ad_status", "")).lower() or None,
            ad_set_status=str(raw_row.get("adgroup_status", "")).lower() or None,
            objective=str(raw_row.get("campaign_objective", "")) or None,
            impressions=_as_int(metrics.get("impressions")),
            reach=_as_int(metrics.get("reach")),
            clicks=_as_int(metrics.get("clicks")),
            spend=_as_float(metrics.get("spend")),
            conversions=_as_int(metrics.get("conversions")),
            video_views=_as_int(metrics.get("video_views_p25")),
            video_completions=_as_int(metrics.get("video_views_p100")),
            engagement=likes + comments + shares,
            custom_metrics={
                "likes": likes,
                "comments": comments,
                "shares": shares,
            },
            raw_response=raw_row,
        )
