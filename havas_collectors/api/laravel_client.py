from __future__ import annotations

import logging
from typing import Any

import httpx
from tenacity import retry, retry_if_exception, stop_after_attempt, wait_exponential

LOGGER = logging.getLogger(__name__)


def _is_retryable_error(error: BaseException) -> bool:
    if isinstance(error, httpx.HTTPStatusError):
        status_code = error.response.status_code
        return status_code == 429 or 500 <= status_code < 600

    return isinstance(
        error,
        (httpx.TimeoutException, httpx.NetworkError, httpx.RemoteProtocolError),
    )


class LaravelInternalClient:
    """HTTP client for Laravel internal ingestion endpoints."""

    def __init__(
        self,
        base_url: str,
        internal_token: str,
        timeout_seconds: float = 30.0,
    ) -> None:
        self.base_url = base_url.rstrip("/")
        self._client = httpx.Client(
            timeout=httpx.Timeout(timeout_seconds),
            headers={
                "Accept": "application/json",
                "Content-Type": "application/json",
                "X-Internal-Token": internal_token,
            },
        )

    @retry(
        reraise=True,
        stop=stop_after_attempt(3),
        wait=wait_exponential(min=1, max=10),
        retry=retry_if_exception(_is_retryable_error),
    )
    def _request(
        self,
        method: str,
        path: str,
        *,
        json_body: dict[str, Any] | None = None,
    ) -> dict[str, Any]:
        response = self._client.request(
            method=method,
            url=f"{self.base_url}/{path.lstrip('/')}",
            json=json_body,
        )
        if response.status_code >= 400:
            LOGGER.warning("Laravel API error status=%s path=%s", response.status_code, path)
            response.raise_for_status()
        return response.json()

    def get_connection_credentials(self, connection_id: int) -> dict[str, Any]:
        payload = self._request("GET", f"platform-connections/{connection_id}/credentials")
        return payload.get("data", {})

    def refresh_connection_token(self, connection_id: int, *, force: bool = False) -> dict[str, Any]:
        payload = self._request(
            "POST",
            f"platform-connections/{connection_id}/refresh-token",
            json_body={"force": force},
        )
        return payload.get("data", {})

    def get_report_platform_section_commentary_context(
        self,
        report_platform_section_id: int,
    ) -> dict[str, Any]:
        payload = self._request(
            "GET",
            f"report-platform-sections/{report_platform_section_id}/ai-context",
        )
        return payload.get("data", {})

    def upsert_ad_set(self, payload: dict[str, Any]) -> int:
        response = self._request("POST", "ad-sets/upsert", json_body=payload)
        return int(response["data"]["id"])

    def upsert_ad(self, payload: dict[str, Any]) -> int:
        response = self._request("POST", "ads/upsert", json_body=payload)
        return int(response["data"]["id"])

    def post_snapshots_batch(self, snapshots: list[dict[str, Any]]) -> list[int]:
        response = self._request("POST", "snapshots/batch", json_body={"snapshots": snapshots})
        ids = response.get("data", {}).get("ids", [])
        return [int(value) for value in ids]

    def update_connection_sync_status(
        self,
        connection_id: int,
        *,
        success: bool,
        error_msg: str | None = None,
    ) -> None:
        self._request(
            "PATCH",
            f"platform-connections/{connection_id}/sync-status",
            json_body={"success": success, "error_msg": error_msg},
        )

    def update_report_platform_section_ai_comments(
        self,
        report_platform_section_id: int,
        payload: dict[str, Any],
    ) -> dict[str, Any]:
        response = self._request(
            "PATCH",
            f"report-platform-sections/{report_platform_section_id}/ai-comments",
            json_body=payload,
        )
        return response.get("data", {})

    def close(self) -> None:
        self._client.close()
