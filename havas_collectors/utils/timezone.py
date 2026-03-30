from __future__ import annotations

from datetime import date, datetime
from typing import TypeAlias
from zoneinfo import ZoneInfo

CASABLANCA_TZ = ZoneInfo("Africa/Casablanca")
DateInput: TypeAlias = date | datetime | str


def to_casablanca_date(value: DateInput) -> date:
    if isinstance(value, datetime):
        if value.tzinfo is None:
            value = value.replace(tzinfo=CASABLANCA_TZ)
        return value.astimezone(CASABLANCA_TZ).date()

    if isinstance(value, date):
        return value

    normalized = value.strip().replace("Z", "+00:00")
    parsed = datetime.fromisoformat(normalized)
    if parsed.tzinfo is None:
        parsed = parsed.replace(tzinfo=CASABLANCA_TZ)
    return parsed.astimezone(CASABLANCA_TZ).date()


def normalize_date_range(date_from: DateInput, date_to: DateInput) -> tuple[date, date]:
    start = to_casablanca_date(date_from)
    end = to_casablanca_date(date_to)
    if start > end:
        raise ValueError("date_from must be <= date_to")
    return start, end
