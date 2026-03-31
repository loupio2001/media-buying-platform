from __future__ import annotations

from datetime import date
from typing import Any

from havas_collectors.collectors.google_collector import GoogleAdsCollector


class YouTubeCollector(GoogleAdsCollector):
    """YouTube collector implementation via Google Ads API.

    This collector reuses Google Ads authentication and streaming query logic,
    while narrowing rows to VIDEO campaigns. It remains low-priority and can be
    expanded with YouTube-specific metrics when business requirements are ready.
    """

    @property
    def platform_name(self) -> str:
        return "youtube"

    def fetch_ad_level_data(
        self,
        account_id: str,
        external_campaign_id: str,
        date_from: date,
        date_to: date,
    ) -> list[dict[str, Any]]:
        rows = super().fetch_ad_level_data(
            account_id=account_id,
            external_campaign_id=external_campaign_id,
            date_from=date_from,
            date_to=date_to,
        )

        filtered: list[dict[str, Any]] = []
        for row in rows:
            campaign = row.get("campaign", {}) if isinstance(row, dict) else {}
            channel = str(campaign.get("advertisingChannelType", "")).upper()
            if channel == "VIDEO":
                filtered.append(row)

        # Fallback to unfiltered rows to avoid accidental data loss when channel
        # field is missing for some accounts/responses.
        return filtered if filtered else rows
