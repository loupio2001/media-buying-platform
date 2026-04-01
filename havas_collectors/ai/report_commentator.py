from __future__ import annotations

import json
import logging
import math
import os
from pathlib import Path
from typing import Any, Literal

from pydantic import BaseModel, ConfigDict, Field, ValidationError, field_validator

from havas_collectors.ai.providers import SUPPORTED_PROVIDERS, AiProvider, build_provider, default_model_for

LOGGER = logging.getLogger(__name__)

# ---------------------------------------------------------------------------
# Prompt template helpers
# ---------------------------------------------------------------------------

_PROMPTS_DIR: Path = Path(__file__).parent / "prompts"
_COMMENTARY_TEMPLATE: str | None = None

# Inline fallback — used when the prompts/ directory or file is not present.
_COMMENTARY_TEMPLATE_FALLBACK: str = (
    "Build an analytical campaign performance comment with key strengths, "
    "risks, and actionable recommendations. Keep it concise and executive-ready.\n\n"
    "Campaign: {campaign_name}\n"
    "Platform: {platform}\n"
    "Campaign Objective: {objective}\n"
    "Reporting Period: {period}\n\n"
    "Objective Playbook:\n"
    "{objective_playbook}\n\n"
    "Input JSON:\n"
    "{metrics_json}"
)


def _load_commentary_template() -> str:
    """Load the report commentary user-prompt template from disk (cached after first read).

    Falls back to ``_COMMENTARY_TEMPLATE_FALLBACK`` when the prompt file is
    missing so the commentator continues to work in environments where the
    ``prompts/`` directory has not been deployed yet.
    """
    global _COMMENTARY_TEMPLATE
    if _COMMENTARY_TEMPLATE is None:
        template_path = _PROMPTS_DIR / "report_commentary.txt"
        try:
            _COMMENTARY_TEMPLATE = template_path.read_text(encoding="utf-8")
            LOGGER.debug("Loaded report commentary template from %s", template_path)
        except FileNotFoundError:
            LOGGER.warning(
                "report_commentary.txt not found at %s — using inline fallback template",
                template_path,
            )
            _COMMENTARY_TEMPLATE = _COMMENTARY_TEMPLATE_FALLBACK
    return _COMMENTARY_TEMPLATE


def _apply_template(template: str, placeholders: dict[str, str]) -> str:
    """Replace ``{key}`` placeholders without conflicting with JSON curly braces.

    Uses plain ``str.replace`` per key so that literal ``{`` / ``}`` characters
    inside JSON blocks in the template are left untouched.
    """
    result = template
    for key, value in placeholders.items():
        result = result.replace(f"{{{key}}}", value)
    return result


class CommentaryRequest(BaseModel):
    """Input payload used to generate report commentary."""

    model_config = ConfigDict(extra="forbid")

    metrics: dict[str, float]
    campaign_context: dict[str, str | float | int | bool | None] = Field(default_factory=dict)
    period: str = Field(min_length=1)
    language: Literal["fr", "en"] = "fr"
    tone: Literal["executive", "analytical", "direct"] = "analytical"

    @field_validator("metrics", mode="before")
    @classmethod
    def _validate_metrics(cls, value: Any) -> dict[str, float]:
        if not isinstance(value, dict):
            raise ValueError("metrics must be a dictionary")

        cleaned: dict[str, float] = {}
        for raw_key, raw_value in value.items():
            key = str(raw_key).strip()
            if not key:
                continue

            if raw_value is None or isinstance(raw_value, bool):
                continue

            numeric: float
            if isinstance(raw_value, (int, float)):
                numeric = float(raw_value)
            elif isinstance(raw_value, str):
                stripped = raw_value.strip()
                if not stripped:
                    continue
                try:
                    numeric = float(stripped)
                except ValueError:
                    continue
            else:
                continue

            if not math.isfinite(numeric):
                continue

            cleaned[key] = numeric

        if not cleaned:
            raise ValueError("metrics must contain at least one finite numeric value")

        return cleaned


class ReportCommentary(BaseModel):
    """Structured commentary returned by report commentator."""

    model_config = ConfigDict(extra="forbid")

    summary: str = Field(min_length=1)
    highlights: list[str] = Field(default_factory=list)
    risks: list[str] = Field(default_factory=list)
    recommendations: list[str] = Field(default_factory=list)
    confidence: float = Field(ge=0.0, le=1.0)

    @field_validator("summary")
    @classmethod
    def _clean_summary(cls, value: str) -> str:
        normalized = value.strip()
        if not normalized:
            raise ValueError("summary cannot be empty")
        return normalized

    @field_validator("highlights", "risks", "recommendations")
    @classmethod
    def _clean_lists(cls, value: list[str]) -> list[str]:
        cleaned: list[str] = []
        for item in value:
            normalized = item.strip()
            if normalized:
                cleaned.append(normalized)
        return cleaned


def _safe_ratio(numerator: float, denominator: float, factor: float = 1.0) -> float | None:
    if denominator <= 0:
        return None
    return (numerator / denominator) * factor


def _normalize_objective(raw_objective: Any) -> str:
    if not isinstance(raw_objective, str):
        return ""
    return raw_objective.strip().lower().replace(" ", "_")


def _objective_playbook(objective: str, language: Literal["fr", "en"]) -> str:
    objective_key = _normalize_objective(objective)

    if language == "fr":
        if objective_key in {"awareness", "reach", "video_views"}:
            return (
                "Prioriser la couverture et la visibilite: reach, impressions, frequency, CPM, VTR. "
                "Ne pas surponderer les conversions faibles si l'objectif est awareness."
            )
        if objective_key in {"traffic", "engagement"}:
            return (
                "Prioriser l'efficacite de trafic: clicks, link_clicks, CTR, CPC. "
                "Identifier fatigue creative/ciblage si CTR baisse ou CPC grimpe."
            )
        if objective_key in {"conversions", "leads", "app_installs"}:
            return (
                "Prioriser la performance bas de funnel: conversions/leads, CPA/CPL, volume utile. "
                "Signaler depense sans conversion comme risque majeur."
            )
        return (
            "Adapter l'analyse a l'objectif annonce et aux KPI disponibles, "
            "sans inferer des objectifs non fournis."
        )

    if objective_key in {"awareness", "reach", "video_views"}:
        return (
            "Prioritize coverage and visibility: reach, impressions, frequency, CPM, VTR. "
            "Do not over-penalize low conversions when objective is awareness."
        )
    if objective_key in {"traffic", "engagement"}:
        return (
            "Prioritize traffic efficiency: clicks, link_clicks, CTR, CPC. "
            "Flag creative fatigue/targeting issues when CTR drops or CPC rises."
        )
    if objective_key in {"conversions", "leads", "app_installs"}:
        return (
            "Prioritize lower-funnel outcomes: conversions/leads, CPA/CPL, usable volume. "
            "Treat spend without conversions as a major risk."
        )
    return (
        "Align analysis with the declared objective and available KPIs, "
        "without assuming missing business goals."
    )


def _env_flag(name: str, default: bool = False) -> bool:
    raw = os.getenv(name)
    if raw is None:
        return default
    normalized = raw.strip().lower()
    if normalized in {"1", "true", "yes", "on"}:
        return True
    if normalized in {"0", "false", "no", "off"}:
        return False
    return default


class ReportCommentator:
    """Generate campaign reporting commentary using selected AI provider with safe fallback."""

    def __init__(
        self,
        *,
        api_key: str | None = None,
        model: str | None = None,
        timeout_seconds: float = 25.0,
        max_tokens: int = 700,
    ) -> None:
        provider_name = os.getenv("AI_PROVIDER", "groq").strip().lower()
        self.provider_name = provider_name if provider_name else "groq"

        self.model = (model or os.getenv("AI_MODEL") or default_model_for(self.provider_name)).strip()
        self.timeout_seconds = timeout_seconds
        self.max_tokens = max_tokens
        self.force_llm = _env_flag("AI_FORCE_LLM", default=False)
        self._provider: AiProvider | None = None
        self._provider_error: str | None = None

        resolved_key = (api_key or os.getenv("AI_API_KEY", "")).strip()

        if self.provider_name not in SUPPORTED_PROVIDERS:
            self._provider_error = f"unsupported_provider:{self.provider_name}"
            LOGGER.warning(
                "Unsupported AI_PROVIDER=%s. Supported values: %s",
                self.provider_name,
                ", ".join(sorted(SUPPORTED_PROVIDERS)),
            )
            return

        self._provider = build_provider(
            self.provider_name,
            api_key=resolved_key,
            model=self.model,
            timeout_seconds=timeout_seconds,
            max_tokens=max_tokens,
        )

        if self._provider is None:
            self._provider_error = f"provider_init_failed:{self.provider_name}"
            LOGGER.warning("Failed to initialize provider for AI_PROVIDER=%s", self.provider_name)
            return

        if not self._provider.is_available:
            LOGGER.info("AI_API_KEY missing. Falling back to local commentary mode.")

    def generate_commentary(self, payload: CommentaryRequest | dict[str, Any]) -> ReportCommentary:
        """Return structured commentary from AI response or local fallback logic."""
        request = payload if isinstance(payload, CommentaryRequest) else CommentaryRequest.model_validate(payload)

        if self._provider is None:
            if self.force_llm:
                raise RuntimeError("LLM provider is unavailable and AI_FORCE_LLM is enabled.")
            return self._build_fallback(request, reason=f"{self.provider_name}_unavailable")

        if not self._provider.is_available:
            if self.force_llm:
                raise RuntimeError("AI_API_KEY is missing and AI_FORCE_LLM is enabled.")
            return self._build_fallback(request, reason=f"{self.provider_name}_unavailable")

        system_prompt, user_prompt = self._build_prompts(request)

        try:
            raw_text = self._provider.invoke(system_prompt=system_prompt, user_prompt=user_prompt)
            return self._parse_llm_response(raw_text)
        except (ValidationError, ValueError, json.JSONDecodeError) as error:
            if self.force_llm:
                raise RuntimeError(f"Invalid LLM output while AI_FORCE_LLM is enabled: {error}") from error
            LOGGER.warning("Invalid %s output, using fallback. reason=%s", self.provider_name, error)
            return self._build_fallback(request, reason="invalid_llm_output")
        except Exception as error:  # pragma: no cover - depends on network/SDK runtime.
            if self.force_llm:
                raise RuntimeError(f"{self.provider_name} call failed while AI_FORCE_LLM is enabled: {error}") from error
            LOGGER.exception("%s call failed, using fallback: %s", self.provider_name, error)
            return self._build_fallback(request, reason=f"{self.provider_name}_error")

    def _build_prompts(self, request: CommentaryRequest) -> tuple[str, str]:
        # System prompt is static — it defines the JSON output contract.
        system_prompt = (
            "You are a senior media performance analyst. "
            "Use only the provided input metrics and context. "
            "Do not invent metrics, values, channels, or outcomes. "
            "If a metric is missing, mention that it is unavailable. "
            "Return only valid JSON with this exact schema: "
            "{\"summary\": string, \"highlights\": string[], \"risks\": string[], "
            "\"recommendations\": string[], \"confidence\": number}. "
            "confidence must be between 0 and 1."
        )

        # Extract human-readable campaign/platform labels for the template.
        campaign_name: str = str(
            request.campaign_context.get("campaign_name")
            or "N/A"
        )
        platform: str = str(
            request.campaign_context.get("platform")
            or request.campaign_context.get("platform_name")
            or "N/A"
        )
        objective: str = str(request.campaign_context.get("campaign_objective") or "unspecified")
        objective_playbook = _objective_playbook(objective, request.language)

        # Serialise the full analytics payload as the metrics block.
        user_payload = {
            "language": request.language,
            "tone": request.tone,
            "period": request.period,
            "campaign_context": request.campaign_context,
            "metrics": request.metrics,
        }
        metrics_json: str = json.dumps(user_payload, ensure_ascii=True, sort_keys=True)

        # Load template from file (cached); apply placeholder substitution.
        template = _load_commentary_template()
        user_prompt = _apply_template(
            template,
            {
                "campaign_name": campaign_name,
                "platform": platform,
                "objective": objective,
                "objective_playbook": objective_playbook,
                "period": request.period,
                "metrics_json": metrics_json,
            },
        )

        return system_prompt, user_prompt


    def _parse_llm_response(self, raw_text: str) -> ReportCommentary:
        parsed_json = self._extract_json(raw_text)
        if not isinstance(parsed_json, dict):
            raise ValueError("LLM output must decode to a JSON object")

        return ReportCommentary.model_validate(parsed_json)

    def _extract_json(self, raw_text: str) -> dict[str, Any]:
        text = raw_text.strip()
        if not text:
            raise ValueError("Empty response from model")

        try:
            decoded = json.loads(text)
            if isinstance(decoded, dict):
                return decoded
        except json.JSONDecodeError:
            pass

        start_index = text.find("{")
        end_index = text.rfind("}")
        if start_index == -1 or end_index == -1 or end_index <= start_index:
            raise ValueError("No JSON object found in model response")

        snippet = text[start_index : end_index + 1]
        decoded = json.loads(snippet)
        if not isinstance(decoded, dict):
            raise ValueError("Decoded JSON is not an object")
        return decoded

    def _build_fallback(self, request: CommentaryRequest, *, reason: str) -> ReportCommentary:
        metrics = request.metrics
        objective = _normalize_objective(request.campaign_context.get("campaign_objective"))

        impressions = metrics.get("impressions", 0.0)
        clicks = metrics.get("clicks", 0.0)
        spend = metrics.get("spend", 0.0)
        conversions = metrics.get("conversions", 0.0)
        leads = metrics.get("leads", 0.0)

        ctr = metrics.get("ctr")
        if ctr is None:
            ctr = _safe_ratio(clicks, impressions, factor=100.0)

        cpc = metrics.get("cpc")
        if cpc is None:
            cpc = _safe_ratio(spend, clicks)

        cpa = metrics.get("cpa")
        if cpa is None:
            cpa = _safe_ratio(spend, conversions)

        cpl = metrics.get("cpl")
        if cpl is None:
            cpl = _safe_ratio(spend, leads)

        target_ctr = self._context_float(request.campaign_context, "target_ctr")
        target_cpa = self._context_float(request.campaign_context, "target_cpa")
        target_cpl = self._context_float(request.campaign_context, "target_cpl")

        highlights: list[str] = []
        risks: list[str] = []
        recommendations: list[str] = []

        if request.language == "fr":
            if ctr is not None and ctr >= 1.5:
                highlights.append(f"CTR solide a {ctr:.2f}% sur la periode.")
            if conversions > 0:
                highlights.append(f"Volume de conversions positif ({int(conversions)}).")
            if leads > 0:
                highlights.append(f"Generation de leads active ({int(leads)}).")

            if spend > 0 and clicks <= 0:
                risks.append("Depense engagee sans clic detecte.")
            if ctr is not None and ctr < 0.6:
                risks.append(f"CTR faible ({ctr:.2f}%), possible fatigue creative ou ciblage large.")
            if spend > 0 and conversions <= 0:
                risks.append("Depense sans conversion observee sur la periode.")

            if objective in {"awareness", "reach", "video_views"}:
                if impressions <= 0:
                    risks.append("Objectif awareness mais impressions nulles sur la periode.")
                recommendations.append(
                    "Optimiser la couverture (reach/frequency) et la qualite d'exposition avant de juger la conversion."
                )
            elif objective in {"traffic", "engagement"}:
                if clicks <= 0:
                    risks.append("Objectif trafic mais aucun clic detecte.")
                recommendations.append(
                    "Concentrer les optimisations sur CTR/CPC: creatives, ciblage et placements."
                )
            elif objective in {"conversions", "leads", "app_installs"}:
                if conversions <= 0 and leads <= 0:
                    risks.append("Objectif conversion/leads sans resultat bas de funnel.")
                recommendations.append(
                    "Prioriser les ensembles qui reduisent CPA/CPL et renforcent le volume de conversions utiles."
                )

            if target_ctr is not None and ctr is not None and ctr < target_ctr:
                recommendations.append(
                    f"Tester de nouveaux creatives pour rapprocher le CTR de la cible ({target_ctr:.2f}%)."
                )
            if target_cpa is not None and cpa is not None and cpa > target_cpa:
                recommendations.append(
                    f"Reallouer le budget vers les ensembles les plus rentables pour reduire le CPA ({cpa:.2f})."
                )
            if target_cpl is not None and cpl is not None and cpl > target_cpl:
                recommendations.append(
                    f"Ajuster l'offre et la landing page pour baisser le CPL actuel ({cpl:.2f})."
                )

            if not recommendations:
                recommendations.append(
                    "Maintenir une revue hebdomadaire des creatives, audiences et placements pour proteger la performance."
                )

            summary = (
                f"Commentaire genere en mode de secours ({reason}) pour {request.period}. "
                f"Depense {spend:.2f}, clics {int(clicks)}, conversions {int(conversions)}."
            )
        else:
            if ctr is not None and ctr >= 1.5:
                highlights.append(f"CTR is healthy at {ctr:.2f}% for the period.")
            if conversions > 0:
                highlights.append(f"Conversion volume is positive ({int(conversions)}).")
            if leads > 0:
                highlights.append(f"Lead generation is active ({int(leads)}).")

            if spend > 0 and clicks <= 0:
                risks.append("Spend was delivered without any recorded clicks.")
            if ctr is not None and ctr < 0.6:
                risks.append(f"CTR is low ({ctr:.2f}%), indicating creative fatigue or broad targeting.")
            if spend > 0 and conversions <= 0:
                risks.append("Spend occurred with no conversions during the period.")

            if objective in {"awareness", "reach", "video_views"}:
                if impressions <= 0:
                    risks.append("Awareness objective but impressions are zero over the period.")
                recommendations.append(
                    "Optimize reach/frequency and exposure quality before judging conversion outcomes."
                )
            elif objective in {"traffic", "engagement"}:
                if clicks <= 0:
                    risks.append("Traffic objective but no clicks were recorded.")
                recommendations.append(
                    "Prioritize CTR/CPC optimization via creative, targeting, and placement tuning."
                )
            elif objective in {"conversions", "leads", "app_installs"}:
                if conversions <= 0 and leads <= 0:
                    risks.append("Conversion/leads objective with no lower-funnel outcome.")
                recommendations.append(
                    "Prioritize ad sets that improve CPA/CPL while preserving useful conversion volume."
                )

            if target_ctr is not None and ctr is not None and ctr < target_ctr:
                recommendations.append(
                    f"Test new creative variants to close the CTR gap toward target ({target_ctr:.2f}%)."
                )
            if target_cpa is not None and cpa is not None and cpa > target_cpa:
                recommendations.append(
                    f"Shift budget toward stronger ad sets to reduce CPA from current level ({cpa:.2f})."
                )
            if target_cpl is not None and cpl is not None and cpl > target_cpl:
                recommendations.append(
                    f"Improve offer and landing experience to lower current CPL ({cpl:.2f})."
                )

            if not recommendations:
                recommendations.append(
                    "Keep a weekly review cadence on creative, audiences, and placements to protect efficiency."
                )

            summary = (
                f"Commentary generated in fallback mode ({reason}) for {request.period}. "
                f"Spend {spend:.2f}, clicks {int(clicks)}, conversions {int(conversions)}."
            )

        if not highlights:
            if request.language == "fr":
                highlights.append("Donnees limitees: lecture prudente necessaire pour confirmer une tendance.")
            else:
                highlights.append("Limited data available: trend confirmation requires more volume.")

        if not risks:
            if request.language == "fr":
                risks.append("Aucun risque critique detecte avec les metriques disponibles.")
            else:
                risks.append("No critical risk detected from currently available metrics.")

        confidence = 0.55
        if len(metrics) >= 6:
            confidence += 0.15
        if conversions > 0 or leads > 0:
            confidence += 0.1
        if reason == "invalid_llm_output":
            confidence -= 0.05
        confidence = max(0.0, min(1.0, confidence))

        return ReportCommentary(
            summary=summary,
            highlights=highlights,
            risks=risks,
            recommendations=recommendations,
            confidence=confidence,
        )

    def _context_float(
        self,
        context: dict[str, str | float | int | bool | None],
        key: str,
    ) -> float | None:
        value = context.get(key)
        if value is None or isinstance(value, bool):
            return None

        if isinstance(value, (int, float)):
            numeric = float(value)
        elif isinstance(value, str):
            stripped = value.strip()
            if not stripped:
                return None
            try:
                numeric = float(stripped)
            except ValueError:
                return None
        else:
            return None

        if not math.isfinite(numeric):
            return None
        return numeric
