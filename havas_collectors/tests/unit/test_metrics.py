from __future__ import annotations

import pytest

from havas_collectors.utils.metrics import (
    calculate_cpc,
    calculate_cpm,
    calculate_cpa,
    calculate_cpl,
    calculate_ctr,
    calculate_frequency,
    calculate_vtr,
)


class TestCalculateCtr:
    def test_normal_case(self) -> None:
        assert calculate_ctr(clicks=100, impressions=10_000) == pytest.approx(1.0)

    def test_zero_impressions_returns_zero(self) -> None:
        assert calculate_ctr(clicks=100, impressions=0) == 0.0

    def test_perfect_ctr(self) -> None:
        assert calculate_ctr(clicks=1000, impressions=1000) == pytest.approx(100.0)


class TestCalculateCpm:
    def test_normal_case(self) -> None:
        assert calculate_cpm(spend=100.0, impressions=10_000) == pytest.approx(10.0)

    def test_zero_impressions_returns_zero(self) -> None:
        assert calculate_cpm(spend=100.0, impressions=0) == 0.0


class TestCalculateCpc:
    def test_normal_case(self) -> None:
        assert calculate_cpc(spend=500.0, clicks=100) == pytest.approx(5.0)

    def test_zero_clicks_returns_zero(self) -> None:
        assert calculate_cpc(spend=500.0, clicks=0) == 0.0


class TestCalculateCpa:
    def test_normal_case(self) -> None:
        assert calculate_cpa(spend=1000.0, conversions=20) == pytest.approx(50.0)

    def test_zero_conversions_returns_zero(self) -> None:
        assert calculate_cpa(spend=1000.0, conversions=0) == 0.0


class TestCalculateCpl:
    def test_normal_case(self) -> None:
        assert calculate_cpl(spend=500.0, leads=25) == pytest.approx(20.0)

    def test_zero_leads_returns_zero(self) -> None:
        assert calculate_cpl(spend=500.0, leads=0) == 0.0


class TestCalculateVtr:
    def test_normal_case(self) -> None:
        assert calculate_vtr(video_views=2000, impressions=10_000) == pytest.approx(20.0)

    def test_zero_impressions_returns_zero(self) -> None:
        assert calculate_vtr(video_views=2000, impressions=0) == 0.0


class TestCalculateFrequency:
    def test_normal_case(self) -> None:
        assert calculate_frequency(impressions=10_000, reach=5000) == pytest.approx(2.0)

    def test_zero_reach_returns_zero(self) -> None:
        assert calculate_frequency(impressions=10_000, reach=0) == 0.0

    def test_single_impression_per_person(self) -> None:
        assert calculate_frequency(impressions=5000, reach=5000) == pytest.approx(1.0)
