from __future__ import annotations

import httpx
from tenacity import retry, retry_if_exception, stop_after_attempt, wait_exponential

from havas_collectors.ai.providers.base import AiProvider

GROQ_API_URL = "https://api.groq.com/openai/v1/chat/completions"


def _is_retryable_exception(error: BaseException) -> bool:
    if isinstance(error, (httpx.TimeoutException, httpx.NetworkError, TimeoutError)):
        return True
    if isinstance(error, httpx.HTTPStatusError):
        return error.response.status_code == 429 or error.response.status_code >= 500
    return False


class GroqProvider(AiProvider):
    @property
    def name(self) -> str:
        return "groq"

    @retry(
        reraise=True,
        stop=stop_after_attempt(3),
        wait=wait_exponential(min=1, max=10),
        retry=retry_if_exception(_is_retryable_exception),
    )
    def invoke(self, *, system_prompt: str, user_prompt: str) -> str:
        with httpx.Client(timeout=self.timeout_seconds) as client:
            response = client.post(
                GROQ_API_URL,
                headers={
                    "Authorization": f"Bearer {self.api_key}",
                    "Content-Type": "application/json",
                },
                json={
                    "model": self.model,
                    "messages": [
                        {"role": "system", "content": system_prompt},
                        {"role": "user", "content": user_prompt},
                    ],
                    "temperature": 0.2,
                    "max_tokens": self.max_tokens,
                    "response_format": {"type": "json_object"},
                },
            )
            response.raise_for_status()

        content = response.json()["choices"][0]["message"]["content"]
        if not isinstance(content, str) or not content.strip():
            raise ValueError("Groq response did not contain text content")

        return content
