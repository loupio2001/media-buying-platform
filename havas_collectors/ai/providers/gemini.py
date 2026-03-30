from __future__ import annotations

from havas_collectors.ai.providers.base import AiProvider


class GeminiProvider(AiProvider):
    """Connector scaffold for future Gemini integration."""

    @property
    def name(self) -> str:
        return "gemini"

    def invoke(self, *, system_prompt: str, user_prompt: str) -> str:
        raise NotImplementedError("Gemini connector is not implemented yet")
