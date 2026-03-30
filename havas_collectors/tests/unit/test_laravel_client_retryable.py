from __future__ import annotations

from typing import Any

import httpx
import pytest

from havas_collectors.api.laravel_client import LaravelInternalClient, _is_retryable_error


def _http_status_error(status_code: int) -> httpx.HTTPStatusError:
    request = httpx.Request("GET", "https://example.test/internal")
    response = httpx.Response(status_code, request=request)
    return httpx.HTTPStatusError("HTTP error", request=request, response=response)


@pytest.mark.parametrize(
    ("error", "expected"),
    [
        (_http_status_error(429), True),
        (_http_status_error(500), True),
        (_http_status_error(400), False),
        (httpx.TimeoutException("timeout"), True),
    ],
)
def test_is_retryable_error(error: BaseException, expected: bool) -> None:
    assert _is_retryable_error(error) is expected


def test_update_report_platform_section_ai_comments_uses_internal_patch_endpoint(
    monkeypatch: pytest.MonkeyPatch,
) -> None:
    client = LaravelInternalClient(
        base_url="https://example.test/api/internal/v1",
        internal_token="internal-token",
    )
    observed: dict[str, Any] = {}

    def fake_request(method: str, url: str, json: dict[str, Any] | None = None) -> httpx.Response:
        observed["method"] = method
        observed["url"] = url
        observed["json"] = json
        request = httpx.Request(method, url, json=json)
        return httpx.Response(
            200,
            request=request,
            json={
                "data": {
                    "id": 17,
                    "ai_summary": "Bon momentum sur Meta.",
                }
            },
        )

    monkeypatch.setattr(client._client, "request", fake_request)

    payload = {
        "ai_summary": "Bon momentum sur Meta.",
        "ai_highlights": ["CTR au-dessus du benchmark"],
        "ai_concerns": ["Reach en retrait"],
        "ai_suggested_action": "Reallouer plus de budget sur les ensembles forts.",
        "performance_flags": ["strong_ctr", "low_reach"],
        "top_performing_ads": ["Ad A"],
        "worst_performing_ads": ["Ad Z"],
    }

    response = client.update_report_platform_section_ai_comments(17, payload)

    assert observed == {
        "method": "PATCH",
        "url": "https://example.test/api/internal/v1/report-platform-sections/17/ai-comments",
        "json": payload,
    }
    assert response == {"id": 17, "ai_summary": "Bon momentum sur Meta."}

    client.close()


def test_get_report_platform_section_commentary_context_uses_internal_get_endpoint(
    monkeypatch: pytest.MonkeyPatch,
) -> None:
    client = LaravelInternalClient(
        base_url="https://example.test/api/internal/v1",
        internal_token="internal-token",
    )
    observed: dict[str, Any] = {}

    def fake_request(method: str, url: str, json: dict[str, Any] | None = None) -> httpx.Response:
        observed["method"] = method
        observed["url"] = url
        observed["json"] = json
        request = httpx.Request(method, url, json=json)
        return httpx.Response(
            200,
            request=request,
            json={
                "data": {
                    "metrics": {"spend": 320.5, "clicks": 40},
                    "period_start": "2026-03-01",
                    "period_end": "2026-03-30",
                }
            },
        )

    monkeypatch.setattr(client._client, "request", fake_request)

    response = client.get_report_platform_section_commentary_context(17)

    assert observed == {
        "method": "GET",
        "url": "https://example.test/api/internal/v1/report-platform-sections/17/ai-context",
        "json": None,
    }
    assert response == {
        "metrics": {"spend": 320.5, "clicks": 40},
        "period_start": "2026-03-01",
        "period_end": "2026-03-30",
    }

    client.close()
