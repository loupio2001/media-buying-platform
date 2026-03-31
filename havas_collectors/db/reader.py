from __future__ import annotations

import os
from typing import Any

from sqlalchemy import create_engine, text

SUPPORTED_PLATFORM_SLUGS = ("google", "meta", "tiktok")


def _build_database_url() -> str:
    database_url = os.getenv("DATABASE_URL")
    if database_url:
        return database_url

    return (
        f"postgresql://{os.getenv('DB_USER')}:{os.getenv('DB_PASSWORD')}"
        f"@{os.getenv('DB_HOST', '127.0.0.1')}:{os.getenv('DB_PORT', '5432')}/{os.getenv('DB_NAME')}"
    )


ENGINE = create_engine(_build_database_url(), pool_pre_ping=True)


def get_active_campaign_platforms() -> list[dict[str, Any]]:
    query = text(
        """
        SELECT
            cp.id AS campaign_platform_id,
            cp.external_campaign_id,
            p.slug AS platform_slug,
            pc.id AS connection_id,
            pc.account_id,
            pc.is_connected,
            pc.error_count,
            c.name AS campaign_name,
            c.status AS campaign_status
        FROM campaign_platforms cp
        JOIN platforms p ON p.id = cp.platform_id
        JOIN platform_connections pc ON pc.id = cp.platform_connection_id
        JOIN campaigns c ON c.id = cp.campaign_id
        WHERE c.status = 'active'
          AND cp.is_active = true
          AND cp.external_campaign_id IS NOT NULL
          AND p.api_supported = true
          AND p.is_active = true
          AND p.slug = ANY(:supported_slugs)
          AND pc.is_connected = true
          AND pc.error_count < 5
        ORDER BY p.slug, cp.id
        """
    )

    with ENGINE.connect() as connection:
        rows = connection.execute(
            query,
            {"supported_slugs": list(SUPPORTED_PLATFORM_SLUGS)},
        ).mappings().all()
        return [dict(row) for row in rows]


def get_category_benchmarks(category_id: int, platform_id: int) -> list[dict[str, Any]]:
    """Return benchmark rows for the given category/platform combination.

    Used by :func:`havas_collectors.tasks.ai_tasks.analyze_brief_task` to
    supply industry benchmarks to the Claude brief analysis prompt.

    Args:
        category_id:  Internal ``categories.id`` value.
        platform_id:  Internal ``platforms.id`` value.

    Returns:
        List of dicts with keys ``metric``, ``min_value``, ``max_value``, ``unit``.
        Returns an empty list when no benchmarks are configured.
    """
    query = text(
        """
        SELECT metric, min_value, max_value, unit
        FROM category_benchmarks
        WHERE category_id = :category_id
          AND platform_id = :platform_id
        ORDER BY metric
        """
    )

    with ENGINE.connect() as connection:
        rows = connection.execute(
            query,
            {"category_id": category_id, "platform_id": platform_id},
        ).mappings().all()
        return [dict(row) for row in rows]