from __future__ import annotations

import json
import logging
import os
from pathlib import Path
from typing import Any

from pydantic import BaseModel, ConfigDict, Field, ValidationError
from tenacity import retry, retry_if_exception_type, stop_after_attempt, wait_exponential

from havas_collectors.ai.providers import AiProvider, build_provider, default_model_for, SUPPORTED_PROVIDERS

LOGGER = logging.getLogger(__name__)

_PROMPTS_DIR: Path = Path(__file__).parent / "prompts"
_BRIEF_ANALYSIS_TEMPLATE: str | None = None

_BRIEF_ANALYSIS_FALLBACK: str = (
    "Analyze the following media brief and return a structured JSON response.\n\n"
    "Brief: {brief_raw}\nCategory: {category_name}\nBudget: {budget} {currency}\n"
    "Benchmarks: {benchmarks}"
)


def _load_brief_analysis_template() -> str:
    global _BRIEF_ANALYSIS_TEMPLATE
    if _BRIEF_ANALYSIS_TEMPLATE is None:
        template_path = _PROMPTS_DIR / "brief_analysis.txt"
        try:
            _BRIEF_ANALYSIS_TEMPLATE = template_path.read_text(encoding="utf-8")
            LOGGER.debug("Loaded brief analysis template from %s", template_path)
        except FileNotFoundError:
            LOGGER.warning(
                "brief_analysis.txt not found at %s — using inline fallback",
                template_path,
            )
            _BRIEF_ANALYSIS_TEMPLATE = _BRIEF_ANALYSIS_FALLBACK
    return _BRIEF_ANALYSIS_TEMPLATE


def _apply_template(template: str, placeholders: dict[str, str]) -> str:
    result = template
    for key, value in placeholders.items():
        result = result.replace(f"{{{key}}}", value)
    return result


class BriefAnalysisResult(BaseModel):
    model_config = ConfigDict(extra="ignore")

    brief_quality_score: int = Field(ge=1, le=10)
    missing_information: list[str] = Field(default_factory=list)
    kpi_challenges: list[str] = Field(default_factory=list)
    questions_for_client: list[str] = Field(default_factory=list)
    channel_rationale: str = ""
    budget_split: dict[str, float] = Field(default_factory=dict)
    media_plan_draft: list[dict[str, Any]] = Field(default_factory=list)


class BriefAnalyzer:
    """Analyze campaign briefs using the configured AI provider."""

    _SYSTEM_PROMPT = (
        "You are a senior media planner. Analyze the brief and return ONLY a valid JSON object "
        "matching the provided schema. No markdown, no explanations, no extra text."
    )

    def __init__(
        self,
        *,
        api_key: str | None = None,
        model: str | None = None,
        timeout_seconds: float = 45.0,
        max_tokens: int = 1500,
    ) -> None:
        provider_name = os.getenv("AI_PROVIDER", "anthropic").strip().lower()
        self.provider_name = provider_name if provider_name else "anthropic"
        self.model = (model or os.getenv("AI_MODEL") or default_model_for(self.provider_name)).strip()
        self.timeout_seconds = timeout_seconds
        self.max_tokens = max_tokens
        self._provider: AiProvider | None = None

        resolved_key = (api_key or os.getenv("AI_API_KEY") or os.getenv("ANTHROPIC_API_KEY", "")).strip()

        if self.provider_name not in SUPPORTED_PROVIDERS:
            LOGGER.warning("Unsupported AI_PROVIDER=%s for brief analysis", self.provider_name)
            return

        self._provider = build_provider(
            self.provider_name,
            api_key=resolved_key,
            model=self.model,
            timeout_seconds=timeout_seconds,
            max_tokens=max_tokens,
        )

    def analyze_brief(
        self,
        brief_data: dict[str, Any],
        benchmarks: dict[str, Any] | list[dict[str, Any]],
    ) -> dict[str, Any]:
        """Run AI analysis on a brief and return structured results.

        Args:
            brief_data: Brief record from Laravel (raw_brief, budget, currency, category_name, etc.)
            benchmarks: Category/platform benchmarks to include in the prompt.

        Returns:
            Dict matching BriefAnalysisResult schema.
        """
        if self._provider is None or not self._provider.is_available:
            LOGGER.warning("AI provider unavailable — returning empty brief analysis")
            return self._empty_result()

        user_prompt = self._build_prompt(brief_data, benchmarks)

        try:
            return self._invoke_with_retry(user_prompt)
        except Exception as error:
            LOGGER.exception("Brief analysis failed: %s", error)
            return self._empty_result()

    @retry(
        reraise=True,
        stop=stop_after_attempt(2),
        wait=wait_exponential(min=2, max=20),
        retry=retry_if_exception_type(Exception),
    )
    def _invoke_with_retry(self, user_prompt: str) -> dict[str, Any]:
        assert self._provider is not None
        raw_text = self._provider.invoke(
            system_prompt=self._SYSTEM_PROMPT,
            user_prompt=user_prompt,
        )
        parsed = self._extract_json(raw_text)
        result = BriefAnalysisResult.model_validate(parsed)
        return result.model_dump()

    def _build_prompt(
        self,
        brief_data: dict[str, Any],
        benchmarks: dict[str, Any] | list[dict[str, Any]],
    ) -> str:
        template = _load_brief_analysis_template()
        return _apply_template(
            template,
            {
                "brief_raw": str(brief_data.get("raw_brief") or brief_data.get("brief_raw") or ""),
                "category_name": str(brief_data.get("category_name") or "Unknown"),
                "budget": str(brief_data.get("budget") or "0"),
                "currency": str(brief_data.get("currency") or "MAD"),
                "benchmarks": json.dumps(benchmarks, ensure_ascii=False),
            },
        )

    def _extract_json(self, raw_text: str) -> dict[str, Any]:
        text = raw_text.strip()
        if not text:
            raise ValueError("Empty response from AI provider")

        # Strip markdown fences
        if text.startswith("```"):
            lines = text.splitlines()
            text = "\n".join(lines[1:-1] if lines[-1].strip() == "```" else lines[1:])
            text = text.strip()

        try:
            decoded = json.loads(text)
            if isinstance(decoded, dict):
                return decoded
        except json.JSONDecodeError:
            pass

        start = text.find("{")
        end = text.rfind("}")
        if start == -1 or end == -1 or end <= start:
            raise ValueError("No JSON object found in AI response")

        decoded = json.loads(text[start : end + 1])
        if not isinstance(decoded, dict):
            raise ValueError("Decoded JSON is not an object")
        return decoded

    def _empty_result(self) -> dict[str, Any]:
        return BriefAnalysisResult(
            brief_quality_score=1,
            missing_information=["AI analysis unavailable — provider not configured"],
            kpi_challenges=[],
            questions_for_client=[],
            channel_rationale="",
            budget_split={},
            media_plan_draft=[],
        ).model_dump()


def analyze_brief(brief_data: dict[str, Any], benchmarks: dict[str, Any] | list[dict[str, Any]]) -> dict[str, Any]:
    """Module-level convenience wrapper for BriefAnalyzer.analyze_brief()."""
    return BriefAnalyzer().analyze_brief(brief_data, benchmarks)
