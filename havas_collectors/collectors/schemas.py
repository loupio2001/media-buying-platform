from __future__ import annotations

from datetime import date
from typing import Any

from pydantic import BaseModel, ConfigDict, Field


class NormalizedAdRecord(BaseModel):
    model_config = ConfigDict(extra="allow")

    snapshot_date: date
    ad_set_external_id: str
    ad_set_name: str
    ad_external_id: str
    ad_name: str
    ad_status: str | None = None
    ad_set_status: str | None = None
    objective: str | None = None
    budget: float | None = None
    budget_type: str | None = None

    impressions: int = 0
    reach: int = 0
    clicks: int = 0
    link_clicks: int = 0
    landing_page_views: int = 0
    spend: float = 0.0
    conversions: int = 0
    leads: int = 0
    video_views: int = 0
    video_completions: int = 0
    engagement: int = 0
    thumb_stop_rate: float | None = None

    format: str | None = None
    headline: str | None = None
    body: str | None = None
    cta: str | None = None
    destination_url: str | None = None
    creative_url: str | None = None

    custom_metrics: dict[str, Any] = Field(default_factory=dict)
    raw_response: dict[str, Any] = Field(default_factory=dict)


class AdSetUpsertPayload(BaseModel):
    campaign_platform_id: int
    external_id: str
    name: str
    status: str | None = None
    objective: str | None = None
    targeting_summary: str | None = None
    budget: float | None = None
    bid_strategy: str | None = None
    budget_type: str | None = None
    start_date: date | None = None
    end_date: date | None = None
    is_tracked: bool = True


class AdUpsertPayload(BaseModel):
    ad_set_id: int
    external_id: str
    name: str
    format: str | None = None
    status: str | None = None
    headline: str | None = None
    body: str | None = None
    cta: str | None = None
    destination_url: str | None = None
    creative_url: str | None = None
    is_tracked: bool = True


class SnapshotPayload(BaseModel):
    ad_id: int
    snapshot_date: date
    granularity: str = "daily"
    impressions: int = 0
    reach: int = 0
    frequency: float | None = None
    clicks: int = 0
    link_clicks: int = 0
    landing_page_views: int = 0
    ctr: float | None = None
    spend: float = 0.0
    cpm: float | None = None
    cpc: float | None = None
    conversions: int = 0
    cpa: float | None = None
    leads: int = 0
    cpl: float | None = None
    video_views: int = 0
    video_completions: int = 0
    vtr: float | None = None
    engagement: int = 0
    engagement_rate: float | None = None
    thumb_stop_rate: float | None = None
    custom_metrics: dict[str, Any] = Field(default_factory=dict)
    raw_response: dict[str, Any] = Field(default_factory=dict)
    source: str = "api"
