from __future__ import annotations

import logging

from tenacity import retry, retry_if_exception, stop_after_attempt, wait_exponential

from havas_collectors.ai.providers.base import AiProvider

LOGGER = logging.getLogger(__name__)


def _is_retryable_anthropic_error(error: BaseException) -> bool:
    """Return True for errors that should be retried (rate-limits, transient server faults)."""
    try:
        import anthropic as _anthropic  # type: ignore[import-untyped]

        if isinstance(error, _anthropic.RateLimitError):
            return True
        if isinstance(error, _anthropic.APIStatusError):
            return error.status_code >= 500
        if isinstance(error, (_anthropic.APIConnectionError, _anthropic.APITimeoutError)):
            return True
    except ImportError:
        pass
    return False


class AnthropicProvider(AiProvider):
    """Claude AI provider using the official Anthropic Python SDK (anthropic>=0.40).

    Authentication uses the ``api_key`` passed to the base constructor.
    The SDK is imported lazily so that the rest of the package still loads
    even if ``anthropic`` is not installed.
    """

    @property
    def name(self) -> str:
        return "anthropic"

    @retry(
        reraise=True,
        stop=stop_after_attempt(3),
        wait=wait_exponential(min=1, max=10),
        retry=retry_if_exception(_is_retryable_anthropic_error),
    )
    def invoke(self, *, system_prompt: str, user_prompt: str) -> str:
        """Call the Claude Messages API and return the text of the first content block.

        Args:
            system_prompt: Instruction block sent as the ``system`` parameter.
            user_prompt:   Human-turn message with the actual data payload.

        Returns:
            Raw text content from Claude's first content block.

        Raises:
            ImportError: If the ``anthropic`` package is not installed.
            ValueError:  If the response contains no usable text content.
        """
        try:
            import anthropic  # type: ignore[import-untyped]
        except ImportError as exc:
            raise ImportError(
                "The 'anthropic' package is required for AnthropicProvider. "
                "Install it with: pip install 'anthropic>=0.40'"
            ) from exc

        client = anthropic.Anthropic(
            api_key=self.api_key,
            timeout=self.timeout_seconds,
        )

        LOGGER.debug(
            "Calling Anthropic API model=%s max_tokens=%s",
            self.model,
            self.max_tokens,
        )

        response = client.messages.create(
            model=self.model,
            max_tokens=self.max_tokens,
            system=system_prompt,
            messages=[{"role": "user", "content": user_prompt}],
        )

        first_block = response.content[0] if response.content else None
        text: str | None = getattr(first_block, "text", None)

        if not isinstance(text, str) or not text.strip():
            raise ValueError(
                f"Anthropic response contained no usable text content "
                f"(model={self.model}, stop_reason={response.stop_reason!r})"
            )

        LOGGER.debug(
            "Anthropic response received stop_reason=%s input_tokens=%s output_tokens=%s",
            response.stop_reason,
            response.usage.input_tokens,
            response.usage.output_tokens,
        )

        return text
