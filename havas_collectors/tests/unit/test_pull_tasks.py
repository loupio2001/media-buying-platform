from __future__ import annotations

from typing import Any

import pytest

from havas_collectors.tasks import pull_tasks


class _FakeCollector:
    def __init__(self, laravel_client: Any) -> None:
        self.laravel_client = laravel_client
        self.closed = False
        self.collect_calls: list[dict[str, Any]] = []
        self.summary = {
            "ad_sets": 1,
            "ads": 2,
            "snapshots": 3,
            "processed_rows": 3,
            "skipped_rows": 0,
            "failed_rows": 0,
        }

    def collect(self, **kwargs: Any) -> dict[str, int]:
        self.collect_calls.append(kwargs)
        return self.summary

    def close(self) -> None:
        self.closed = True


class _FakeLaravelClient:
    def __init__(self) -> None:
        self.updated_status: list[dict[str, Any]] = []

    def get_connection_credentials(self, connection_id: int) -> dict[str, Any]:
        return {
            "account_id": "from-credentials",
            "access_token": "secret",
            "extra_credentials": {"developer_token": "dev-token"},
        }

    def update_connection_sync_status(self, connection_id: int, *, success: bool, error_msg: str | None = None) -> None:
        self.updated_status.append(
            {"connection_id": connection_id, "success": success, "error_msg": error_msg}
        )

    def close(self) -> None:
        return None


def test_pull_all_active_campaigns_dispatches_supported_platforms(monkeypatch) -> None:
    dispatched: list[dict[str, Any]] = []

    monkeypatch.setattr(
        pull_tasks,
        "get_active_campaign_platforms",
        lambda: [
            {
                "campaign_platform_id": 1,
                "platform_slug": "meta",
                "external_campaign_id": "cmp-1",
                "connection_id": 11,
                "account_id": "acc-1",
            },
            {
                "campaign_platform_id": 2,
                "platform_slug": "linkedin",
                "external_campaign_id": "cmp-2",
                "connection_id": None,
                "account_id": "",
            },
        ],
    )
    monkeypatch.setattr(pull_tasks.pull_single_campaign_platform, "delay", lambda **kwargs: dispatched.append(kwargs))

    summary = pull_tasks.pull_all_active_campaigns()

    assert summary == {"dispatched": 1, "skipped": 1}
    assert dispatched == [
        {
            "campaign_platform_id": 1,
            "platform_slug": "meta",
            "external_campaign_id": "cmp-1",
            "connection_id": 11,
            "account_id": "acc-1",
        }
    ]


def test_pull_single_campaign_platform_uses_credentials_and_updates_sync(monkeypatch) -> None:
    fake_laravel = _FakeLaravelClient()
    fake_collector = _FakeCollector(fake_laravel)

    monkeypatch.setattr(pull_tasks, "_build_laravel_client", lambda: fake_laravel)
    monkeypatch.setitem(pull_tasks.COLLECTOR_MAP, "meta", lambda laravel_client: fake_collector)

    summary = pull_tasks.pull_single_campaign_platform.run(
        campaign_platform_id=42,
        platform_slug="meta",
        external_campaign_id="cmp-42",
        connection_id=7,
        account_id="fallback-account",
    )

    assert summary["snapshots"] == 3
    assert fake_collector.collect_calls[0]["account_id"] == "from-credentials"
    assert fake_collector.collect_calls[0]["credentials"]["developer_token"] == "dev-token"
    assert fake_laravel.updated_status == [
        {"connection_id": 7, "success": True, "error_msg": None}
    ]
    assert fake_collector.closed is True


def test_pull_single_campaign_platform_treats_zero_rows_as_success(monkeypatch) -> None:
    fake_laravel = _FakeLaravelClient()
    fake_collector = _FakeCollector(fake_laravel)
    fake_collector.summary = {
        "ad_sets": 0,
        "ads": 0,
        "snapshots": 0,
        "processed_rows": 0,
        "skipped_rows": 0,
        "failed_rows": 0,
    }

    monkeypatch.setattr(pull_tasks, "_build_laravel_client", lambda: fake_laravel)
    monkeypatch.setitem(pull_tasks.COLLECTOR_MAP, "meta", lambda laravel_client: fake_collector)

    summary = pull_tasks.pull_single_campaign_platform.run(
        campaign_platform_id=42,
        platform_slug="meta",
        external_campaign_id="cmp-42",
        connection_id=7,
        account_id="fallback-account",
    )

    assert summary["processed_rows"] == 0
    assert fake_laravel.updated_status == [
        {"connection_id": 7, "success": True, "error_msg": None}
    ]


def test_pull_single_campaign_platform_marks_partial_failures_as_failure(monkeypatch) -> None:
    fake_laravel = _FakeLaravelClient()
    fake_collector = _FakeCollector(fake_laravel)
    fake_collector.summary = {
        "ad_sets": 1,
        "ads": 2,
        "snapshots": 2,
        "processed_rows": 2,
        "skipped_rows": 0,
        "failed_rows": 1,
    }

    monkeypatch.setattr(pull_tasks, "_build_laravel_client", lambda: fake_laravel)
    monkeypatch.setitem(pull_tasks.COLLECTOR_MAP, "meta", lambda laravel_client: fake_collector)
    monkeypatch.setattr(
        pull_tasks.pull_single_campaign_platform,
        "retry",
        lambda exc: (_ for _ in ()).throw(exc),
    )

    with pytest.raises(RuntimeError, match="failed rows"):
        pull_tasks.pull_single_campaign_platform.run(
            campaign_platform_id=42,
            platform_slug="meta",
            external_campaign_id="cmp-42",
            connection_id=7,
            account_id="fallback-account",
        )

    assert fake_laravel.updated_status == [
        {"connection_id": 7, "success": False, "error_msg": "Collector had failed rows for campaign_platform_id=42"}
    ]