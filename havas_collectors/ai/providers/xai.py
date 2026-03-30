from __future__ import annotations

from havas_collectors.ai.providers.base import AiProvider


class XaiProvider(AiProvider):
    """Connector scaffold for future xAI integration."""

    @property
    def name(self) -> str:
        return "xai"

    def invoke(self, *, system_prompt: str, user_prompt: str) -> str:
        raise NotImplementedError("xAI connector is not implemented yet")
