from __future__ import annotations

import asyncio
import hashlib
import json
import logging
import time
from dataclasses import dataclass
from pathlib import Path
from typing import Any

from .config import Settings

logger = logging.getLogger(__name__)

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


class ModelRuntime:
    def __init__(self, settings: Settings) -> None:
        self.settings = settings
        self.status = "not_configured"
        self.load_error: str | None = None
        self.model_version: str | None = settings.model_version
        self.threshold: float | None = None
        self.temperature: float | None = None
        self.label_map: dict[int, str] = {}
        self._tokenizer: Any = None
        self._model: Any = None
        self._torch: Any = None
        self._semaphore = asyncio.Semaphore(settings.max_concurrency)

    @property
    def ready(self) -> bool:
        return self.status == "ready"

    def load(self) -> None:
        if not self.settings.model_path:
            logger.info("BERT_MODEL_PATH not set — model will stay not_configured (readiness 503)")
            return

        self.status = "loading"
        try:
            self._verify_model_directory()
            self._load_model_and_tokenizer()
            self._verify_label_map()
            self._load_sidecars()
            self._verify_checksum_if_available()
            self.status = "ready"
            logger.info(
                "Model ready: %s (version %s, labels %s, threshold %s, temp %s)",
                self.settings.model_path,
                self.model_version,
                self.label_map,
                self.threshold,
                self.temperature,
            )
        except Exception as exc:
            self.status = "failed"
            self.load_error = f"{type(exc).__name__}: {exc}"
            logger.error("Model failed to load: %s", self.load_error)

    def _verify_model_directory(self) -> None:
        source = Path(self.settings.model_path)
        if not source.exists():
            raise FileNotFoundError(f"BERT_MODEL_PATH does not exist: {source}")
        if not source.is_dir():
            raise NotADirectoryError(f"BERT_MODEL_PATH is not a directory: {source}")
        # Required files for a HuggingFace sequence-classification model
        has_weights = (source / "model.safetensors").exists() or (source / "pytorch_model.bin").exists()
        if not has_weights:
            raise FileNotFoundError(f"Model weights not found in {source} (expected model.safetensors or pytorch_model.bin)")
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
        import torch
        from transformers import AutoModelForSequenceClassification, AutoTokenizer

        source = self.settings.model_path
        self._tokenizer = AutoTokenizer.from_pretrained(
            source, local_files_only=self.settings.local_files_only
        )
        self._model = AutoModelForSequenceClassification.from_pretrained(
            source, local_files_only=self.settings.local_files_only
        )
        self._model.eval()
        self._torch = torch
        self.model_version = self.model_version or self._derive_model_version(source)

    def _verify_label_map(self) -> None:
        config = self._model.config
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
            raise ValueError(f"Model num_labels={num_labels} does not match expected {len(EXPECTED_LABELS)} for valid/hoax")

        actual_labels = set(id2label.values())
        if actual_labels != EXPECTED_LABELS:
            raise ValueError(f"Model id2label must be exactly {EXPECTED_LABELS}, got {actual_labels} (raw: {id2label_raw})")

        # Verify id2label mapping is exactly {0: valid, 1: hoax}
        if id2label != EXPECTED_ID2LABEL:
            # Allow swapped? No — must be exactly this mapping
            raise ValueError(f"Model id2label must be {EXPECTED_ID2LABEL}, got {id2label}")

        # Verify label2id is the inverse
        label2id: dict[str, int] = {str(k): int(v) for k, v in label2id_raw.items()}
        if set(label2id.keys()) != EXPECTED_LABELS:
            raise ValueError(f"Model label2id keys must be {EXPECTED_LABELS}, got {set(label2id.keys())}")
        for label, idx in EXPECTED_LABEL2ID.items():
            if label2id.get(label) != idx:
                raise ValueError(f"Model label2id[{label!r}] must be {idx}, got {label2id.get(label)}")

        self.label_map = id2label
        logger.info("Label map verified: %s", id2label)

    def _load_sidecars(self) -> None:
        source = Path(self.settings.model_path)
        # threshold.json — meragukan derived from confidence < threshold
        threshold_path = source / "threshold.json"
        if threshold_path.exists():
            try:
                data = json.loads(threshold_path.read_text(encoding="utf-8"))
                threshold = data.get("threshold")
                if threshold is not None:
                    threshold = float(threshold)
                    if not 0.0 < threshold < 1.0:
                        raise ValueError(f"threshold must be in (0,1), got {threshold}")
                    self.threshold = threshold
                    logger.info("Loaded threshold sidecar: %s", threshold)
                # Validate structure
                if "accuracy" not in data or "coverage" not in data:
                    logger.warning("threshold.json missing expected keys (accuracy/coverage): %s", data.keys())
            except Exception as exc:
                logger.warning("Failed to load threshold.json: %s", exc)
        else:
            logger.info("threshold.json not found in %s — meragukan will be handled at Laravel layer", source)

        # calibration.json — temperature scaling
        calibration_path = source / "calibration.json"
        if calibration_path.exists():
            try:
                data = json.loads(calibration_path.read_text(encoding="utf-8"))
                temp = data.get("temperature")
                if temp is not None:
                    temp = float(temp)
                    if not 0.05 <= temp <= 20.0:
                        raise ValueError(f"temperature {temp} out of expected range")
                    self.temperature = temp
                    logger.info("Loaded calibration sidecar: T=%s", temp)
            except Exception as exc:
                logger.warning("Failed to load calibration.json: %s", exc)

    def _verify_checksum_if_available(self) -> None:
        # Verify model files against manifest.json if present (either in model dir or parent)
        source = Path(self.settings.model_path)
        manifest_candidates = [
            source.parent / "manifest.json",
            source / "manifest.json",
            Path("models/indobert-hoax/manifest.json"),
        ]
        manifest_path = next((p for p in manifest_candidates if p.exists()), None)
        if manifest_path is None:
            logger.info("No manifest.json found for checksum verification")
            return

        try:
            manifest = json.loads(manifest_path.read_text(encoding="utf-8"))
            # Manifest is a list of entries; find matching version
            version = self.model_version or Path(source).name
            # Try to find entry matching version or model_version
            entry = None
            if isinstance(manifest, list):
                for e in manifest:
                    if e.get("version") == version or e.get("version") == version.lstrip("v"):
                        entry = e
                        break
                if entry is None and manifest:
                    # Fall back to latest entry
                    entry = manifest[-1]
            elif isinstance(manifest, dict):
                entry = manifest

            if entry and "files" in entry:
                for rel_path, expected_sha in entry["files"].items():
                    file_path = source / rel_path
                    if not file_path.exists():
                        logger.warning("Manifest expects %s but not found", rel_path)
                        continue
                    actual_sha = _sha256(file_path)
                    if actual_sha != expected_sha:
                        raise ValueError(f"Checksum mismatch for {rel_path}: expected {expected_sha[:8]}..., got {actual_sha[:8]}...")
                logger.info("Checksum verification passed against %s", manifest_path)
        except Exception as exc:
            # Checksum failure is a hard error for production, but we log and fail load
            raise ValueError(f"Checksum verification failed: {exc}") from exc

    def _derive_model_version(self, source: str) -> str:
        commit_hash = getattr(self._model.config, "_commit_hash", None)
        if commit_hash:
            return str(commit_hash)
        configured_name = getattr(self._model.config, "_name_or_path", None)
        if configured_name:
            return str(configured_name)
        return Path(source).name or source

    async def predict(self, text: str) -> Prediction:
        if not self.ready:
            raise RuntimeError("model is not ready")
        async with self._semaphore:
            return await asyncio.to_thread(self._predict_sync, text)

    def _predict_sync(self, text: str) -> Prediction:
        started = time.perf_counter()
        encoded = self._tokenizer(
            text,
            return_tensors="pt",
            truncation=True,
            max_length=self.settings.max_sequence_length,
        )
        with self._torch.inference_mode():
            logits = self._model(**encoded).logits[0]
            # Apply temperature scaling if available (for better calibrated confidence)
            if self.temperature is not None and self.temperature != 1.0:
                logits = logits / self.temperature
            probabilities = self._torch.softmax(logits, dim=-1).detach().cpu().tolist()

        labels = self._labels(len(probabilities))
        raw_scores = {label: float(score) for label, score in zip(labels, probabilities)}
        best_index = max(range(len(probabilities)), key=probabilities.__getitem__)
        best_label = labels[best_index]
        best_confidence = float(probabilities[best_index])
        # Apply meragukan threshold if available — per exported MODEL_CARD contract:
        # label = id2label[argmax] if confidence >= threshold else "meragukan"
        if self.threshold is not None and best_confidence < self.threshold:
            best_label = "meragukan"
        return Prediction(
            label=best_label,
            confidence=best_confidence,
            raw_scores=raw_scores,
            inference_ms=round((time.perf_counter() - started) * 1000, 3),
        )

    def _labels(self, count: int) -> list[str]:
        if self.label_map and len(self.label_map) == count:
            return [self.label_map[i] for i in range(count)]
        id2label = getattr(self._model.config, "id2label", {}) or {}
        return [str(id2label.get(index, id2label.get(str(index), f"LABEL_{index}"))) for index in range(count)]


def _sha256(path: Path) -> str:
    digest = hashlib.sha256()
    with path.open("rb") as handle:
        for chunk in iter(lambda: handle.read(1 << 20), b""):
            digest.update(chunk)
    return digest.hexdigest()
