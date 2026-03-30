from __future__ import annotations

from datetime import date
from typing import Any

import pytest

from havas_collectors.collectors.base_collector import BaseCollector
from havas_collectors.collectors.schemas import NormalizedAdRecord


class _FakeLaravelClient:
    def __init__(self) -> None:
        self.ad_set_calls: list[dict[str, Any]] = []
        self.ad_calls: list[dict[str, Any]] = []
        self.snapshot_batches: list[list[dict[str, Any]]] = []

    def upsert_ad_set(self, payload: dict[str, Any]) -> int:
        self.ad_set_calls.append(payload)
        return 10

    def upsert_ad(self, payload: dict[str, Any]) -> int:
        self.ad_calls.append(payload)
        return 20

    def post_snapshots_batch(self, snapshots: list[dict[str, Any]]) -> list[int]:
        self.snapshot_batches.append(snapshots)
        return [100 + index for index, _ in enumerate(snapshots)]


class _CollectCollector(BaseCollector):
    def __init__(self, laravel_client: Any, rows: list[dict[str, Any]]) -> None:
        super().__init__(laravel_client=laravel_client)
        self._rows = rows

    @property
    def platform_name(self) -> str:
        return "dummy"

    def authenticate(self, credentials: dict[str, Any]) -> None:
        return None

    def fetch_ad_level_data(
        self,
        account_id: str,
        external_campaign_id: str,
        date_from: date,
        date_to: date,
    ) -> list[dict[str, Any]]:
        return self._rows

    def normalize_record(self, raw_row: dict[str, Any]) -> NormalizedAdRecord:
        if raw_row.get("boom"):
            raise ValueError("bad row")

        return NormalizedAdRecord(
            snapshot_date=date(2026, 3, 30),
            ad_set_external_id=str(raw_row["ad_set_external_id"]),
            ad_set_name="Ad Set",
            ad_external_id=str(raw_row["ad_external_id"]),
            ad_name="Ad",
            impressions=100,
            clicks=10,
            spend=50.0,
        )


def test_collect_deduplicates_upserts_and_returns_unique_counts() -> None:
    client = _FakeLaravelClient()
    collector = _CollectCollector(
        laravel_client=client,
        rows=[
            {"ad_set_external_id": "aset-1", "ad_external_id": "ad-1"},
            {"ad_set_external_id": "aset-1", "ad_external_id": "ad-1"},
            {"ad_set_external_id": "aset-1", "ad_external_id": "ad-2"},
        ],
    )

    summary = collector.collect(
        credentials={},
        account_id="acc",
        external_campaign_id="cmp",
        date_from=date(2026, 3, 1),
        date_to=date(2026, 3, 2),
        campaign_platform_id=1,
    )

    assert summary == {
        "ad_sets": 1,
        "ads": 2,
        "snapshots": 3,
        "processed_rows": 3,
        "skipped_rows": 0,
        "failed_rows": 0,
    }
    assert len(client.ad_set_calls) == 1
    assert len(client.ad_calls) == 2
    assert len(client.snapshot_batches) == 1

    collector.close()


def test_collect_raises_when_all_rows_fail_and_no_snapshot_persisted() -> None:
    client = _FakeLaravelClient()
    collector = _CollectCollector(
        laravel_client=client,
        rows=[
            {"boom": True, "ad_set_external_id": "aset-1", "ad_external_id": "ad-1"},
        ],
    )

    with pytest.raises(RuntimeError, match="No snapshots were persisted"):
        collector.collect(
            credentials={},
            account_id="acc",
            external_campaign_id="cmp",
            date_from=date(2026, 3, 1),
            date_to=date(2026, 3, 2),
            campaign_platform_id=1,
        )

    collector.close()