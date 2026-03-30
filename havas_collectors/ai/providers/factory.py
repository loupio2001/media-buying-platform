from __future__ import annotations

from havas_collectors.ai.providers.anthropic import AnthropicProvider
from havas_collectors.ai.providers.base import AiProvider
from havas_collectors.ai.providers.gemini import GeminiProvider
from havas_collectors.ai.providers.groq import GroqProvider
from havas_collectors.ai.providers.xai import XaiProvider

SUPPORTED_PROVIDERS = {"groq", "anthropic", "gemini", "xai"}


def build_provider(
    provider_name: str,
    *,
    api_key: str,
    model: str,
    timeout_seconds: float,
    max_tokens: int,
) -> AiProvider | None:
    normalized = provider_name.strip().lower()

    if normalized == "groq":
        return GroqProvider(
            api_key=api_key,
            model=model,
            timeout_seconds=timeout_seconds,
            max_tokens=max_tokens,
        )

    if normalized == "anthropic":
        return AnthropicProvider(
            api_key=api_key,
            model=model,
            timeout_seconds=timeout_seconds,
            max_tokens=max_tokens,
        )

    if normalized == "gemini":
        return GeminiProvider(
            api_key=api_key,
            model=model,
            timeout_seconds=timeout_seconds,
            max_tokens=max_tokens,
        )

    if normalized == "xai":
        return XaiProvider(
            api_key=api_key,
            model=model,
            timeout_seconds=timeout_seconds,
            max_tokens=max_tokens,
        )

    return None


def default_model_for(provider_name: str) -> str:
    normalized = provider_name.strip().lower()
    if normalized == "anthropic":
        return "claude-3-5-sonnet-latest"
    if normalized == "gemini":
        return "gemini-2.0-flash"
    if normalized == "xai":
        return "grok-3"
    return "llama-3.3-70b-versatile"
