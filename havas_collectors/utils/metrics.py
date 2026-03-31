"""Metric calculation helpers.

CRITICAL MATH RULE: Ratio metrics (CTR, CPM, CPC, CPA, CPL, VTR, frequency)
must NEVER be averaged with AVG(). Always recompute from the underlying sums.
"""
from __future__ import annotations


def calculate_ctr(clicks: float, impressions: float) -> float:
    """CTR = SUM(clicks) / SUM(impressions) * 100.

    Args:
        clicks: Total click count.
        impressions: Total impression count.

    Returns:
        Click-through rate as a percentage, or 0.0 when impressions is zero.
    """
    if impressions <= 0:
        return 0.0
    return (clicks / impressions) * 100.0


def calculate_cpm(spend: float, impressions: float) -> float:
    """CPM = SUM(spend) / SUM(impressions) * 1000.

    Args:
        spend: Total spend amount.
        impressions: Total impression count.

    Returns:
        Cost per thousand impressions, or 0.0 when impressions is zero.
    """
    if impressions <= 0:
        return 0.0
    return (spend / impressions) * 1000.0


def calculate_cpc(spend: float, clicks: float) -> float:
    """CPC = SUM(spend) / SUM(clicks).

    Args:
        spend: Total spend amount.
        clicks: Total click count.

    Returns:
        Cost per click, or 0.0 when clicks is zero.
    """
    if clicks <= 0:
        return 0.0
    return spend / clicks


def calculate_cpa(spend: float, conversions: float) -> float:
    """CPA = SUM(spend) / SUM(conversions).

    Args:
        spend: Total spend amount.
        conversions: Total conversion count.

    Returns:
        Cost per acquisition, or 0.0 when conversions is zero.
    """
    if conversions <= 0:
        return 0.0
    return spend / conversions


def calculate_cpl(spend: float, leads: float) -> float:
    """CPL = SUM(spend) / SUM(leads).

    Args:
        spend: Total spend amount.
        leads: Total lead count.

    Returns:
        Cost per lead, or 0.0 when leads is zero.
    """
    if leads <= 0:
        return 0.0
    return spend / leads


def calculate_vtr(video_views: float, impressions: float) -> float:
    """VTR = SUM(video_views) / SUM(impressions) * 100.

    Args:
        video_views: Total video view count.
        impressions: Total impression count.

    Returns:
        View-through rate as a percentage, or 0.0 when impressions is zero.
    """
    if impressions <= 0:
        return 0.0
    return (video_views / impressions) * 100.0


def calculate_frequency(impressions: float, reach: float) -> float:
    """Frequency = SUM(impressions) / SUM(reach).

    Args:
        impressions: Total impression count.
        reach: Total unique reach count.

    Returns:
        Average frequency, or 0.0 when reach is zero.
    """
    if reach <= 0:
        return 0.0
    return impressions / reach
