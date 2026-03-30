from __future__ import annotations

import os
from typing import Any

from sqlalchemy import create_engine, text


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
        LEFT JOIN platform_connections pc ON pc.id = cp.platform_connection_id
        JOIN campaigns c ON c.id = cp.campaign_id
        WHERE c.status = 'active'
          AND cp.is_active = true
          AND cp.external_campaign_id IS NOT NULL
          AND (pc.id IS NULL OR pc.is_connected = true)
          AND (pc.id IS NULL OR pc.error_count < 5)
        ORDER BY p.slug, cp.id
        """
    )

    with ENGINE.connect() as connection:
        rows = connection.execute(query).mappings().all()
        return [dict(row) for row in rows]