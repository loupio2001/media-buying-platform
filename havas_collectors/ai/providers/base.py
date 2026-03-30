from __future__ import annotations

from abc import ABC, abstractmethod


class AiProvider(ABC):
    """Provider contract used by report commentary generation."""

    def __init__(self, *, api_key: str, model: str, timeout_seconds: float, max_tokens: int) -> None:
        self.api_key = api_key
        self.model = model
        self.timeout_seconds = timeout_seconds
        self.max_tokens = max_tokens

    @property
    @abstractmethod
    def name(self) -> str:
        """Stable provider identifier (groq, anthropic, gemini, xai)."""

    @property
    def is_available(self) -> bool:
        return bool(self.api_key)

    @abstractmethod
    def invoke(self, *, system_prompt: str, user_prompt: str) -> str:
        """Invoke model and return text content."""
