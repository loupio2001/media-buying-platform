from __future__ import annotations

import argparse
import logging
import os
from typing import Any

from havas_collectors.ai.report_commentator import CommentaryRequest, ReportCommentary, ReportCommentator
from havas_collectors.api.laravel_client import LaravelInternalClient

LOGGER = logging.getLogger(__name__)


def build_commentary_request_from_context(context: dict[str, Any]) -> CommentaryRequest:
    metrics = _resolve_metrics(context)
    campaign_context = _resolve_campaign_context(context)

    payload = {
        "metrics": metrics,
        "campaign_context": campaign_context,
        "period": _resolve_period(context),
        "language": context.get("language", "fr"),
        "tone": context.get("tone", "analytical"),
    }

    return CommentaryRequest.model_validate(payload)


def build_ai_comments_payload(commentary: ReportCommentary) -> dict[str, Any]:
    payload: dict[str, Any] = {
        "ai_summary": commentary.summary,
        "ai_highlights": commentary.highlights,
        "ai_concerns": commentary.risks,
    }

    if commentary.recommendations:
        payload["ai_suggested_action"] = commentary.recommendations[0]

    return payload


def generate_and_persist_commentary(
    report_platform_section_id: int,
    *,
    client: LaravelInternalClient,
    commentator: ReportCommentator,
) -> dict[str, Any]:
    context = client.get_report_platform_section_commentary_context(report_platform_section_id)
    request = build_commentary_request_from_context(context)
    commentary = commentator.generate_commentary(request)
    payload = build_ai_comments_payload(commentary)
    return client.update_report_platform_section_ai_comments(report_platform_section_id, payload)


def main(argv: list[str] | None = None) -> int:
    parser = argparse.ArgumentParser(
        description="Generate and persist AI commentary for a report platform section.",
    )
    parser.add_argument("report_platform_section_id", type=int)
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
        persisted = generate_and_persist_commentary(
            args.report_platform_section_id,
            client=client,
            commentator=ReportCommentator(),
        )
    except Exception:
        LOGGER.exception(
            "Failed to generate AI commentary for report_platform_section_id=%s",
            args.report_platform_section_id,
        )
        return 1
    finally:
        client.close()

    LOGGER.info(
        "Persisted AI commentary for report_platform_section_id=%s",
        args.report_platform_section_id,
    )
    LOGGER.debug("Persisted payload response: %s", persisted)
    return 0


def _resolve_period(context: dict[str, Any]) -> str:
    period = context.get("period")
    if isinstance(period, str) and period.strip():
        return period.strip()

    report = context.get("report")
    if isinstance(report, dict):
        report_period = report.get("period")
        if isinstance(report_period, dict):
            period_start = report_period.get("start")
            period_end = report_period.get("end")
            if isinstance(period_start, str) and period_start.strip() and isinstance(period_end, str) and period_end.strip():
                return f"{period_start.strip()} to {period_end.strip()}"

    period_start = context.get("period_start")
    period_end = context.get("period_end")
    if isinstance(period_start, str) and period_start.strip() and isinstance(period_end, str) and period_end.strip():
        return f"{period_start.strip()} to {period_end.strip()}"

    raise ValueError("commentary context must include period or period_start and period_end")


def _resolve_metrics(context: dict[str, Any]) -> dict[str, Any]:
    metrics = context.get("metrics")
    if isinstance(metrics, dict):
        return metrics

    report_platform_section = context.get("report_platform_section")
    if isinstance(report_platform_section, dict):
        section_metrics = report_platform_section.get("metrics")
        if isinstance(section_metrics, dict):
            return section_metrics

    raise ValueError("commentary context must include a metrics object")


def _resolve_campaign_context(context: dict[str, Any]) -> dict[str, str | float | int | bool | None]:
    raw_campaign_context = context.get("campaign_context")
    if isinstance(raw_campaign_context, dict):
        return raw_campaign_context
    if raw_campaign_context is not None:
        raise ValueError("campaign_context must be an object when provided")

    campaign_context: dict[str, str | float | int | bool | None] = {}

    campaign = context.get("campaign")
    if isinstance(campaign, dict):
        campaign_context["campaign_name"] = _as_str_or_none(campaign.get("name"))
        campaign_context["campaign_status"] = _as_str_or_none(campaign.get("status"))
        campaign_context["campaign_objective"] = _as_str_or_none(campaign.get("objective"))
        campaign_context["currency"] = _as_str_or_none(campaign.get("currency"))

        client = campaign.get("client")
        if isinstance(client, dict):
            campaign_context["client_name"] = _as_str_or_none(client.get("name"))
            category = client.get("category")
            if isinstance(category, dict):
                campaign_context["category_name"] = _as_str_or_none(category.get("name"))
                campaign_context["category_slug"] = _as_str_or_none(category.get("slug"))

    platform = context.get("platform")
    if isinstance(platform, dict):
        campaign_context["platform"] = _as_str_or_none(platform.get("slug") or platform.get("name"))
        campaign_context["platform_name"] = _as_str_or_none(platform.get("name"))

    campaign_platform = context.get("campaign_platform")
    if isinstance(campaign_platform, dict):
        campaign_context["campaign_budget"] = _as_float_or_none(campaign_platform.get("budget"))
        campaign_context["campaign_budget_type"] = _as_str_or_none(campaign_platform.get("budget_type"))
        campaign_context["campaign_is_active"] = _as_bool_or_none(campaign_platform.get("is_active"))

    performance = context.get("performance_vs_benchmark")
    if isinstance(performance, dict):
        campaign_context["performance_status"] = _as_str_or_none(performance.get("overall_status"))
        kpi_targets = performance.get("kpi_targets")
        if isinstance(kpi_targets, dict):
            for metric in ("ctr", "cpa", "cpl", "cpc"):
                target_payload = kpi_targets.get(metric)
                if isinstance(target_payload, dict):
                    campaign_context[f"target_{metric}"] = _as_float_or_none(target_payload.get("target"))

    return {key: value for key, value in campaign_context.items() if value is not None}


def _as_str_or_none(value: Any) -> str | None:
    return value if isinstance(value, str) and value.strip() else None


def _as_float_or_none(value: Any) -> float | None:
    if value is None or isinstance(value, bool):
        return None
    if isinstance(value, (int, float)):
        return float(value)
    if isinstance(value, str) and value.strip():
        try:
            return float(value)
        except ValueError:
            return None
    return None


def _as_bool_or_none(value: Any) -> bool | None:
    return value if isinstance(value, bool) else None


def _required_env(name: str) -> str:
    value = os.getenv(name, "").strip()
    if not value:
        raise ValueError(f"Missing required environment variable: {name}")
    return value


if __name__ == "__main__":
    raise SystemExit(main())