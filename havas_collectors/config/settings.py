from __future__ import annotations

import os

from dotenv import load_dotenv

load_dotenv(override=False)

DATABASE_URL: str = os.getenv(
    "DATABASE_URL",
    (
        f"postgresql://{os.getenv('DB_USER', 'havas_reader')}:"
        f"{os.getenv('DB_PASSWORD', '')}@"
        f"{os.getenv('DB_HOST', '127.0.0.1')}:"
        f"{os.getenv('DB_PORT', '5432')}/"
        f"{os.getenv('DB_NAME', 'havas_media')}"
    ),
)

LARAVEL_API_URL: str = os.getenv(
    "LARAVEL_API_URL", "http://127.0.0.1:8000/api/internal/v1"
)

INTERNAL_API_TOKEN: str = os.getenv("INTERNAL_API_TOKEN", "")

REDIS_URL: str = os.getenv("REDIS_URL", "redis://127.0.0.1:6379/0")

ANTHROPIC_API_KEY: str = os.getenv("ANTHROPIC_API_KEY", "")

APP_TIMEZONE: str = os.getenv("APP_TIMEZONE", "Africa/Casablanca")
