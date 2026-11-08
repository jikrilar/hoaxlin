"""Incremental layer-freezing fine-tuning and atomic release export."""

from __future__ import annotations

import csv
import gc
import json
import logging
import math
import os
import random
import shutil
import tempfile
import time
from dataclasses import dataclass
from datetime import datetime, timezone
from pathlib import Path
from typing import Any

import numpy as np

from .config import Settings
from .contracts import validate_version_name
from .model_release import (
    ARCHITECTURE,
    CRITICAL_FILES,
    EXPECTED_ID2LABEL,
    EXPECTED_LABEL2ID,
    EXPECTED_LABELS,
    EXPECTED_NUM_LABELS,
    MODEL_IDENTIFIER,
    TASK,
    sha256,
    validate_incremental_release,
)
from .preprocessing import encode_sequence

logger = logging.getLogger("uvicorn.error")

VALID_LABEL_ALIASES = frozenset({"valid", "0", "true", "benar", "fakta", "fact", "info"})
HOAX_LABEL_ALIASES = frozenset(
    {
        "hoax",
        "hoaks",
        "hoaks:",
        "hoax:",
        "1",
        "false",
        "salah",
        "keliru",
        "misinformasi",
        "disinformasi",
        "penipuan",
    }
)
ABSTAIN_LABELS = frozenset({"meragukan", "uncertain"})
MAX_REPLAY_SAMPLES = 500
MIN_REPLAY_TARGET = 50


class TrainingError(RuntimeError):
    """Raised when incremental fine-tuning or artifact export fails."""


class TrainingDataError(TrainingError):
    """Raised when training or replay CSV inputs are invalid."""


@dataclass(frozen=True, slots=True)
class TrainingSample:
    text: str
    label_id: int


@dataclass(frozen=True, slots=True)
class TrainingResult:
    version_name: str
    output_dir: Path
    accuracy: float
    f1_score: float
    training_duration: int
    new_sample_count: int
    replay_sample_count: int
    train_sample_count: int
    eval_sample_count: int
    epochs: int
    trainable_params: int
    frozen_params: int


def normalize_binary_label(raw_label: Any) -> int:
    if not isinstance(raw_label, str):
        raw_text = str(raw_label if raw_label is not None else "")
    else:
        raw_text = raw_label
    normalized = raw_text.strip().lower()
    if normalized in ABSTAIN_LABELS:
        raise TrainingDataError(
            "Label 'meragukan' is derived from confidence threshold and cannot be trained; use 'valid' or 'hoax'"
        )
    if normalized in VALID_LABEL_ALIASES:
        return 0
    if normalized in HOAX_LABEL_ALIASES:
        return 1
    raise TrainingDataError(
        f"Unsupported label {raw_text.strip()!r}; expected 'valid' or 'hoax'"
    )


def load_csv_samples(csv_path: Path) -> list[TrainingSample]:
    if not csv_path.is_file():
        raise TrainingDataError(f"Training CSV file does not exist: {csv_path}")

    samples: list[TrainingSample] = []
    try:
        with csv_path.open("r", encoding="utf-8-sig", newline="") as handle:
            reader = csv.DictReader(handle)
            if not reader.fieldnames:
                raise TrainingDataError("Training CSV is missing a header row")

            header_map: dict[str, str] = {}
            for original_name in reader.fieldnames:
                key = original_name.strip().lower()
                if key and key not in header_map:
                    header_map[key] = original_name

            if "text" not in header_map or "label" not in header_map:
                raise TrainingDataError(
                    "Training CSV must contain 'text' and 'label' columns"
                )

            text_col = header_map["text"]
            label_col = header_map["label"]

            for row_index, row in enumerate(reader, start=2):
                text = str(row.get(text_col) or "").strip()
                raw_label = str(row.get(label_col) or "").strip()
                if not text and not raw_label:
                    continue
                if not text:
                    raise TrainingDataError(
                        f"Row {row_index} has a label but empty text"
                    )
                if not raw_label:
                    raise TrainingDataError(
                        f"Row {row_index} has text but empty label"
                    )
                try:
                    label_id = normalize_binary_label(raw_label)
                except TrainingDataError as exc:
                    raise TrainingDataError(f"Row {row_index}: {exc}") from exc
                samples.append(TrainingSample(text=text, label_id=label_id))
    except UnicodeDecodeError as exc:
        raise TrainingDataError("Training CSV must be valid UTF-8") from exc
    except csv.Error as exc:
        raise TrainingDataError(f"Invalid CSV formatting: {exc}") from exc

    if not samples:
        raise TrainingDataError("Training CSV contains no usable training rows")
    return samples


def resolve_replay_buffer_path(settings: Settings) -> Path | None:
    candidates: list[Path] = []
    if settings.replay_buffer_path:
        candidates.append(Path(settings.replay_buffer_path))
    else:
        candidates.append(Path(settings.datasets_dir) / "replay_buffer.csv")
        candidates.append(Path(settings.models_dir) / "replay_buffer.csv")

    for candidate in candidates:
        if candidate.is_file():
            return candidate
    return None


def sample_replay_buffer(
    replay_path: Path,
    *,
    target_count: int,
    seed: int,
    exclude_texts: set[str] | None = None,
) -> list[TrainingSample]:
    if target_count <= 0 or not replay_path.is_file():
        return []

    try:
        all_rows = load_csv_samples(replay_path)
    except TrainingDataError as exc:
        logger.warning("Skipping invalid replay buffer %s: %s", replay_path, exc)
        return []

    excluded = exclude_texts or set()
    pool = [row for row in all_rows if row.text not in excluded]
    if not pool:
        return []

    if len(pool) <= target_count:
        return pool

    rng = random.Random(seed)
    by_class: dict[int, list[TrainingSample]] = {0: [], 1: []}
    for row in pool:
        by_class[row.label_id].append(row)

    selected: list[TrainingSample] = []
    if by_class[0] and by_class[1]:
        half = target_count // 2
        take_0 = min(len(by_class[0]), half)
        take_1 = min(len(by_class[1]), target_count - take_0)
        if take_0 + take_1 < target_count:
            take_0 = min(len(by_class[0]), target_count - take_1)
        selected.extend(rng.sample(by_class[0], take_0))
        selected.extend(rng.sample(by_class[1], take_1))
    else:
        selected.extend(rng.sample(pool, target_count))

    rng.shuffle(selected)
    return selected


def merge_and_split_samples(
    new_samples: list[TrainingSample],
    replay_samples: list[TrainingSample],
    *,
    seed: int,
) -> tuple[list[TrainingSample], list[TrainingSample], str]:
    merged = list(new_samples) + list(replay_samples)
    if len(merged) < 2:
        raise TrainingDataError(
            "At least 2 training samples are required after merging replay buffer"
        )

    present_labels = {sample.label_id for sample in merged}
    if present_labels != {0, 1}:
        raise TrainingDataError(
            "Combined training samples must include both 'valid' and 'hoax' labels"
        )

    rng = random.Random(seed)
    by_class: dict[int, list[TrainingSample]] = {
        0: [s for s in merged if s.label_id == 0],
        1: [s for s in merged if s.label_id == 1],
    }

    if len(merged) >= 10 and len(by_class[0]) >= 2 and len(by_class[1]) >= 2:
        train_samples: list[TrainingSample] = []
        val_samples: list[TrainingSample] = []
        for label_id in (0, 1):
            cls_items = list(by_class[label_id])
            rng.shuffle(cls_items)
            n_val = max(1, round(0.2 * len(cls_items)))
            n_val = min(n_val, len(cls_items) - 1)
            val_samples.extend(cls_items[:n_val])
            train_samples.extend(cls_items[n_val:])
        rng.shuffle(train_samples)
        rng.shuffle(val_samples)
        return train_samples, val_samples, "validation (20%)"

    train_samples = list(merged)
    val_samples = list(merged)
    rng.shuffle(train_samples)
    return train_samples, val_samples, "train-fallback"


def freeze_bert_layers(model: Any) -> dict[str, int]:
    """Freeze embeddings, earlier encoder layers, and pooler; train only layer[-1] and classifier."""
    bert = getattr(model, "bert", None)
    encoder = getattr(bert, "encoder", None)
    layers = getattr(encoder, "layer", None)
    classifier = getattr(model, "classifier", None)

    if layers is None or len(layers) == 0 or classifier is None:
        raise TrainingError(
            "Expected BertForSequenceClassification with bert.encoder.layer and classifier"
        )

    for param in model.parameters():
        param.requires_grad = False

    for param in layers[-1].parameters():
        param.requires_grad = True

    for param in classifier.parameters():
        param.requires_grad = True

    total_params = sum(p.numel() for p in model.parameters())
    trainable_params = sum(p.numel() for p in model.parameters() if p.requires_grad)
    frozen_params = total_params - trainable_params
    return {
        "total_params": total_params,
        "trainable_params": trainable_params,
        "frozen_params": frozen_params,
        "num_encoder_layers": len(layers),
        "trainable_layer_index": len(layers) - 1,
    }


def compute_classification_metrics(
    y_true: np.ndarray,
    y_pred: np.ndarray,
) -> dict[str, Any]:
    y_true = np.asarray(y_true, dtype=np.int64)
    y_pred = np.asarray(y_pred, dtype=np.int64)
    if y_true.size == 0 or y_true.shape != y_pred.shape:
        raise TrainingError("Invalid prediction arrays for metric computation")

    n_classes = EXPECTED_NUM_LABELS
    matrix = np.zeros((n_classes, n_classes), dtype=np.int64)
    for true_cls, pred_cls in zip(y_true.tolist(), y_pred.tolist()):
        if 0 <= true_cls < n_classes and 0 <= pred_cls < n_classes:
            matrix[true_cls, pred_cls] += 1

    precision: list[float] = []
    recall: list[float] = []
    f1: list[float] = []
    for cls_idx in range(n_classes):
        tp = int(matrix[cls_idx, cls_idx])
        fp = int(matrix[:, cls_idx].sum() - tp)
        fn = int(matrix[cls_idx, :].sum() - tp)
        prec = float(tp / (tp + fp)) if (tp + fp) > 0 else 0.0
        rec = float(tp / (tp + fn)) if (tp + fn) > 0 else 0.0
        cls_f1 = (2.0 * prec * rec / (prec + rec)) if (prec + rec) > 0 else 0.0
        precision.append(prec)
        recall.append(rec)
        f1.append(cls_f1)

    accuracy = float(np.mean(y_true == y_pred))
    return {
        "accuracy": accuracy,
        "precision": precision,
        "recall": recall,
        "f1": f1,
        "macro_precision": float(np.mean(precision)),
        "macro_recall": float(np.mean(recall)),
        "macro_f1": float(np.mean(f1)),
        "confusion_matrix": matrix.tolist(),
    }


def _verify_base_model_labels(model: Any) -> None:
    config = model.config
    num_labels = getattr(config, "num_labels", None)
    if num_labels != EXPECTED_NUM_LABELS:
        raise TrainingError(
            f"Base model num_labels={num_labels} does not match expected {EXPECTED_NUM_LABELS}"
        )
    id2label_raw = getattr(config, "id2label", {}) or {}
    label2id_raw = getattr(config, "label2id", {}) or {}
    try:
        id2label = {int(k): str(v) for k, v in id2label_raw.items()}
        label2id = {str(k): int(v) for k, v in label2id_raw.items()}
    except (TypeError, ValueError) as exc:
        raise TrainingError("Base model config has invalid label mappings") from exc
    if id2label != EXPECTED_ID2LABEL or label2id != EXPECTED_LABEL2ID:
        raise TrainingError(
            f"Base model labels must be {EXPECTED_ID2LABEL}, got id2label={id2label}"
        )


def _read_base_sidecars(base_model_path: Path, eval_accuracy: float) -> tuple[dict[str, Any], dict[str, Any]]:
    threshold_data: dict[str, Any] = {
        "threshold": 0.99,
        "accuracy": eval_accuracy,
        "coverage": 1.0,
    }
    threshold_path = base_model_path / "threshold.json"
    if threshold_path.is_file():
        try:
            loaded = json.loads(threshold_path.read_text(encoding="utf-8"))
            raw_threshold = loaded.get("threshold") if isinstance(loaded, dict) else None
            if (
                isinstance(raw_threshold, (int, float))
                and not isinstance(raw_threshold, bool)
                and math.isfinite(float(raw_threshold))
                and 0.0 < float(raw_threshold) < 1.0
            ):
                threshold_data = dict(loaded)
                threshold_data["threshold"] = float(raw_threshold)
                threshold_data.setdefault("accuracy", eval_accuracy)
                threshold_data.setdefault("coverage", 1.0)
        except (OSError, UnicodeError, json.JSONDecodeError):
            pass

    calibration_data: dict[str, Any] = {
        "temperature": 1.0,
        "method": "temperature_scaling",
    }
    calibration_path = base_model_path / "calibration.json"
    if calibration_path.is_file():
        try:
            loaded = json.loads(calibration_path.read_text(encoding="utf-8"))
            raw_temp = loaded.get("temperature") if isinstance(loaded, dict) else None
            if (
                isinstance(raw_temp, (int, float))
                and not isinstance(raw_temp, bool)
                and math.isfinite(float(raw_temp))
                and 0.05 <= float(raw_temp) <= 20.0
            ):
                calibration_data = {
                    "temperature": float(raw_temp),
                    "method": "temperature_scaling",
                }
        except (OSError, UnicodeError, json.JSONDecodeError):
            pass

    return threshold_data, calibration_data


def _tokenize_batches(
    tokenizer: Any,
    samples: list[TrainingSample],
    *,
    max_length: int,
    batch_size: int,
    torch_module: Any,
) -> list[dict[str, Any]]:
    encoded_items = [
        encode_sequence(tokenizer, sample.text, max_length)
        for sample in samples
    ]
    batches: list[dict[str, Any]] = []
    for start in range(0, len(samples), batch_size):
        slice_items = encoded_items[start : start + batch_size]
        slice_labels = [sample.label_id for sample in samples[start : start + batch_size]]
        padded = tokenizer.pad(slice_items, padding=True, return_tensors="pt")
        batch_dict = {key: value for key, value in padded.items()}
        batch_dict["labels"] = torch_module.tensor(slice_labels, dtype=torch_module.long)
        batches.append(batch_dict)
    return batches


def train_incremental_model(
    *,
    csv_path: Path,
    version_name: str,
    base_model_path: Path,
    settings: Settings,
    base_version: str | None = None,
    max_sequence_length: int | None = None,
) -> TrainingResult:
    """Fine-tune an isolated copy of IndoBERT with encoder layer freezing and save atomically."""
    cleaned_version = validate_version_name(version_name, allow_baseline=False)
    models_root = Path(settings.models_dir)
    models_root.mkdir(parents=True, exist_ok=True)
    target_dir = models_root / cleaned_version
    if target_dir.exists():
        raise TrainingError(f"Model version directory already exists: {cleaned_version}")
    if not base_model_path.is_dir():
        raise TrainingError(f"Base model directory does not exist: {base_model_path}")

    new_samples = load_csv_samples(csv_path)
    replay_path = resolve_replay_buffer_path(settings)
    replay_samples: list[TrainingSample] = []
    if replay_path is not None:
        replay_target = min(MAX_REPLAY_SAMPLES, max(len(new_samples), MIN_REPLAY_TARGET))
        replay_samples = sample_replay_buffer(
            replay_path,
            target_count=replay_target,
            seed=settings.train_seed,
            exclude_texts={sample.text for sample in new_samples},
        )

    train_samples, val_samples, split_name = merge_and_split_samples(
        new_samples,
        replay_samples,
        seed=settings.train_seed,
    )

    epochs = max(2, min(3, int(settings.train_epochs)))
    batch_size = max(1, min(int(settings.train_batch_size), len(train_samples)))
    seq_len = max(2, min(512, int(max_sequence_length or settings.max_sequence_length)))

    import torch
    from transformers import (
        AutoModelForSequenceClassification,
        AutoTokenizer,
        get_linear_schedule_with_warmup,
    )

    started = time.perf_counter()
    staging_dir: Path | None = None
    model = None
    optimizer = None
    scheduler = None

    try:
        random.seed(settings.train_seed)
        np.random.seed(settings.train_seed)
        torch.manual_seed(settings.train_seed)

        tokenizer = AutoTokenizer.from_pretrained(
            str(base_model_path),
            local_files_only=settings.local_files_only,
        )
        if tokenizer.pad_token_id is None and getattr(tokenizer, "eos_token", None) is not None:
            tokenizer.pad_token = tokenizer.eos_token

        model = AutoModelForSequenceClassification.from_pretrained(
            str(base_model_path),
            local_files_only=settings.local_files_only,
        )
        _verify_base_model_labels(model)
        freeze_stats = freeze_bert_layers(model)

        trainable_params = [param for param in model.parameters() if param.requires_grad]
        optimizer = torch.optim.AdamW(
            trainable_params,
            lr=settings.train_learning_rate,
            weight_decay=0.01,
        )
        steps_per_epoch = max(1, math.ceil(len(train_samples) / batch_size))
        total_steps = steps_per_epoch * epochs
        warmup_steps = max(0, int(0.1 * total_steps))
        scheduler = get_linear_schedule_with_warmup(
            optimizer,
            num_warmup_steps=warmup_steps,
            num_training_steps=total_steps,
        )

        logger.info(
            "Starting incremental fine-tuning: version=%s base=%s epochs=%s batch_size=%s "
            "new_samples=%s replay_samples=%s train=%s eval=%s trainable_params=%s/%s",
            cleaned_version,
            base_version or base_model_path.name,
            epochs,
            batch_size,
            len(new_samples),
            len(replay_samples),
            len(train_samples),
            len(val_samples),
            freeze_stats["trainable_params"],
            freeze_stats["total_params"],
        )

        rng = random.Random(settings.train_seed)
        model.train()
        for _epoch in range(epochs):
            epoch_samples = list(train_samples)
            rng.shuffle(epoch_samples)
            batches = _tokenize_batches(
                tokenizer,
                epoch_samples,
                max_length=seq_len,
                batch_size=batch_size,
                torch_module=torch,
            )
            for batch in batches:
                optimizer.zero_grad(set_to_none=True)
                outputs = model(**batch)
                loss = outputs.loss
                loss.backward()
                torch.nn.utils.clip_grad_norm_(trainable_params, max_norm=1.0)
                optimizer.step()
                scheduler.step()

        model.eval()
        eval_batches = _tokenize_batches(
            tokenizer,
            val_samples,
            max_length=seq_len,
            batch_size=max(1, min(32, len(val_samples))),
            torch_module=torch,
        )
        all_preds: list[int] = []
        with torch.inference_mode():
            for batch in eval_batches:
                logits = model(
                    input_ids=batch["input_ids"],
                    attention_mask=batch["attention_mask"],
                    **(
                        {"token_type_ids": batch["token_type_ids"]}
                        if "token_type_ids" in batch
                        else {}
                    ),
                ).logits
                preds = torch.argmax(logits, dim=-1).detach().cpu().tolist()
                all_preds.extend(int(item) for item in preds)

        y_true = np.asarray([sample.label_id for sample in val_samples], dtype=np.int64)
        y_pred = np.asarray(all_preds, dtype=np.int64)
        metrics = compute_classification_metrics(y_true, y_pred)
        accuracy = float(metrics["accuracy"])
        f1_score = float(metrics["macro_f1"])

        exported_at = datetime.now(timezone.utc).isoformat()
        safe_base_version = (base_version or base_model_path.name).lstrip("/")
        if "/" in safe_base_version or "\\" in safe_base_version:
            safe_base_version = Path(safe_base_version).name

        staging_dir = Path(
            tempfile.mkdtemp(prefix=f".staging-{cleaned_version}-", dir=models_root)
        )
        os.chmod(staging_dir, 0o755)

        model.config.architectures = [ARCHITECTURE]
        model.config.num_labels = EXPECTED_NUM_LABELS
        model.config.id2label = {str(k): v for k, v in EXPECTED_ID2LABEL.items()}
        model.config.label2id = dict(EXPECTED_LABEL2ID)
        model.save_pretrained(staging_dir, safe_serialization=True)

        cfg_path = staging_dir / "config.json"
        cfg = json.loads(cfg_path.read_text(encoding="utf-8"))
        cfg["architectures"] = [ARCHITECTURE]
        cfg["num_labels"] = EXPECTED_NUM_LABELS
        cfg["id2label"] = {str(k): v for k, v in EXPECTED_ID2LABEL.items()}
        cfg["label2id"] = dict(EXPECTED_LABEL2ID)
        cfg_path.write_text(json.dumps(cfg, indent=2, ensure_ascii=False), encoding="utf-8")

        tokenizer.save_pretrained(staging_dir)

        threshold_data, calibration_data = _read_base_sidecars(base_model_path, accuracy)
        (staging_dir / "threshold.json").write_text(
            json.dumps(threshold_data, indent=2, ensure_ascii=False),
            encoding="utf-8",
        )
        (staging_dir / "calibration.json").write_text(
            json.dumps(calibration_data, ensure_ascii=False),
            encoding="utf-8",
        )

        evaluation_payload = {
            "model_version": cleaned_version,
            "n": len(val_samples),
            "sample_count": len(val_samples),
            "class_names": list(EXPECTED_LABELS),
            "accuracy": accuracy,
            "macro_precision": metrics["macro_precision"],
            "macro_recall": metrics["macro_recall"],
            "macro_f1": f1_score,
            "per_class": {
                "valid": {
                    "precision": metrics["precision"][0],
                    "recall": metrics["recall"][0],
                    "f1": metrics["f1"][0],
                },
                "hoax": {
                    "precision": metrics["precision"][1],
                    "recall": metrics["recall"][1],
                    "f1": metrics["f1"][1],
                },
            },
            "confusion_matrix": metrics["confusion_matrix"],
            "dataset_name": "incremental-finetune",
            "dataset_version": cleaned_version,
            "split": split_name,
            "exported_at": exported_at,
            "training_summary": {
                "base_version": safe_base_version,
                "epochs": epochs,
                "new_samples": len(new_samples),
                "replay_samples": len(replay_samples),
                "train_samples": len(train_samples),
                "eval_samples": len(val_samples),
                "trainable_params": freeze_stats["trainable_params"],
                "frozen_params": freeze_stats["frozen_params"],
            },
        }
        (staging_dir / "evaluation.json").write_text(
            json.dumps(evaluation_payload, indent=2, ensure_ascii=False),
            encoding="utf-8",
        )

        (staging_dir / "MODEL_CARD.md").write_text(
            (
                f"# {MODEL_IDENTIFIER} {cleaned_version}\n\n"
                f"Incrementally fine-tuned from `{safe_base_version}` with frozen encoder layers "
                f"(trainable `bert.encoder.layer[-1]` and `classifier` head).\n\n"
                f"- Labels: {EXPECTED_LABELS} (id2label {EXPECTED_ID2LABEL})\n"
                f"- Epochs: {epochs}\n"
                f"- Samples: {len(new_samples)} new + {len(replay_samples)} replay\n"
                f"- Evaluation ({split_name}, n={len(val_samples)}): "
                f"accuracy={accuracy:.4f}, macro_f1={f1_score:.4f}\n"
            ),
            encoding="utf-8",
        )

        file_entries = {
            path.name: {
                "sha256": sha256(path),
                "size": path.stat().st_size,
            }
            for path in sorted(staging_dir.iterdir(), key=lambda p: p.name)
            if path.is_file() and path.name != "manifest.json"
        }
        for missing_name in CRITICAL_FILES:
            if missing_name not in file_entries:
                raise TrainingError(
                    f"Exported model artifact is missing critical file: {missing_name}"
                )

        manifest_payload = {
            "schema_version": "1.1",
            "kind": "incremental-finetune",
            "model": MODEL_IDENTIFIER,
            "version": cleaned_version,
            "task": TASK,
            "architecture": ARCHITECTURE,
            "labels": list(EXPECTED_LABELS),
            "num_labels": EXPECTED_NUM_LABELS,
            "local_files_only": True,
            "threshold": threshold_data["threshold"],
            "calibration": {
                "method": "temperature_scaling",
                "temperature": calibration_data["temperature"],
            },
            "preprocessing_version": "1.0.0",
            "max_sequence_length": seq_len,
            "max_token_length": seq_len,
            "truncation_strategy": "head-only",
            "multi_claim_policy": "single-sequence-head-only",
            "tokenizer_hash": file_entries["tokenizer.json"]["sha256"],
            "tokenizer_config_hash": file_entries["tokenizer_config.json"]["sha256"],
            "critical_files": list(CRITICAL_FILES),
            "files": file_entries,
            "provenance": {
                "kind": "incremental-finetune",
                "base_version": safe_base_version,
                "exported_at": exported_at,
                "epochs": epochs,
                "learning_rate": settings.train_learning_rate,
                "batch_size": batch_size,
                "seed": settings.train_seed,
                "new_samples": len(new_samples),
                "replay_samples": len(replay_samples),
                "train_samples": len(train_samples),
                "eval_samples": len(val_samples),
            },
        }
        (staging_dir / "manifest.json").write_text(
            json.dumps(manifest_payload, indent=2, ensure_ascii=False),
            encoding="utf-8",
        )

        for item in staging_dir.iterdir():
            if item.is_file():
                os.chmod(item, 0o644)

        validate_incremental_release(staging_dir, expected_version=cleaned_version)

        if target_dir.exists():
            raise TrainingError(f"Model version directory already exists: {cleaned_version}")
        staging_dir.rename(target_dir)
        staging_dir = None

        duration = max(0, int(round(time.perf_counter() - started)))
        logger.info(
            "Incremental fine-tuning completed: version=%s duration=%ss accuracy=%.4f f1=%.4f",
            cleaned_version,
            duration,
            accuracy,
            f1_score,
        )
        return TrainingResult(
            version_name=cleaned_version,
            output_dir=target_dir,
            accuracy=accuracy,
            f1_score=f1_score,
            training_duration=duration,
            new_sample_count=len(new_samples),
            replay_sample_count=len(replay_samples),
            train_sample_count=len(train_samples),
            eval_sample_count=len(val_samples),
            epochs=epochs,
            trainable_params=freeze_stats["trainable_params"],
            frozen_params=freeze_stats["frozen_params"],
        )
    finally:
        if staging_dir is not None and staging_dir.exists():
            shutil.rmtree(staging_dir, ignore_errors=True)
        del scheduler, optimizer, model
        gc.collect()
