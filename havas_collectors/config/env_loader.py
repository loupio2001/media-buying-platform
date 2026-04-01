from __future__ import annotations

import os
from pathlib import Path


def load_project_env() -> None:
    """Load key=value pairs from the project .env file into os.environ.

    Existing environment variables win. This keeps local Celery/collector runs on
    Windows aligned with the Laravel .env without requiring shell-level exports.
    """

    env_path = Path(__file__).resolve().parents[2] / ".env"
    if not env_path.exists():
        return

    for raw_line in env_path.read_text(encoding="utf-8").splitlines():
        line = raw_line.strip()
        if not line or line.startswith("#") or "=" not in line:
            continue

        key, value = line.split("=", 1)
        key = key.strip()
        if key == "" or key in os.environ:
            continue

        cleaned = value.strip().strip('"').strip("'")
        os.environ[key] = cleaned
