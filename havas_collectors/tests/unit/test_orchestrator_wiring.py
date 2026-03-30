from __future__ import annotations

from types import SimpleNamespace
from typing import Any

from havas_collectors.db import reader
from havas_collectors.tasks.celery_app import app


def test_celery_app_registers_pull_tasks() -> None:
    assert "havas_collectors.tasks.pull_tasks.pull_all_active_campaigns" in app.tasks
    assert "havas_collectors.tasks.pull_tasks.pull_single_campaign_platform" in app.tasks


def test_reader_filters_to_supported_connected_platforms(monkeypatch) -> None:
    observed: dict[str, Any] = {}

    class _Result:
        def mappings(self) -> "_Result":
            return self

        def all(self) -> list[dict[str, Any]]:
            return [{"campaign_platform_id": 1, "platform_slug": "meta"}]

    class _Connection:
        def execute(self, query: Any, params: dict[str, Any]) -> _Result:
            observed["sql"] = str(query)
            observed["params"] = params
            return _Result()

        def __enter__(self) -> "_Connection":
            return self

        def __exit__(self, exc_type, exc, tb) -> None:
            return None

    monkeypatch.setattr(reader, "ENGINE", SimpleNamespace(connect=lambda: _Connection()))

    rows = reader.get_active_campaign_platforms()

    assert rows == [{"campaign_platform_id": 1, "platform_slug": "meta"}]
    assert observed["params"] == {"supported_slugs": ["google", "meta", "tiktok"]}
    assert "p.api_supported = true" in observed["sql"]
    assert "p.is_active = true" in observed["sql"]
    assert "p.slug = ANY(:supported_slugs)" in observed["sql"]
    assert "JOIN platform_connections pc" in observed["sql"]