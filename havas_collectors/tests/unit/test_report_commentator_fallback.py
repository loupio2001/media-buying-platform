from __future__ import annotations

import pytest

from havas_collectors.ai.report_commentator import ReportCommentary, ReportCommentator


@pytest.mark.parametrize(
    ("language", "expected_phrase"),
    [
        ("fr", "mode de secours"),
        ("en", "fallback mode"),
    ],
)
def test_generate_commentary_returns_valid_fallback_structure(
    monkeypatch: pytest.MonkeyPatch,
    language: str,
    expected_phrase: str,
) -> None:
    monkeypatch.delenv("AI_API_KEY", raising=False)

    commentator = ReportCommentator(api_key="")
    payload = {
        "metrics": {
            "impressions": 12000,
            "clicks": 180,
            "spend": 540.0,
            "conversions": 12,
            "leads": 6,
        },
        "campaign_context": {
            "target_ctr": 2.0,
            "target_cpa": 35.0,
            "target_cpl": 45.0,
        },
        "period": "2026-03-01 to 2026-03-30",
        "language": language,
        "tone": "analytical",
    }

    result = commentator.generate_commentary(payload)

    assert isinstance(result, ReportCommentary)
    assert result.summary.strip() != ""
    assert expected_phrase in result.summary
    assert result.highlights
    assert result.risks
    assert result.recommendations
    assert 0.0 <= result.confidence <= 1.0


def test_generate_commentary_falls_back_for_unknown_provider(
    monkeypatch: pytest.MonkeyPatch,
) -> None:
    monkeypatch.setenv("AI_PROVIDER", "unknown-provider")
    monkeypatch.setenv("AI_API_KEY", "test-key")

    commentator = ReportCommentator(api_key=None)
    payload = {
        "metrics": {
            "impressions": 1000,
            "clicks": 20,
            "spend": 50.0,
        },
        "period": "2026-03-01 to 2026-03-30",
        "language": "en",
        "tone": "analytical",
    }

    result = commentator.generate_commentary(payload)

    assert isinstance(result, ReportCommentary)
    assert "unknown-provider_unavailable" in result.summary


@pytest.mark.parametrize(
    ("language", "objective", "expected_hint"),
    [
        ("fr", "awareness", "reach"),
        ("fr", "traffic", "CTR/CPC"),
        ("fr", "conversions", "CPA/CPL"),
        ("en", "awareness", "reach/frequency"),
        ("en", "traffic", "CTR/CPC"),
        ("en", "conversions", "CPA/CPL"),
    ],
)
def test_generate_commentary_applies_objective_specific_fallback_guidance(
    monkeypatch: pytest.MonkeyPatch,
    language: str,
    objective: str,
    expected_hint: str,
) -> None:
    monkeypatch.delenv("AI_API_KEY", raising=False)

    commentator = ReportCommentator(api_key="")
    payload = {
        "metrics": {
            "impressions": 9000,
            "clicks": 90,
            "spend": 420.0,
            "conversions": 2,
            "leads": 1,
        },
        "campaign_context": {
            "campaign_objective": objective,
        },
        "period": "2026-03-01 to 2026-03-30",
        "language": language,
        "tone": "analytical",
    }

    result = commentator.generate_commentary(payload)

    assert isinstance(result, ReportCommentary)
    assert any(expected_hint.lower() in recommendation.lower() for recommendation in result.recommendations)
