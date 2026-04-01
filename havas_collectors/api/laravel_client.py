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
        self._timeout_seconds = timeout_seconds
        self._headers = {
            "Accept": "application/json",
            "Content-Type": "application/json",
            "X-Internal-Token": internal_token,
        }
        self._client = self._build_client()

    def _build_client(self) -> httpx.Client:
        return httpx.Client(
            timeout=httpx.Timeout(self._timeout_seconds),
            headers=self._headers,
            trust_env=False,
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
        try:
            response = self._client.request(
                method=method,
                url=f"{self.base_url}/{path.lstrip('/')}",
                json=json_body,
            )
        except httpx.NetworkError as error:
            if "WinError 10106" in str(error):
                LOGGER.warning("Reinitializing HTTP client after WinError 10106 on path=%s", path)
                self._client.close()
                self._client = self._build_client()
            raise

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

    def get_campaign_commentary_context(
        self,
        campaign_id: int,
        *,
        days: int,
        platform_id: int | None = None,
    ) -> dict[str, Any]:
        path = f"campaigns/{campaign_id}/ai-context?days={days}"
        if platform_id is not None:
            path += f"&platform_id={platform_id}"

        payload = self._request("GET", path)
        return payload.get("data", {})

    def update_campaign_ai_comments(
        self,
        campaign_id: int,
        payload: dict[str, Any],
    ) -> dict[str, Any]:
        response = self._request(
            "PATCH",
            f"campaigns/{campaign_id}/ai-comments",
            json_body=payload,
        )
        return response.get("data", {})

    def close(self) -> None:
        self._client.close()

    # ------------------------------------------------------------------
    # Brief endpoints (used by ai_tasks.analyze_brief_task)
    # ------------------------------------------------------------------

    def get_brief(self, brief_id: int) -> dict[str, Any]:
        """Fetch a brief record including raw text, category, budget, and platform IDs.

        Laravel endpoint: GET /internal/v1/briefs/{id}
        """
        payload = self._request("GET", f"briefs/{brief_id}")
        return payload.get("data", {})

    def post_brief_ai_analysis(
        self,
        brief_id: int,
        analysis: dict[str, Any],
    ) -> dict[str, Any]:
        """Persist AI analysis results for a brief.

        Laravel endpoint: POST /internal/v1/briefs/{id}/ai-analysis
        """
        payload = self._request(
            "POST",
            f"briefs/{brief_id}/ai-analysis",
            json_body=analysis,
        )
        return payload.get("data", {})

    # ------------------------------------------------------------------
    # Report endpoints (used by ai_tasks.generate_report_commentary_task)
    # ------------------------------------------------------------------

    def get_report(self, report_id: int) -> dict[str, Any]:
        """Fetch a report record including aggregated metrics and campaign context.

        Laravel endpoint: GET /internal/v1/reports/{id}
        """
        payload = self._request("GET", f"reports/{report_id}")
        return payload.get("data", {})

    def post_report_commentary(
        self,
        report_id: int,
        commentary: dict[str, Any],
    ) -> dict[str, Any]:
        """Persist AI-generated commentary for a report.

        Laravel endpoint: POST /internal/v1/reports/{id}/commentary
        """
        payload = self._request(
            "POST",
            f"reports/{report_id}/commentary",
            json_body=commentary,
        )
        return payload.get("data", {})
