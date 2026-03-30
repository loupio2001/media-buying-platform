from __future__ import annotations

from datetime import date, datetime

import pytest

from havas_collectors.utils.timezone import normalize_date_range, to_casablanca_date


def test_to_casablanca_date_with_date_returns_same_date() -> None:
    value = date(2026, 3, 30)

    result = to_casablanca_date(value)

    assert result == date(2026, 3, 30)


def test_to_casablanca_date_with_naive_datetime_assumes_casablanca() -> None:
    value = datetime(2026, 3, 30, 10, 15, 0)

    result = to_casablanca_date(value)

    assert result == date(2026, 3, 30)


def test_to_casablanca_date_with_utc_z_string_converts_timezone() -> None:
    value = "2026-03-30T23:30:00Z"

    result = to_casablanca_date(value)

    assert result == date(2026, 3, 31)


def test_normalize_date_range_valid_case() -> None:
    start, end = normalize_date_range("2026-03-01", "2026-03-30")

    assert start == date(2026, 3, 1)
    assert end == date(2026, 3, 30)


def test_normalize_date_range_raises_when_start_is_after_end() -> None:
    with pytest.raises(ValueError, match="date_from must be <= date_to"):
        normalize_date_range("2026-03-31", "2026-03-30")
