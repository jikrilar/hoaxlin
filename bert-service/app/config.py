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


def _int_in_range(name: str, default: int, minimum: int, maximum: int) -> int:
    raw = os.getenv(name)
    if raw is None:
        return default
    try:
        value = int(raw)
    except ValueError as exc:
        raise ValueError(f"{name} must be an integer") from exc
    if not minimum <= value <= maximum:
        raise ValueError(f"{name} must be between {minimum} and {maximum}")
    return value


def _positive_float(name: str, default: float) -> float:
    raw = os.getenv(name)
    if raw is None:
        return default
    try:
        value = float(raw)
    except ValueError as exc:
        raise ValueError(f"{name} must be a number") from exc
    if value <= 0.0:
        raise ValueError(f"{name} must be positive")
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
    require_release_manifest: bool = False
    # Direct Settings construction is explicit; env defaults defer to the release.
    sequence_length_explicit: bool = True
    models_dir: str = "/app/models"
    datasets_dir: str = "/app/datasets"
    baseline_fallback_path: str | None = "/opt/hoaxlin/baseline/indobert-hoax/v1.0.0"
    replay_buffer_path: str | None = None
    train_epochs: int = 3
    train_batch_size: int = 8
    train_learning_rate: float = 2e-5
    train_seed: int = 42
    max_train_csv_bytes: int = 50 * 1024 * 1024

    @classmethod
    def from_env(cls) -> "Settings":
        model_path = os.getenv("BERT_MODEL_PATH", "").strip() or None
        token = (
            os.getenv("BERT_INTERNAL_API_TOKEN", "").strip()
            or os.getenv("BERT_SERVICE_TOKEN", "").strip()
            or None
        )
        model_version = os.getenv("BERT_MODEL_VERSION", "").strip() or None
        raw_fallback = os.getenv("BERT_BASELINE_FALLBACK_PATH")
        baseline_fallback_path = (
            "/opt/hoaxlin/baseline/indobert-hoax/v1.0.0"
            if raw_fallback is None
            else (raw_fallback.strip() or None)
        )
        replay_buffer_path = os.getenv("BERT_REPLAY_BUFFER_PATH", "").strip() or None
        return cls(
            service_name=os.getenv("BERT_SERVICE_NAME", "Hoax BERT Inference Service"),
            service_version=os.getenv("BERT_SERVICE_VERSION", "0.1.0"),
            model_path=model_path,
            model_version=model_version,
            internal_api_token=token,
            max_concurrency=_positive_int("BERT_MAX_CONCURRENCY", 2),
            max_text_length=_positive_int("BERT_MAX_TEXT_LENGTH", 20_000),
            max_sequence_length=_positive_int("BERT_MAX_SEQUENCE_LENGTH", 512),
            sequence_length_explicit="BERT_MAX_SEQUENCE_LENGTH" in os.environ,
            local_files_only=_boolean("BERT_LOCAL_FILES_ONLY", True),
            require_release_manifest=_boolean(
                "BERT_REQUIRE_RELEASE_MANIFEST", False
            ),
            models_dir=os.getenv("BERT_MODELS_DIR", "/app/models").strip() or "/app/models",
            datasets_dir=os.getenv("BERT_DATASETS_DIR", "/app/datasets").strip() or "/app/datasets",
            baseline_fallback_path=baseline_fallback_path,
            replay_buffer_path=replay_buffer_path,
            train_epochs=_int_in_range("BERT_TRAIN_EPOCHS", 3, 2, 3),
            train_batch_size=_positive_int("BERT_TRAIN_BATCH_SIZE", 8),
            train_learning_rate=_positive_float("BERT_TRAIN_LEARNING_RATE", 2e-5),
            train_seed=_int_in_range("BERT_TRAIN_SEED", 42, 0, 2**31 - 1),
            max_train_csv_bytes=_positive_int("BERT_MAX_TRAIN_CSV_BYTES", 50 * 1024 * 1024),
        )
