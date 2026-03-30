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


class GoogleAdsCollector(BaseCollector):
    @property
    def platform_name(self) -> str:
        return "google_ads"

    def __init__(self, *args: Any, **kwargs: Any) -> None:
        super().__init__(*args, **kwargs)
        self._headers: dict[str, str] = {}
        self._api_url_template = os.getenv(
            "GOOGLE_ADS_API_URL",
            "https://googleads.googleapis.com/v17/customers/{account_id}/googleAds:searchStream",
        )

    def authenticate(self, credentials: dict[str, Any]) -> None:
        access_token = str(credentials.get("access_token") or "")
        developer_token = str(
            credentials.get("developer_token")
            or credentials.get("api_key")
            or credentials.get("extra_credentials", {}).get("developer_token", "")
        )
        login_customer_id = str(
            credentials.get("login_customer_id")
            or credentials.get("extra_credentials", {}).get("login_customer_id", "")
        )

        if not access_token or not developer_token:
            raise ValueError("Google credentials must include access_token and developer_token")

        self._headers = {
            "Authorization": f"Bearer {access_token}",
            "developer-token": developer_token,
            "Content-Type": "application/json",
        }
        if login_customer_id:
            self._headers["login-customer-id"] = login_customer_id

    def fetch_ad_level_data(
        self,
        account_id: str,
        external_campaign_id: str,
        date_from: date,
        date_to: date,
    ) -> list[dict[str, Any]]:
        url = self._api_url_template.format(account_id=account_id)
        query = (
            "SELECT "
            "campaign.id, campaign.name, campaign.advertising_channel_type, "
            "ad_group.id, ad_group.name, ad_group.status, "
            "ad_group_ad.ad.id, ad_group_ad.ad.name, ad_group_ad.status, "
            "metrics.impressions, metrics.clicks, metrics.cost_micros, "
            "metrics.conversions, metrics.video_views, "
            "metrics.interactions, segments.date "
            "FROM ad_group_ad "
            f"WHERE campaign.id = {external_campaign_id} "
            f"AND segments.date BETWEEN '{date_from.isoformat()}' AND '{date_to.isoformat()}'"
        )

        response = self.request_json(
            "POST",
            url,
            headers=self._headers,
            json_body={"query": query},
        )

        rows: list[dict[str, Any]] = []
        if isinstance(response, list):
            for chunk in response:
                for item in chunk.get("results", []):
                    rows.append(item)
        elif isinstance(response, dict):
            rows.extend(response.get("results", []))

        return rows

    def normalize_record(self, raw_row: dict[str, Any]) -> NormalizedAdRecord:
        campaign = raw_row.get("campaign", {})
        ad_group = raw_row.get("ad_group", {})
        ad_group_ad = raw_row.get("ad_group_ad", {})
        ad = ad_group_ad.get("ad", {})
        metrics = raw_row.get("metrics", {})
        segments = raw_row.get("segments", {})

        return NormalizedAdRecord(
            snapshot_date=to_casablanca_date(segments.get("date", date.today())),
            ad_set_external_id=str(ad_group.get("id", "")),
            ad_set_name=str(ad_group.get("name", "Unknown ad group")),
            ad_external_id=str(ad.get("id", "")),
            ad_name=str(ad.get("name", "Unknown ad")),
            ad_status=str(ad_group_ad.get("status", "")).lower() or None,
            ad_set_status=str(ad_group.get("status", "")).lower() or None,
            objective=str(campaign.get("advertising_channel_type", "")) or None,
            impressions=_as_int(metrics.get("impressions")),
            clicks=_as_int(metrics.get("clicks")),
            spend=_as_float(metrics.get("cost_micros")) / 1_000_000,
            conversions=_as_int(metrics.get("conversions")),
            video_views=_as_int(metrics.get("video_views")),
            engagement=_as_int(metrics.get("interactions")),
            custom_metrics={},
            raw_response=raw_row,
        )
