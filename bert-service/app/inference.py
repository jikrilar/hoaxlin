from __future__ import annotations

import asyncio
import hashlib
import json
import logging
import math
import re
import threading
import time
from dataclasses import dataclass, field
from pathlib import Path
from typing import Any

from .config import Settings
from .contracts import FROZEN_BASELINE_VERSIONS, validate_version_name
from .decision_policy import decide, fingerprint
from .model_release import (
    sequence_contract,
    validate_incremental_release,
    validate_release,
)
from .preprocessing import encode_sequence

logger = logging.getLogger("uvicorn.error")

# Canonical labels for hoaxlin.id — binary model with meragukan derived via
# confidence threshold (see threshold.json sidecar).
EXPECTED_LABELS = {"valid", "hoax"}
EXPECTED_ID2LABEL = {0: "valid", 1: "hoax"}
EXPECTED_LABEL2ID = {"valid": 0, "hoax": 1}


@dataclass(frozen=True, slots=True)
class Prediction:
    label: str
    confidence: float
    raw_scores: dict[str, float]
    inference_ms: float
    token_count: int | None = None
    truncated: bool = False
    preprocessing_version: str | None = None
    decision: dict | None = None
    model_version: str | None = None


class ModelVersionNotFoundError(FileNotFoundError):
    """Raised when a requested model version directory does not exist."""


@dataclass(frozen=True, slots=True)
class LoadedModelState:
    model_path: str
    model_version: str
    threshold: float
    temperature: float
    evaluation_metadata: dict[str, Any]
    label_map: dict[int, str]
    preprocessing_version: str
    max_sequence_length: int
    truncation_strategy: str
    multi_claim_policy: str
    policy_fingerprint: str
    tokenizer: Any
    model: Any
    torch_module: Any
    tokenizer_lock: threading.Lock = field(
        default_factory=threading.Lock,
        repr=False,
        compare=False,
    )


class ModelRuntime:
    def __init__(self, settings: Settings) -> None:
        self.settings = settings
        self.status = "not_configured"
        self.load_error: str | None = None
        self.model_version: str | None = settings.model_version
        self.threshold: float | None = None
        self.temperature: float | None = None
        self.evaluation_metadata: dict[str, Any] = {}
        self.label_map: dict[int, str] = {}
        self.preprocessing_version: str = "1.0.0"
        self.max_sequence_length: int = settings.max_sequence_length
        self.truncation_strategy: str = "head-only"
        self.multi_claim_policy: str = "single-sequence-head-only"
        self.policy_fingerprint: str | None = None
        self._tokenizer: Any = None
        self._model: Any = None
        self._torch: Any = None
        self._active_model_path: str | None = settings.model_path
        self._initial_model_path: str | None = settings.model_path
        self._initial_model_version: str | None = settings.model_version
        self._state: LoadedModelState | None = None
        self._state_lock = threading.RLock()
        self._activation_lock = threading.Lock()
        self._fallback_tokenizer_lock = threading.Lock()
        self._semaphore = asyncio.Semaphore(settings.max_concurrency)

    @property
    def ready(self) -> bool:
        with self._state_lock:
            return self.status == "ready"

    @property
    def active_model_path(self) -> str | None:
        with self._state_lock:
            if self._state is not None:
                return self._state.model_path
            return self._active_model_path

    def status_snapshot(self) -> dict[str, Any]:
        with self._state_lock:
            return {
                "status": self.status,
                "ready": self.status == "ready",
                "load_error": self.load_error,
                "model_version": self.model_version,
                "label_map": dict(self.label_map),
                "threshold": self.threshold,
                "temperature": self.temperature,
                "preprocessing_version": self.preprocessing_version,
                "max_sequence_length": self.max_sequence_length,
                "truncation_strategy": self.truncation_strategy,
                "multi_claim_policy": self.multi_claim_policy,
                "policy_fingerprint": self.policy_fingerprint,
                "evaluation_metadata": dict(self.evaluation_metadata),
                "active_model_path": self._state.model_path if self._state is not None else self._active_model_path,
            }

    def load(self) -> None:
        if not self.settings.model_path:
            logger.info("BERT_MODEL_PATH not set — model will stay not_configured (readiness 503)")
            return

        with self._activation_lock:
            with self._state_lock:
                self.status = "loading"
            try:
                state = self._build_state_from_directory(
                    Path(self.settings.model_path),
                    expected_version=self.settings.model_version,
                )
                self._commit_state(state)
                logger.info(
                    "Model ready: %s (version %s, labels %s, threshold %s, temp %s)",
                    state.model_path,
                    state.model_version,
                    state.label_map,
                    state.threshold,
                    state.temperature,
                )
            except Exception as exc:
                with self._state_lock:
                    self.status = "failed"
                    self.load_error = f"{type(exc).__name__}: {exc}"
                    load_error = self.load_error
                logger.error("Model failed to load: %s", load_error)

    def resolve_model_directory(self, version_name: str) -> Path:
        cleaned = validate_version_name(version_name, allow_baseline=True)
        models_root = Path(self.settings.models_dir).resolve()
        candidates: list[tuple[Path, bool]] = [(models_root / cleaned, True)]
        if cleaned.lower() in FROZEN_BASELINE_VERSIONS:
            candidates.append((models_root / "indobert-hoax" / cleaned, True))
            if self._initial_model_path:
                initial = Path(self._initial_model_path)
                if self._initial_model_version == cleaned or initial.name == cleaned:
                    candidates.append((initial, False))

        for candidate, require_under_root in candidates:
            if not candidate.exists():
                continue
            resolved = candidate.resolve()
            if require_under_root and not resolved.is_relative_to(models_root):
                continue
            if resolved.is_dir():
                return resolved

        raise ModelVersionNotFoundError(
            f"Model version {cleaned!r} was not found under {models_root}"
        )

    def resolve_training_base_directory(self) -> tuple[Path, str, int]:
        with self._state_lock:
            state = self._state
            active_path = self._active_model_path
            active_version = self.model_version
            active_max_len = self.max_sequence_length

        if state is not None and Path(state.model_path).is_dir():
            return Path(state.model_path), state.model_version, state.max_sequence_length
        if active_path and Path(active_path).is_dir():
            return Path(active_path), active_version or Path(active_path).name, active_max_len

        baseline_dir = self.resolve_model_directory("v1.0.0")
        return baseline_dir, "v1.0.0", self.settings.max_sequence_length

    def activate_model(self, version_name: str) -> LoadedModelState:
        cleaned = validate_version_name(version_name, allow_baseline=True)
        target_dir = self.resolve_model_directory(cleaned)

        with self._activation_lock:
            new_state = self._build_state_from_directory(
                target_dir,
                expected_version=cleaned,
            )
            self._commit_state(new_state)
            logger.info(
                "Hot-swapped active model: %s (version %s, threshold %s, temp %s, fingerprint %s)",
                new_state.model_path,
                new_state.model_version,
                new_state.threshold,
                new_state.temperature,
                new_state.policy_fingerprint,
            )
            return new_state

    def _commit_state(self, state: LoadedModelState) -> None:
        with self._state_lock:
            self._state = state
            self._active_model_path = state.model_path
            self.status = "ready"
            self.load_error = None
            self.model_version = state.model_version
            self.threshold = state.threshold
            self.temperature = state.temperature
            self.evaluation_metadata = dict(state.evaluation_metadata)
            self.label_map = dict(state.label_map)
            self.preprocessing_version = state.preprocessing_version
            self.max_sequence_length = state.max_sequence_length
            self.truncation_strategy = state.truncation_strategy
            self.multi_claim_policy = state.multi_claim_policy
            self.policy_fingerprint = state.policy_fingerprint
            self._tokenizer = state.tokenizer
            self._model = state.model
            self._torch = state.torch_module

    def _build_state_from_directory(
        self,
        source: Path,
        *,
        expected_version: str | None,
    ) -> LoadedModelState:
        self._verify_model_directory_for(source)

        resolved_version = expected_version
        preprocessing_version = "1.0.0"
        max_sequence_length = self.settings.max_sequence_length
        truncation_strategy = "head-only"
        multi_claim_policy = "single-sequence-head-only"

        if self.settings.require_release_manifest:
            release = self._validate_strict_release(source, expected_version=expected_version)
            resolved_version = release["version"]
            (
                preprocessing_version,
                max_sequence_length,
                truncation_strategy,
                multi_claim_policy,
            ) = self._extract_sequence_contract(release)
            logger.info(
                "Release manifest verified: version=%s files=%s model_sha256=%s",
                release["version"],
                release["files"],
                release["model_sha256"],
            )

        tokenizer, model, torch_module, derived_version = self._load_model_and_tokenizer_from(
            source,
            configured_version=resolved_version,
        )
        resolved_version = derived_version
        label_map = self._verify_label_map_for(model)
        threshold, temperature = self._load_sidecars_for(source)

        if not self.settings.require_release_manifest:
            entry = self._release_manifest_entry(source, resolved_version)
            if isinstance(entry, dict):
                if "preprocessing_version" not in entry and "schema_version" not in entry:
                    entry = {**entry, "schema_version": "1.0"}
                (
                    preprocessing_version,
                    max_sequence_length,
                    truncation_strategy,
                    multi_claim_policy,
                ) = self._extract_sequence_contract(entry)
            self._verify_checksum_for(source, resolved_version)

        evaluation_metadata = self._read_evaluation_metadata(source, resolved_version)
        policy_fp = self._compute_policy_fingerprint(
            source=source,
            model_version=resolved_version,
            temperature=temperature,
            threshold=threshold,
            preprocessing_version=preprocessing_version,
            max_sequence_length=max_sequence_length,
            truncation_strategy=truncation_strategy,
            multi_claim_policy=multi_claim_policy,
        )
        return LoadedModelState(
            model_path=str(source),
            model_version=resolved_version,
            threshold=threshold,
            temperature=temperature,
            evaluation_metadata=evaluation_metadata,
            label_map=label_map,
            preprocessing_version=preprocessing_version,
            max_sequence_length=max_sequence_length,
            truncation_strategy=truncation_strategy,
            multi_claim_policy=multi_claim_policy,
            policy_fingerprint=policy_fp,
            tokenizer=tokenizer,
            model=model,
            torch_module=torch_module,
        )

    def _validate_strict_release(
        self,
        source: Path,
        *,
        expected_version: str | None,
    ) -> dict[str, Any]:
        manifest_path = source / "manifest.json"
        if manifest_path.is_file():
            try:
                manifest_data = json.loads(manifest_path.read_text(encoding="utf-8"))
            except (OSError, UnicodeError, json.JSONDecodeError):
                manifest_data = None
            if isinstance(manifest_data, dict) and manifest_data.get("kind") == "incremental-finetune":
                return validate_incremental_release(source, expected_version=expected_version)
        return validate_release(source, expected_version=expected_version)

    def _compute_policy_fingerprint(
        self,
        *,
        source: Path,
        model_version: str,
        temperature: float,
        threshold: float,
        preprocessing_version: str,
        max_sequence_length: int,
        truncation_strategy: str,
        multi_claim_policy: str,
    ) -> str:
        return fingerprint(
            {
                "schema": "bert-decision-v1",
                "files": {
                    name: _sha256(source / name) if (source / name).is_file() else None
                    for name in (
                        "model.safetensors",
                        "config.json",
                        "tokenizer.json",
                        "tokenizer_config.json",
                        "calibration.json",
                        "threshold.json",
                    )
                },
                "model_version": model_version,
                "temperature": temperature,
                "threshold": threshold,
                "preprocessing": preprocessing_version,
                "max_sequence_length": max_sequence_length,
                "truncation": truncation_strategy,
                "multi_claim": multi_claim_policy,
            }
        )

    def _extract_sequence_contract(
        self, release_or_manifest: dict[str, Any]
    ) -> tuple[str, int, str, str]:
        contract = sequence_contract(
            release_or_manifest, legacy=release_or_manifest.get("schema_version") == "1.0"
        )
        preprocessing_version = contract["preprocessing_version"]
        max_sequence_length = self.settings.max_sequence_length
        manifest_seq_len = contract["max_sequence_length"]
        if manifest_seq_len is not None:
            if (
                self.settings.sequence_length_explicit
                and self.settings.max_sequence_length != manifest_seq_len
            ):
                raise ValueError(
                    f"Configuration mismatch: configured max_sequence_length ({self.settings.max_sequence_length}) "
                    f"does not match release contract ({manifest_seq_len})"
                )
            max_sequence_length = manifest_seq_len
        return (
            preprocessing_version,
            max_sequence_length,
            contract["truncation_strategy"],
            contract["multi_claim_policy"],
        )

    def _apply_release_sequence_contract(self, release_or_manifest: dict[str, Any]) -> None:
        (
            self.preprocessing_version,
            self.max_sequence_length,
            self.truncation_strategy,
            self.multi_claim_policy,
        ) = self._extract_sequence_contract(release_or_manifest)

    def _load_sequence_contract_from_manifest_if_available(self) -> None:
        source = Path(self.settings.model_path)
        version = self.model_version or Path(source).name
        entry = self._release_manifest_entry(source, version)
        if isinstance(entry, dict):
            if "preprocessing_version" not in entry and "schema_version" not in entry:
                entry = {**entry, "schema_version": "1.0"}
            self._apply_release_sequence_contract(entry)

    def _verify_model_directory(self) -> None:
        self._verify_model_directory_for(Path(self.settings.model_path))

    def _verify_model_directory_for(self, source: Path) -> None:
        if not source.exists():
            raise FileNotFoundError(f"BERT_MODEL_PATH does not exist: {source}")
        if not source.is_dir():
            raise NotADirectoryError(f"BERT_MODEL_PATH is not a directory: {source}")
        # Required files for a HuggingFace sequence-classification model
        has_weights = (source / "model.safetensors").exists() or (source / "pytorch_model.bin").exists()
        if not has_weights:
            raise FileNotFoundError(
                f"Model weights not found in {source} (expected model.safetensors or pytorch_model.bin)"
            )
        if not (source / "config.json").exists():
            raise FileNotFoundError(f"config.json not found in {source}")
        # Tokenizer: at least one of these must exist
        has_tokenizer = (
            (source / "tokenizer.json").exists()
            or (source / "vocab.txt").exists()
            or (source / "spiece.model").exists()
        )
        if not has_tokenizer:
            logger.warning("No tokenizer files found in %s — will try to load tokenizer anyway", source)

    def _load_model_and_tokenizer(self) -> None:
        tokenizer, model, torch_module, version = self._load_model_and_tokenizer_from(
            Path(self.settings.model_path),
            configured_version=self.model_version,
        )
        self._tokenizer = tokenizer
        self._model = model
        self._torch = torch_module
        self.model_version = version

    def _load_model_and_tokenizer_from(
        self,
        source: Path,
        *,
        configured_version: str | None,
    ) -> tuple[Any, Any, Any, str]:
        import torch
        import transformers
        from transformers import AutoModelForSequenceClassification, AutoTokenizer

        source_str = str(source)
        tokenizer = AutoTokenizer.from_pretrained(
            source_str, local_files_only=self.settings.local_files_only
        )
        model = AutoModelForSequenceClassification.from_pretrained(
            source_str, local_files_only=self.settings.local_files_only
        )
        model.eval()
        version = configured_version or self._derive_model_version_from(model, source_str)
        logger.info(
            "Inference runtime: device=cpu torch=%s transformers=%s cuda_available=%s",
            torch.__version__,
            transformers.__version__,
            torch.cuda.is_available(),
        )
        return tokenizer, model, torch, version

    def _verify_label_map(self) -> None:
        self.label_map = self._verify_label_map_for(self._model)

    def _verify_label_map_for(self, model: Any) -> dict[int, str]:
        config = model.config
        id2label_raw = getattr(config, "id2label", {}) or {}
        label2id_raw = getattr(config, "label2id", {}) or {}
        num_labels = getattr(config, "num_labels", None)

        # Normalize id2label keys to int for comparison (HF stores as str keys in JSON)
        id2label: dict[int, str] = {}
        for k, v in id2label_raw.items():
            try:
                id2label[int(k)] = str(v)
            except (ValueError, TypeError):
                continue

        if num_labels is not None and num_labels != len(EXPECTED_LABELS):
            raise ValueError(
                f"Model num_labels={num_labels} does not match expected {len(EXPECTED_LABELS)} for valid/hoax"
            )

        actual_labels = set(id2label.values())
        if actual_labels != EXPECTED_LABELS:
            raise ValueError(
                f"Model id2label must be exactly {EXPECTED_LABELS}, got {actual_labels} (raw: {id2label_raw})"
            )

        # Verify id2label mapping is exactly {0: valid, 1: hoax}
        if id2label != EXPECTED_ID2LABEL:
            raise ValueError(f"Model id2label must be {EXPECTED_ID2LABEL}, got {id2label}")

        # Verify label2id is the inverse
        label2id: dict[str, int] = {str(k): int(v) for k, v in label2id_raw.items()}
        if set(label2id.keys()) != EXPECTED_LABELS:
            raise ValueError(
                f"Model label2id keys must be {EXPECTED_LABELS}, got {set(label2id.keys())}"
            )
        for label, idx in EXPECTED_LABEL2ID.items():
            if label2id.get(label) != idx:
                raise ValueError(
                    f"Model label2id[{label!r}] must be {idx}, got {label2id.get(label)}"
                )

        logger.info("Label map verified: %s", id2label)
        return id2label

    def _load_sidecars(self) -> None:
        self.threshold = None
        self.temperature = None
        threshold, temperature = self._load_sidecars_for(Path(self.settings.model_path))
        self.threshold = threshold
        self.temperature = temperature

    def _load_sidecars_for(self, source: Path) -> tuple[float, float]:
        threshold: float | None = None
        temperature: float | None = None

        # threshold.json — meragukan derived from confidence < threshold
        threshold_path = source / "threshold.json"
        if threshold_path.exists():
            try:
                data = json.loads(threshold_path.read_text(encoding="utf-8"))
                raw_threshold = data.get("threshold")
                if raw_threshold is not None:
                    parsed_threshold = float(raw_threshold)
                    if not 0.0 < parsed_threshold < 1.0:
                        raise ValueError(f"threshold must be in (0,1), got {parsed_threshold}")
                    threshold = parsed_threshold
                    logger.info("Loaded threshold sidecar: %s", threshold)
                if "accuracy" not in data or "coverage" not in data:
                    logger.warning(
                        "threshold.json missing expected keys (accuracy/coverage): %s", data.keys()
                    )
            except Exception as exc:
                logger.warning("Failed to load threshold.json: %s", exc)
        else:
            logger.warning("Required threshold sidecar not found")

        # calibration.json — temperature scaling
        calibration_path = source / "calibration.json"
        if calibration_path.exists():
            try:
                data = json.loads(calibration_path.read_text(encoding="utf-8"))
                raw_temp = data.get("temperature")
                if raw_temp is not None:
                    parsed_temp = float(raw_temp)
                    if not 0.05 <= parsed_temp <= 20.0:
                        raise ValueError(f"temperature {parsed_temp} out of expected range")
                    temperature = parsed_temp
                    logger.info("Loaded calibration sidecar: T=%s", temperature)
            except Exception as exc:
                logger.warning("Failed to load calibration.json: %s", exc)

        if threshold is None or temperature is None:
            raise ValueError(
                "Valid threshold and calibration sidecars are required for decision authority"
            )
        return threshold, temperature

    def _load_evaluation_metadata(self) -> None:
        if not self.settings.model_path:
            self.evaluation_metadata = {}
            return
        self.evaluation_metadata = self._read_evaluation_metadata(
            Path(self.settings.model_path),
            self.model_version,
        )

    def _read_evaluation_metadata(
        self,
        source: Path,
        version: str | None,
    ) -> dict[str, Any]:
        """Load safe, optional provenance for the active model artifact.

        Evaluation metadata is best-effort. The serving model remains usable
        when an older release has no evaluation sidecar or optional fields are
        malformed. Paths, checkpoints, prompts, and other training internals
        are deliberately excluded from the public /version response.
        """
        evaluation_path = source / "evaluation.json"
        if not evaluation_path.is_file():
            return {}

        try:
            evaluation = json.loads(evaluation_path.read_text(encoding="utf-8"))
        except (OSError, UnicodeError, json.JSONDecodeError) as exc:
            logger.warning("Failed to read optional evaluation metadata: %s", type(exc).__name__)
            return {}

        if not isinstance(evaluation, dict):
            logger.warning("Optional evaluation metadata must be an object")
            return {}

        if not isinstance(version, str) or not version.strip():
            return {}

        entry = self._release_manifest_entry(source, version)
        if entry is False:
            # A manifest exists but does not identify the active version. Do
            # not present metrics from an unrelated artifact as current.
            logger.warning("Evaluation metadata does not match active model version")
            return {}

        reported_version = evaluation.get("model_version")
        if isinstance(reported_version, str) and not self._same_version(reported_version, version):
            logger.warning("Evaluation sidecar model version does not match active model")
            return {}

        def number(name: str) -> float | None:
            value = evaluation.get(name)
            if isinstance(value, bool) or not isinstance(value, (int, float)):
                return None
            try:
                value = float(value)
            except (OverflowError, ValueError):
                return None
            return value if math.isfinite(value) and 0.0 <= value <= 1.0 else None

        sample_count = evaluation.get("sample_count", evaluation.get("n"))
        try:
            sample_count_value = int(sample_count)
        except (OverflowError, TypeError, ValueError):
            sample_count_value = 0
        if (
            isinstance(sample_count, bool)
            or not isinstance(sample_count, (int, float))
            or sample_count_value <= 0
        ):
            sample_count = None
        else:
            sample_count = sample_count_value

        def text(*values: Any) -> str | None:
            for value in values:
                if isinstance(value, str) and value.strip():
                    return value.strip()[:160]
            return None

        manifest_entry = entry if isinstance(entry, dict) else {}
        provenance = (
            manifest_entry.get("provenance")
            if isinstance(manifest_entry.get("provenance"), dict)
            else {}
        )
        accuracy = number("accuracy")
        macro_f1 = number("macro_f1")
        dataset_name = text(evaluation.get("dataset_name"), manifest_entry.get("dataset_name"))
        dataset_version = text(
            evaluation.get("dataset_version"), manifest_entry.get("dataset_version")
        )
        if dataset_name is None or dataset_version is None:
            card_path = source / "MODEL_CARD.md"
            if card_path.is_file():
                try:
                    card = card_path.read_text(encoding="utf-8")
                    match = re.search(
                        r"^[-*] Dataset:\s*([^\r\n]+?)\s+v([0-9]+(?:\.[0-9]+){1,2})\b",
                        card,
                        re.MULTILINE,
                    )
                    if match:
                        dataset_name = dataset_name or match.group(1).strip()
                        dataset_version = dataset_version or f"v{match.group(2).strip()}"
                except (OSError, UnicodeError):
                    pass
        split = text(evaluation.get("split"))
        if split is None and (
            accuracy is not None or macro_f1 is not None or sample_count is not None
        ):
            # evaluate_checkpoint() explicitly evaluates test.jsonl.
            split = "held-out test"

        exported_at = text(
            evaluation.get("exported_at"),
            manifest_entry.get("exported_at"),
            provenance.get("exported_at"),
        )

        return {
            "model_version": version,
            "accuracy": accuracy,
            "macro_f1": macro_f1,
            "sample_count": sample_count,
            "dataset_name": dataset_name,
            "dataset_version": dataset_version,
            "split": split,
            "exported_at": exported_at,
        }

    def _release_manifest_entry(self, source: Path, version: str) -> dict[str, Any] | bool | None:
        candidates = [source / "manifest.json", source.parent / "manifest.json"]
        manifest_path = next((path for path in candidates if path.is_file()), None)
        if manifest_path is None:
            return None

        try:
            manifest = json.loads(manifest_path.read_text(encoding="utf-8"))
        except (OSError, UnicodeError, json.JSONDecodeError) as exc:
            logger.warning("Failed to read optional release metadata: %s", type(exc).__name__)
            return False

        entries = manifest if isinstance(manifest, list) else [manifest]
        for entry in entries:
            if not isinstance(entry, dict):
                continue
            candidate_version = entry.get("version")
            if isinstance(candidate_version, str) and self._same_version(candidate_version, version):
                return entry

        return False

    def _same_version(self, left: str, right: str) -> bool:
        return left.lstrip("vV") == right.lstrip("vV")

    def _verify_checksum_if_available(self) -> None:
        self._verify_checksum_for(
            Path(self.settings.model_path),
            self.model_version or Path(self.settings.model_path).name,
        )

    def _verify_checksum_for(self, source: Path, version: str) -> None:
        manifest_candidates = [
            source / "manifest.json",
            source.parent / "manifest.json",
            Path("models/indobert-hoax/manifest.json"),
        ]
        manifest_path = next((p for p in manifest_candidates if p.exists()), None)
        if manifest_path is None:
            logger.info("No manifest.json found for checksum verification")
            return

        try:
            manifest = json.loads(manifest_path.read_text(encoding="utf-8"))
            entry = None
            if isinstance(manifest, list):
                for candidate in manifest:
                    if not isinstance(candidate, dict):
                        continue
                    cand_ver = str(candidate.get("version", ""))
                    if cand_ver == version or self._same_version(cand_ver, version):
                        entry = candidate
                        break
                if entry is None and manifest:
                    entry = manifest[-1]
            elif isinstance(manifest, dict):
                entry = manifest

            if entry and isinstance(entry.get("files"), dict):
                for rel_path, expected_meta in entry["files"].items():
                    file_path = source / rel_path
                    if not file_path.exists():
                        logger.warning("Manifest expects %s but not found", rel_path)
                        continue
                    if isinstance(expected_meta, dict):
                        expected_sha = expected_meta.get("sha256")
                        expected_size = expected_meta.get("size")
                        if expected_size is not None and file_path.stat().st_size != expected_size:
                            raise ValueError(
                                f"Size mismatch for {rel_path}: expected {expected_size}, got {file_path.stat().st_size}"
                            )
                    else:
                        expected_sha = expected_meta
                    if not isinstance(expected_sha, str):
                        raise ValueError(f"Invalid checksum entry for {rel_path}")
                    actual_sha = _sha256(file_path)
                    if actual_sha != expected_sha.lower():
                        raise ValueError(
                            f"Checksum mismatch for {rel_path}: expected {expected_sha[:8]}..., got {actual_sha[:8]}..."
                        )
                logger.info("Checksum verification passed against %s", manifest_path)
        except Exception as exc:
            raise ValueError(f"Checksum verification failed: {exc}") from exc

    def _derive_model_version(self, source: str) -> str:
        return self._derive_model_version_from(self._model, source)

    def _derive_model_version_from(self, model: Any, source: str) -> str:
        commit_hash = getattr(model.config, "_commit_hash", None)
        if commit_hash:
            return str(commit_hash)
        configured_name = getattr(model.config, "_name_or_path", None)
        if configured_name:
            return str(configured_name)
        return Path(source).name or source

    async def predict(self, text: str) -> Prediction:
        if not self.ready:
            raise RuntimeError("model is not ready")
        async with self._semaphore:
            return await asyncio.to_thread(self._predict_sync, text)

    def _encode(self, text: str) -> Any:
        state = self._state
        lock = state.tokenizer_lock if state is not None and state.tokenizer is self._tokenizer else self._fallback_tokenizer_lock
        with lock:
            return encode_sequence(
                self._tokenizer, text, self.max_sequence_length, return_tensors="pt"
            )

    def _snapshot_for_inference(self) -> LoadedModelState:
        with self._state_lock:
            if (
                self._state is not None
                and self._tokenizer is self._state.tokenizer
                and self._model is self._state.model
                and self._torch is self._state.torch_module
            ):
                return self._state
            if self.status != "ready" or self._model is None or self._tokenizer is None or self._torch is None:
                raise RuntimeError("model is not ready")
            return LoadedModelState(
                model_path=self._active_model_path or (self.settings.model_path or ""),
                model_version=self.model_version or "unknown",
                threshold=self.threshold if self.threshold is not None else 0.0,
                temperature=self.temperature if self.temperature is not None else 1.0,
                evaluation_metadata=dict(self.evaluation_metadata),
                label_map=dict(self.label_map),
                preprocessing_version=self.preprocessing_version,
                max_sequence_length=self.max_sequence_length,
                truncation_strategy=self.truncation_strategy,
                multi_claim_policy=self.multi_claim_policy,
                policy_fingerprint=self.policy_fingerprint or "",
                tokenizer=self._tokenizer,
                model=self._model,
                torch_module=self._torch,
                tokenizer_lock=self._fallback_tokenizer_lock,
            )

    def _predict_sync(self, text: str) -> Prediction:
        started = time.perf_counter()
        state = self._snapshot_for_inference()
        with state.tokenizer_lock:
            full_encoded = state.tokenizer(text, add_special_tokens=True)
            token_count = len(full_encoded["input_ids"])
            truncated = token_count > state.max_sequence_length

            encoded = encode_sequence(
                state.tokenizer, text, state.max_sequence_length, return_tensors="pt"
            )
        with state.torch_module.inference_mode():
            logits = state.model(**encoded).logits[0]
            uncalibrated = state.torch_module.softmax(logits, dim=-1).detach().cpu().tolist()
            # Apply temperature scaling if available (for better calibrated confidence)
            if state.temperature is not None and state.temperature != 1.0:
                logits = logits / state.temperature
            probabilities = state.torch_module.softmax(logits, dim=-1).detach().cpu().tolist()

        labels = self._labels_for(state, len(probabilities))
        raw_scores = {label: float(score) for label, score in zip(labels, probabilities)}
        best_index = max(range(len(probabilities)), key=probabilities.__getitem__)
        best_confidence = float(probabilities[best_index])
        decision = {
            "schema_version": "bert-decision-v1",
            "fingerprint": state.policy_fingerprint,
            "threshold": state.threshold if state.threshold is not None else 0.0,
            **decide(raw_scores, state.threshold if state.threshold is not None else 0.0),
            "uncalibrated_scores": dict(zip(labels, uncalibrated)),
            "calibrated_scores": raw_scores,
        }
        best_label = decision["served_label"]
        return Prediction(
            label=best_label,
            confidence=best_confidence,
            raw_scores=raw_scores,
            inference_ms=round((time.perf_counter() - started) * 1000, 3),
            token_count=token_count,
            truncated=truncated,
            preprocessing_version=state.preprocessing_version,
            decision=decision,
            model_version=state.model_version,
        )

    def _labels(self, count: int) -> list[str]:
        return self._labels_for(self._snapshot_for_inference(), count)

    def _labels_for(self, state: LoadedModelState, count: int) -> list[str]:
        if state.label_map and len(state.label_map) == count:
            return [state.label_map[i] for i in range(count)]
        id2label = getattr(state.model.config, "id2label", {}) or {}
        return [
            str(id2label.get(index, id2label.get(str(index), f"LABEL_{index}")))
            for index in range(count)
        ]


def _sha256(path: Path) -> str:
    digest = hashlib.sha256()
    with path.open("rb") as handle:
        for chunk in iter(lambda: handle.read(1 << 20), b""):
            digest.update(chunk)
    return digest.hexdigest()
