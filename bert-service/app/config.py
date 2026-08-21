from __future__ import annotations

import os
from dataclasses import dataclass
from pathlib import Path


def _load_dotenv() -> None:
    """Load bert-service/.env and project .env if present (simple parser, no external dep).

    This mirrors python-dotenv for the limited key=value format used here.
    Existing env vars are not overwritten (process env takes precedence).
    """
    candidates = [
        Path(__file__).resolve().parent.parent / ".env",
        Path(__file__).resolve().parent.parent.parent / ".env",
    ]
    for env_path in candidates:
        if not env_path.is_file():
            continue
        try:
            for line in env_path.read_text(encoding="utf-8").splitlines():
                stripped = line.strip()
                if not stripped or stripped.startswith("#"):
                    continue
                if "=" not in stripped:
                    continue
                key, value = stripped.split("=", 1)
                key = key.strip()
                value = value.strip().strip('"').strip("'")
                if key and key not in os.environ:
                    os.environ[key] = value
        except Exception:
            continue


# Load .env files on import so Settings.from_env() sees them without extra setup.
_load_dotenv()


def _positive_int(name: str, default: int) -> int:
    raw = os.getenv(name)
    if raw is None:
        return default
    try:
        value = int(raw)
    except ValueError as exc:
        raise ValueError(f"{name} must be an integer") from exc
    if value < 1:
        raise ValueError(f"{name} must be at least 1")
    return value


def _boolean(name: str, default: bool) -> bool:
    raw = os.getenv(name)
    if raw is None:
        return default
    normalized = raw.strip().lower()
    if normalized in {"1", "true", "yes", "on"}:
        return True
    if normalized in {"0", "false", "no", "off"}:
        return False
    raise ValueError(f"{name} must be a boolean")


@dataclass(frozen=True, slots=True)
class Settings:
    service_name: str
    service_version: str
    model_path: str | None
    model_version: str | None
    internal_api_token: str | None
    max_concurrency: int
    max_text_length: int
    max_sequence_length: int
    local_files_only: bool

    @classmethod
    def from_env(cls) -> "Settings":
        model_path = os.getenv("BERT_MODEL_PATH", "").strip() or None
        token = (
            os.getenv("BERT_INTERNAL_API_TOKEN", "").strip()
            or os.getenv("BERT_SERVICE_TOKEN", "").strip()
            or None
        )
        model_version = os.getenv("BERT_MODEL_VERSION", "").strip() or None
        return cls(
            service_name=os.getenv("BERT_SERVICE_NAME", "Hoax BERT Inference Service"),
            service_version=os.getenv("BERT_SERVICE_VERSION", "0.1.0"),
            model_path=model_path,
            model_version=model_version,
            internal_api_token=token,
            max_concurrency=_positive_int("BERT_MAX_CONCURRENCY", 2),
            max_text_length=_positive_int("BERT_MAX_TEXT_LENGTH", 20_000),
            max_sequence_length=_positive_int("BERT_MAX_SEQUENCE_LENGTH", 512),
            local_files_only=_boolean("BERT_LOCAL_FILES_ONLY", True),
        )
