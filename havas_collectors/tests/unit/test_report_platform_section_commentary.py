from __future__ import annotations

from typing import Any

from havas_collectors.ai.report_commentator import CommentaryRequest, ReportCommentary
from havas_collectors.ai.report_platform_section_commentary import (
    build_ai_comments_payload,
    build_commentary_request_from_context,
    generate_and_persist_commentary,
)


def test_build_commentary_request_from_context_supports_period_start_end() -> None:
    request = build_commentary_request_from_context(
        {
            "metrics": {
                "impressions": 12000,
                "clicks": 240,
                "spend": 650.0,
            },
            "campaign_context": {
                "campaign_name": "Spring Lead Gen",
                "platform": "meta",
            },
            "period_start": "2026-03-01",
            "period_end": "2026-03-30",
        }
    )

    assert isinstance(request, CommentaryRequest)
    assert request.period == "2026-03-01 to 2026-03-30"
    assert request.language == "fr"
    assert request.tone == "analytical"


def test_build_commentary_request_from_nested_laravel_context() -> None:
    request = build_commentary_request_from_context(
        {
            "report_platform_section": {
                "id": 12,
                "metrics": {
                    "impressions": 12000,
                    "clicks": 240,
                    "spend": 650.0,
                },
            },
            "report": {
                "period": {
                    "start": "2026-03-01",
                    "end": "2026-03-30",
                },
            },
            "campaign": {
                "name": "Spring Lead Gen",
                "status": "active",
                "objective": "traffic",
                "currency": "MAD",
                "client": {
                    "name": "Client Test",
                    "category": {
                        "name": "Retail",
                        "slug": "retail",
                    },
                },
            },
            "platform": {
                "name": "Meta Ads",
                "slug": "meta-ads",
            },
            "campaign_platform": {
                "budget": 12000.0,
                "budget_type": "lifetime",
                "is_active": True,
            },
            "performance_vs_benchmark": {
                "overall_status": "above",
                "kpi_targets": {
                    "ctr": {"target": 2.5},
                    "cpa": {"target": 30.0},
                },
            },
        }
    )

    assert isinstance(request, CommentaryRequest)
    assert request.period == "2026-03-01 to 2026-03-30"
    assert request.metrics == {
        "impressions": 12000.0,
        "clicks": 240.0,
        "spend": 650.0,
    }
    assert request.campaign_context["campaign_name"] == "Spring Lead Gen"
    assert request.campaign_context["platform"] == "meta-ads"
    assert request.campaign_context["target_ctr"] == 2.5
    assert request.campaign_context["target_cpa"] == 30.0


def test_build_ai_comments_payload_maps_commentary_to_laravel_fields() -> None:
    payload = build_ai_comments_payload(
        ReportCommentary(
            summary="Meta tient une bonne dynamique sur la periode.",
            highlights=["CTR solide", "CPA maitrise"],
            risks=["Reach en retrait"],
            recommendations=["Augmenter le budget sur les ensembles les plus efficaces."],
            confidence=0.72,
        )
    )

    assert payload == {
        "ai_summary": "Meta tient une bonne dynamique sur la periode.",
        "ai_highlights": ["CTR solide", "CPA maitrise"],
        "ai_concerns": ["Reach en retrait"],
        "ai_suggested_action": "Augmenter le budget sur les ensembles les plus efficaces.",
    }


def test_generate_and_persist_commentary_fetches_context_generates_and_updates() -> None:
    client = _StubLaravelClient(
        context={
            "metrics": {
                "impressions": 18000,
                "clicks": 270,
                "spend": 810.0,
                "conversions": 14,
            },
            "campaign_context": {
                "campaign_name": "Q1 Lead Gen",
                "platform": "meta",
                "target_ctr": 1.5,
            },
            "period": "2026-03-01 to 2026-03-30",
            "language": "fr",
            "tone": "analytical",
        }
    )
    commentator = _StubCommentator()

    response = generate_and_persist_commentary(
        44,
        client=client,
        commentator=commentator,
    )

    assert commentator.request is not None
    assert commentator.request.period == "2026-03-01 to 2026-03-30"
    assert client.updated_section_id == 44
    assert client.updated_payload == {
        "ai_summary": "Commentaire structure.",
        "ai_highlights": ["CTR superieur a la cible."],
        "ai_concerns": ["Reach en dessous du potentiel."],
        "ai_suggested_action": "Reallouer du budget vers les meilleurs ensembles.",
    }
    assert response == {
        "id": 44,
        "ai_summary": "Commentaire structure.",
    }


class _StubLaravelClient:
    def __init__(self, *, context: dict[str, Any]) -> None:
        self.context = context
        self.updated_section_id: int | None = None
        self.updated_payload: dict[str, Any] | None = None

    def get_report_platform_section_commentary_context(self, report_platform_section_id: int) -> dict[str, Any]:
        assert report_platform_section_id == 44
        return self.context

    def update_report_platform_section_ai_comments(
        self,
        report_platform_section_id: int,
        payload: dict[str, Any],
    ) -> dict[str, Any]:
        self.updated_section_id = report_platform_section_id
        self.updated_payload = payload
        return {
            "id": report_platform_section_id,
            "ai_summary": payload["ai_summary"],
        }


class _StubCommentator:
    def __init__(self) -> None:
        self.request: CommentaryRequest | None = None

    def generate_commentary(self, payload: CommentaryRequest | dict[str, Any]) -> ReportCommentary:
        assert isinstance(payload, CommentaryRequest)
        self.request = payload
        return ReportCommentary(
            summary="Commentaire structure.",
            highlights=["CTR superieur a la cible."],
            risks=["Reach en dessous du potentiel."],
            recommendations=["Reallouer du budget vers les meilleurs ensembles."],
            confidence=0.81,
        )