from __future__ import annotations

import os
import re
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


def _pick_value(mapping: dict[str, Any], *keys: str) -> Any:
    for key in keys:
        if key in mapping:
            return mapping[key]

    return None


def _normalize_customer_id(value: Any) -> str:
    normalized = re.sub(r"\D", "", str(value or ""))
    return normalized


class GoogleAdsCollector(BaseCollector):
    @property
    def platform_name(self) -> str:
        return "google_ads"

    def __init__(self, *args: Any, **kwargs: Any) -> None:
        super().__init__(*args, **kwargs)
        self._headers: dict[str, str] = {}
        self._api_url_template = os.getenv(
            "GOOGLE_ADS_API_URL",
            "https://googleads.googleapis.com/v23/customers/{account_id}/googleAds:searchStream",
        )

    def authenticate(self, credentials: dict[str, Any]) -> None:
        extra_credentials = _as_dict(credentials.get("extra_credentials"))
        access_token = str(credentials.get("access_token") or "")
        developer_token = str(
            credentials.get("developer_token")
            or extra_credentials.get("developer_token", "")
            or os.getenv("GOOGLE_ADS_DEVELOPER_TOKEN", "")
        )
        login_customer_id = str(
            credentials.get("login_customer_id")
            or extra_credentials.get("login_customer_id", "")
        )

        if not access_token or not developer_token:
            raise ValueError("Google credentials must include access_token and developer_token")

        normalized_login_customer_id = _normalize_customer_id(login_customer_id)

        self._headers = {
            "Authorization": f"Bearer {access_token}",
            "developer-token": developer_token,
            "Content-Type": "application/json",
        }
        if normalized_login_customer_id:
            self._headers["login-customer-id"] = normalized_login_customer_id

    def fetch_ad_level_data(
        self,
        account_id: str,
        external_campaign_id: str,
        date_from: date,
        date_to: date,
    ) -> list[dict[str, Any]]:
        normalized_account_id = _normalize_customer_id(account_id)
        normalized_campaign_id = _normalize_customer_id(external_campaign_id)

        if not normalized_account_id or not normalized_campaign_id:
            raise ValueError("Google account_id and external_campaign_id must be numeric identifiers")

        url = self._api_url_template.format(account_id=normalized_account_id)
        query = (
            "SELECT "
            "campaign.id, campaign.name, campaign.advertising_channel_type, "
            "ad_group.id, ad_group.name, ad_group.status, "
            "ad_group_ad.ad.id, ad_group_ad.ad.name, ad_group_ad.status, "
            "metrics.impressions, metrics.clicks, metrics.cost_micros, "
            "metrics.conversions, "
            "metrics.interactions, segments.date "
            "FROM ad_group_ad "
            f"WHERE campaign.id = {normalized_campaign_id} "
            f'AND segments.date >= "{date_from.isoformat()}" '
            f'AND segments.date <= "{date_to.isoformat()}"'
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
                if not isinstance(chunk, dict):
                    continue
                for item in chunk.get("results", []):
                    rows.append(item)
        elif isinstance(response, dict):
            results = response.get("results", [])
            if isinstance(results, list):
                rows.extend(results)

        return rows

    def normalize_record(self, raw_row: dict[str, Any]) -> NormalizedAdRecord:
        campaign = _as_dict(_pick_value(raw_row, "campaign"))
        ad_group = _as_dict(_pick_value(raw_row, "ad_group", "adGroup"))
        ad_group_ad = _as_dict(_pick_value(raw_row, "ad_group_ad", "adGroupAd"))
        ad = _as_dict(ad_group_ad.get("ad"))
        metrics = _as_dict(raw_row.get("metrics"))
        segments = _as_dict(raw_row.get("segments"))

        return NormalizedAdRecord(
            snapshot_date=to_casablanca_date(segments.get("date", date.today())),
            ad_set_external_id=str(_pick_value(ad_group, "id") or ""),
            ad_set_name=str(_pick_value(ad_group, "name") or "Unknown ad group"),
            ad_external_id=str(_pick_value(ad, "id") or ""),
            ad_name=str(_pick_value(ad, "name") or "Unknown ad"),
            ad_status=str(_pick_value(ad_group_ad, "status") or "").lower() or None,
            ad_set_status=str(_pick_value(ad_group, "status") or "").lower() or None,
            objective=str(_pick_value(campaign, "advertising_channel_type", "advertisingChannelType") or "") or None,
            impressions=_as_int(_pick_value(metrics, "impressions")),
            clicks=_as_int(_pick_value(metrics, "clicks")),
            spend=_as_float(_pick_value(metrics, "cost_micros", "costMicros")) / 1_000_000,
            conversions=_as_int(_pick_value(metrics, "conversions")),
            video_views=_as_int(_pick_value(metrics, "video_views", "videoViews")),
            engagement=_as_int(_pick_value(metrics, "interactions")),
            custom_metrics={},
            raw_response=raw_row,
        )
