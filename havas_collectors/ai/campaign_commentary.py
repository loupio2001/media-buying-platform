from __future__ import annotations

import argparse
import logging
import os
from typing import Any

from havas_collectors.ai.report_commentator import ReportCommentator
from havas_collectors.ai.report_platform_section_commentary import build_commentary_request_from_context
from havas_collectors.api.laravel_client import LaravelInternalClient

LOGGER = logging.getLogger(__name__)


def _build_campaign_ai_payload(commentary: Any, *, days: int, platform_id: int | None) -> dict[str, Any]:
    payload: dict[str, Any] = {
        "ai_commentary_summary": commentary.summary,
        "ai_commentary_highlights": commentary.highlights,
        "ai_commentary_concerns": commentary.risks,
        "days": days,
        "platform_id": platform_id,
    }

    if commentary.recommendations:
        payload["ai_commentary_suggested_action"] = commentary.recommendations[0]

    return payload


def generate_and_persist_campaign_commentary(
    campaign_id: int,
    *,
    days: int,
    platform_id: int | None,
    client: LaravelInternalClient,
    commentator: ReportCommentator,
) -> dict[str, Any]:
    context = client.get_campaign_commentary_context(
        campaign_id,
        days=days,
        platform_id=platform_id,
    )
    request = build_commentary_request_from_context(context)
    commentary = commentator.generate_commentary(request)
    payload = _build_campaign_ai_payload(commentary, days=days, platform_id=platform_id)

    return client.update_campaign_ai_comments(campaign_id, payload)


def _required_env(name: str) -> str:
    value = os.getenv(name, "").strip()
    if not value:
        raise ValueError(f"Missing required environment variable: {name}")
    return value


def main(argv: list[str] | None = None) -> int:
    parser = argparse.ArgumentParser(
        description="Generate and persist AI commentary for campaign details filters.",
    )
    parser.add_argument("campaign_id", type=int)
    parser.add_argument("--days", type=int, default=7)
    parser.add_argument("--platform-id", type=int, default=None)
    parser.add_argument("--log-level", default="INFO")
    args = parser.parse_args(argv)

    logging.basicConfig(
        level=getattr(logging, str(args.log_level).upper(), logging.INFO),
        format="%(levelname)s %(name)s %(message)s",
    )

    client = LaravelInternalClient(
        base_url=_required_env("LARAVEL_API_URL"),
        internal_token=_required_env("INTERNAL_API_TOKEN"),
    )

    try:
        persisted = generate_and_persist_campaign_commentary(
            args.campaign_id,
            days=max(1, min(args.days, 90)),
            platform_id=args.platform_id,
            client=client,
            commentator=ReportCommentator(),
        )
    except Exception:
        LOGGER.exception(
            "Failed to generate AI campaign commentary for campaign_id=%s",
            args.campaign_id,
        )
        return 1
    finally:
        client.close()

    LOGGER.info(
        "Persisted campaign AI commentary for campaign_id=%s",
        args.campaign_id,
    )
    LOGGER.debug("Persisted payload response: %s", persisted)
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
