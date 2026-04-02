from __future__ import annotations

import argparse
import logging
from typing import Any

from havas_collectors.db.reader import get_active_campaign_platforms
from havas_collectors.tasks.pull_tasks import _sync_campaign_platform

LOGGER = logging.getLogger(__name__)


def _filter_targets(connection_id: int | None) -> list[dict[str, Any]]:
    targets = get_active_campaign_platforms()

    if connection_id is None:
        return targets

    return [
        target
        for target in targets
        if int(target.get("connection_id") or 0) == connection_id
    ]


def _sync_targets(targets: list[dict[str, Any]]) -> tuple[int, int]:
    succeeded = 0
    failed = 0

    for target in targets:
        try:
            _sync_campaign_platform(target)
            succeeded += 1
        except Exception:
            failed += 1
            LOGGER.exception(
                "Manual sync failed campaign_platform_id=%s platform=%s",
                target.get("campaign_platform_id"),
                target.get("platform_slug"),
            )

    return succeeded, failed


def main(argv: list[str] | None = None) -> int:
    parser = argparse.ArgumentParser(description="Run Havas platform collector sync without Celery.")
    group = parser.add_mutually_exclusive_group(required=True)
    group.add_argument("--all", action="store_true", help="Sync all active campaign-platform links")
    group.add_argument("--connection-id", type=int, help="Sync only the given platform connection")
    parser.add_argument("--log-level", default="INFO")
    args = parser.parse_args(argv)

    logging.basicConfig(
        level=getattr(logging, str(args.log_level).upper(), logging.INFO),
        format="%(levelname)s %(name)s %(message)s",
    )

    connection_id = args.connection_id if not args.all else None
    targets = _filter_targets(connection_id)

    if not targets:
        LOGGER.error("No active campaign platforms found for the selected manual sync scope.")
        return 1

    succeeded, failed = _sync_targets(targets)
    LOGGER.info(
        "Manual sync finished: succeeded=%s failed=%s selected=%s",
        succeeded,
        failed,
        len(targets),
    )
    return 1 if failed > 0 else 0


if __name__ == "__main__":
    raise SystemExit(main())
