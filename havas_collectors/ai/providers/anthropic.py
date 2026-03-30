from __future__ import annotations

from havas_collectors.ai.providers.base import AiProvider


class AnthropicProvider(AiProvider):
    """Connector scaffold for future Anthropic integration."""

    @property
    def name(self) -> str:
        return "anthropic"

    def invoke(self, *, system_prompt: str, user_prompt: str) -> str:
        raise NotImplementedError("Anthropic connector is not implemented yet")
