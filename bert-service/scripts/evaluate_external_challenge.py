"""Evaluate the frozen external challenge set with the released model only.

This module deliberately has no training, calibration, threshold-search, or
model-export path.  The dataset and its manifest are validated before the
model is loaded, and all reported predictions are produced by the same
``ModelRuntime`` used by the inference service.
"""

from __future__ import annotations

import argparse
import asyncio
import csv
import hashlib
import json
import math
import re
import sys
from collections import Counter, defaultdict
from dataclasses import dataclass
from datetime import datetime, timezone
from pathlib import Path
from typing import Any, Iterable

BERT_ROOT = Path(__file__).resolve().parents[1]
PROJECT_ROOT = BERT_ROOT.parent
if str(BERT_ROOT) not in sys.path:
    sys.path.insert(0, str(BERT_ROOT))

from app.config import Settings  # noqa: E402
from app.inference import EXPECTED_ID2LABEL, ModelRuntime  # noqa: E402
from dataset.normalize import normalize_key, tokenize_norm, word_shingles  # noqa: E402

EXPECTED_DATASET_NAME = "external-challenge"
EXPECTED_DATASET_VERSION = "1.0.0"
EXPECTED_MODEL_NAME = "indobert-hoax"
EXPECTED_MODEL_VERSION = "v1.0.0"
SERVING_THRESHOLD = 0.99
SIMILARITY_THRESHOLD = 0.65
LABEL_ORDER = ("valid", "hoax")
CORPUS_PATH = PROJECT_ROOT / "datasets" / "processed" / "komdigi-antara-v1" / "all.jsonl"
EXPECTED_SOURCES = (
    "TurnBackHoax / MAFINDO",
    "CekFakta",
    "AFP Fact Check Indonesia",
    "Bank Indonesia",
    "BMKG",
    "Kementerian Kesehatan RI",
)
REQUIRED_DATASET_FIELDS = (
    "id",
    "text",
    "label",
    "text_source",
    "evidence_source",
    "evidence_url",
    "published_at",
    "topic",
    "verification_method",
    "notes",
    "approved",
)
PREDICTION_FIELDS = (
    "id",
    "text",
    "true_label",
    "predicted_binary_label",
    "prob_valid",
    "prob_hoax",
    "confidence",
    "correct",
    "served_label",
    "abstained",
    "text_source",
    "evidence_source",
    "evidence_url",
    "published_at",
    "topic",
    "verification_method",
    "notes",
    "approved",
)
VERDICT_LEAK_RE = re.compile(
    r"(?:\bfaktanya\b|\bhasil (?:cek fakta|penelusuran)\b|"
    r"\bberdasarkan penelusuran\b|\brating\s*:|\bkesimpulan\s*:|"
    r"\b(?:klaim|narasi|informasi)\s+(?:ini\s+)?(?:hoaks?|hoax|false|keliru|tidak benar|salah)\b|"
    r"\b(?:merupakan|adalah)\s+(?:hoaks?|hoax|false|keliru|tidak benar)\b|"
    r"^\s*(?:\[(?:salah|hoaks?|hoax|false|keliru|tidak benar)\]|"
    r"(?:salah|hoaks?|hoax|false|keliru|tidak benar)\s*[:!,-]))",
    re.IGNORECASE,
)


class EvaluationError(RuntimeError):
    """Raised when a frozen dataset or model contract is not satisfied."""


@dataclass(frozen=True, slots=True)
class FrozenDataset:
    path: Path
    manifest_path: Path
    manifest: dict[str, Any]
    fields: tuple[str, ...]
    rows: tuple[dict[str, str], ...]
    sha256: str


@dataclass(frozen=True, slots=True)
class QualitySummary:
    exact_duplicates: int
    challenge_overlaps: int
    training_overlaps: int
    max_similarity_inside_challenge: dict[str, Any]
    max_similarity_vs_training: dict[str, Any]
    verdict_leakage: int


def sha256_file(path: Path) -> str:
    digest = hashlib.sha256()
    with path.open("rb") as handle:
        for chunk in iter(lambda: handle.read(1 << 20), b""):
            digest.update(chunk)
    return digest.hexdigest()


def read_csv(path: Path) -> tuple[tuple[str, ...], tuple[dict[str, str], ...]]:
    try:
        with path.open("r", encoding="utf-8-sig", newline="") as handle:
            reader = csv.DictReader(handle)
            fields = tuple(reader.fieldnames or ())
            rows = tuple(reader)
    except (OSError, UnicodeError, csv.Error) as exc:
        raise EvaluationError(f"Unable to read CSV {path}: {type(exc).__name__}") from exc
    return fields, rows


def _counter_equals(actual: Counter[str], expected: dict[str, int]) -> bool:
    return actual == Counter(expected)


def validate_frozen_dataset(dataset_path: Path, manifest_path: Path) -> FrozenDataset:
    """Validate the immutable snapshot before any model is loaded."""

    if not dataset_path.is_file():
        raise EvaluationError(f"Frozen dataset not found: {dataset_path}")
    if not manifest_path.is_file():
        raise EvaluationError(f"Frozen dataset manifest not found: {manifest_path}")
    try:
        manifest = json.loads(manifest_path.read_text(encoding="utf-8"))
    except (OSError, UnicodeError, json.JSONDecodeError) as exc:
        raise EvaluationError("Frozen dataset manifest is not valid JSON") from exc
    if not isinstance(manifest, dict):
        raise EvaluationError("Frozen dataset manifest must be an object")
    if manifest.get("dataset_name") != EXPECTED_DATASET_NAME:
        raise EvaluationError("Unexpected external challenge dataset name")
    if manifest.get("version") != EXPECTED_DATASET_VERSION:
        raise EvaluationError("Unexpected external challenge dataset version")
    file_metadata = manifest.get("file")
    if not isinstance(file_metadata, dict):
        raise EvaluationError("Manifest file metadata is missing")
    expected_sha = file_metadata.get("sha256")
    if not isinstance(expected_sha, str) or not re.fullmatch(r"[0-9a-fA-F]{64}", expected_sha):
        raise EvaluationError("Manifest does not contain a valid challenge SHA-256")
    actual_sha = sha256_file(dataset_path)
    if actual_sha.lower() != expected_sha.lower():
        raise EvaluationError("challenge.csv SHA-256 does not match manifest")

    fields, rows = read_csv(dataset_path)
    missing_schema = [field for field in REQUIRED_DATASET_FIELDS if field not in fields]
    if missing_schema:
        raise EvaluationError(f"Frozen dataset schema is missing: {missing_schema}")
    if len(rows) != 120:
        raise EvaluationError(f"Frozen dataset must contain 120 rows, got {len(rows)}")
    labels = Counter(row.get("label", "") for row in rows)
    if not _counter_equals(labels, {"valid": 60, "hoax": 60}):
        raise EvaluationError(f"Frozen dataset label counts are invalid: {dict(labels)}")
    sources = Counter(row.get("evidence_source", "") for row in rows)
    if sources != Counter({source: 20 for source in EXPECTED_SOURCES}):
        raise EvaluationError(f"Frozen dataset source counts are invalid: {dict(sources)}")
    if any((row.get("approved") or "").strip() != "1" for row in rows):
        raise EvaluationError("Frozen dataset contains an unapproved row")
    if any(row.get("label") not in LABEL_ORDER for row in rows):
        raise EvaluationError("Frozen dataset contains an unsupported label")
    missing_values = sorted(
        f"{row.get('id', '<missing-id>')}:{field}"
        for row in rows
        for field in REQUIRED_DATASET_FIELDS
        if not (row.get(field) or "").strip()
    )
    if missing_values:
        raise EvaluationError(f"Frozen dataset has missing required values: {missing_values[:10]}")
    duplicate_ids = [record_id for record_id, count in Counter(row["id"] for row in rows).items() if count > 1]
    if duplicate_ids:
        raise EvaluationError(f"Frozen dataset contains duplicate IDs: {duplicate_ids}")
    if manifest.get("rows") != 120:
        raise EvaluationError("Manifest row count does not equal 120")
    if manifest.get("labels") != {"hoax": 60, "valid": 60}:
        raise EvaluationError("Manifest label counts do not match the frozen dataset")
    if manifest.get("sources") != {source: 20 for source in EXPECTED_SOURCES}:
        raise EvaluationError("Manifest source counts do not match the frozen dataset")
    usage_policy = manifest.get("usage_policy")
    if not isinstance(usage_policy, dict) or usage_policy.get("evaluation_only") is not True:
        raise EvaluationError("Manifest does not mark the dataset evaluation-only")
    return FrozenDataset(
        path=dataset_path,
        manifest_path=manifest_path,
        manifest=manifest,
        fields=fields,
        rows=rows,
        sha256=actual_sha,
    )


def _token_jaccard(left: str, right: str) -> float:
    left_tokens = set(tokenize_norm(left))
    right_tokens = set(tokenize_norm(right))
    union = left_tokens | right_tokens
    return len(left_tokens & right_tokens) / len(union) if union else 0.0


def _shingle_set(text: str) -> set[tuple[str, ...]]:
    return set(word_shingles(tokenize_norm(text), size=3))


def _load_training_index(
    corpus_path: Path,
) -> tuple[list[tuple[str, str]], dict[tuple[str, ...], list[int]], list[set[tuple[str, ...]]]]:
    if not corpus_path.is_file():
        raise EvaluationError(f"Training comparison corpus not found: {corpus_path}")
    records: list[tuple[str, str]] = []
    inverted: dict[tuple[str, ...], list[int]] = defaultdict(list)
    shingles: list[set[tuple[str, ...]]] = []
    try:
        with corpus_path.open("r", encoding="utf-8") as handle:
            for line_number, line in enumerate(handle, start=1):
                if not line.strip():
                    continue
                item = json.loads(line)
                text = str(item.get("text") or "")
                record_id = str(item.get("record_id") or f"line:{line_number}")
                records.append((record_id, text))
                current = _shingle_set(text)
                index = len(shingles)
                shingles.append(current)
                for shingle in current:
                    inverted[shingle].append(index)
    except (OSError, UnicodeError, json.JSONDecodeError) as exc:
        raise EvaluationError("Unable to read training comparison corpus") from exc
    return records, inverted, shingles


def _closest_training(
    text: str,
    records: list[tuple[str, str]],
    inverted: dict[tuple[str, ...], list[int]],
    shingles: list[set[tuple[str, ...]]],
) -> tuple[float, str]:
    query = _shingle_set(text)
    intersections: Counter[int] = Counter()
    for shingle in query:
        intersections.update(inverted.get(shingle, ()))
    best_score = 0.0
    best_id = ""
    for index, intersection in intersections.items():
        union = len(query) + len(shingles[index]) - intersection
        score = intersection / union if union else 0.0
        if score > best_score:
            best_score = score
            best_id = records[index][0]
    return best_score, best_id


def validate_dataset_quality(dataset: FrozenDataset, corpus_path: Path) -> QualitySummary:
    rows = dataset.rows
    exact_seen: dict[str, str] = {}
    exact_duplicates = 0
    for row in rows:
        key = normalize_key(row["text"])
        if key in exact_seen:
            exact_duplicates += 1
        else:
            exact_seen[key] = row["id"]

    max_challenge = {"similarity": 0.0, "left_id": "", "right_id": ""}
    challenge_overlaps = 0
    for index, left in enumerate(rows):
        for right in rows[:index]:
            score = _token_jaccard(left["text"], right["text"])
            if score > float(max_challenge["similarity"]):
                max_challenge = {
                    "similarity": score,
                    "left_id": left["id"],
                    "right_id": right["id"],
                }
            if score >= SIMILARITY_THRESHOLD:
                challenge_overlaps += 1

    records, inverted, shingles = _load_training_index(corpus_path)
    max_training = {"similarity": 0.0, "candidate_id": "", "training_id": ""}
    training_overlaps = 0
    for row in rows:
        score, matched_id = _closest_training(row["text"], records, inverted, shingles)
        if score > float(max_training["similarity"]):
            max_training = {
                "similarity": score,
                "candidate_id": row["id"],
                "training_id": matched_id,
            }
        if score >= SIMILARITY_THRESHOLD:
            training_overlaps += 1

    verdict_leakage = sum(
        1
        for row in rows
        if row["label"] == "hoax" and VERDICT_LEAK_RE.search(row["text"])
    )
    if exact_duplicates or challenge_overlaps or training_overlaps or verdict_leakage:
        raise EvaluationError(
            "Frozen dataset quality validation failed: "
            f"exact={exact_duplicates}, challenge={challenge_overlaps}, "
            f"training={training_overlaps}, verdict_leakage={verdict_leakage}"
        )
    manifest_quality = dataset.manifest.get("quality")
    if isinstance(manifest_quality, dict):
        if manifest_quality.get("exact_duplicates") != exact_duplicates:
            raise EvaluationError("Manifest exact-duplicate count does not match dataset")
        if manifest_quality.get("training_overlap_at_or_above_threshold") != training_overlaps:
            raise EvaluationError("Manifest training-overlap count does not match dataset")
        if manifest_quality.get("challenge_overlap_at_or_above_threshold") != challenge_overlaps:
            raise EvaluationError("Manifest challenge-overlap count does not match dataset")
        if manifest_quality.get("verdict_leakage") != verdict_leakage:
            raise EvaluationError("Manifest verdict-leakage count does not match dataset")
        expected_training_max = manifest_quality.get("max_similarity_vs_training", {})
        expected_challenge_max = manifest_quality.get("max_similarity_inside_challenge", {})
        if isinstance(expected_training_max, dict) and not math.isclose(
            float(expected_training_max.get("similarity", -1.0)),
            float(max_training["similarity"]),
            rel_tol=0.0,
            abs_tol=1e-9,
        ):
            raise EvaluationError("Manifest maximum training similarity does not match dataset")
        if isinstance(expected_challenge_max, dict) and not math.isclose(
            float(expected_challenge_max.get("similarity", -1.0)),
            float(max_challenge["similarity"]),
            rel_tol=0.0,
            abs_tol=1e-9,
        ):
            raise EvaluationError("Manifest maximum challenge similarity does not match dataset")
    return QualitySummary(
        exact_duplicates=exact_duplicates,
        challenge_overlaps=challenge_overlaps,
        training_overlaps=training_overlaps,
        max_similarity_inside_challenge=max_challenge,
        max_similarity_vs_training=max_training,
        verdict_leakage=verdict_leakage,
    )


def _normalise_version(value: str) -> str:
    return value.strip().lstrip("vV")


def validate_model_metadata(model_path: Path, expected_version: str = EXPECTED_MODEL_VERSION) -> dict[str, Any]:
    """Validate release identity and label/threshold metadata without loading weights."""

    if not model_path.is_dir():
        raise EvaluationError(f"Model directory not found: {model_path}")
    if _normalise_version(model_path.name) != _normalise_version(expected_version):
        raise EvaluationError(f"Model path is not {expected_version}: {model_path}")
    try:
        config = json.loads((model_path / "config.json").read_text(encoding="utf-8"))
        threshold_data = json.loads((model_path / "threshold.json").read_text(encoding="utf-8"))
    except (OSError, UnicodeError, json.JSONDecodeError) as exc:
        raise EvaluationError("Model metadata is not readable") from exc
    raw_id2label = config.get("id2label") if isinstance(config, dict) else None
    id2label = {int(key): str(value) for key, value in (raw_id2label or {}).items()}
    if id2label != EXPECTED_ID2LABEL or config.get("num_labels") != 2:
        raise EvaluationError(f"Model label mapping is not {EXPECTED_ID2LABEL}")
    threshold = threshold_data.get("threshold") if isinstance(threshold_data, dict) else None
    if isinstance(threshold, bool) or not isinstance(threshold, (int, float)):
        raise EvaluationError("Model threshold metadata is missing")
    if not math.isclose(float(threshold), SERVING_THRESHOLD, rel_tol=0.0, abs_tol=1e-15):
        raise EvaluationError(f"Model threshold is not {SERVING_THRESHOLD}")

    release_entry: dict[str, Any] | None = None
    for manifest_path in (model_path / "manifest.json", model_path.parent / "manifest.json"):
        if not manifest_path.is_file():
            continue
        try:
            manifest_data = json.loads(manifest_path.read_text(encoding="utf-8"))
        except (OSError, UnicodeError, json.JSONDecodeError) as exc:
            raise EvaluationError("Model release metadata is not valid JSON") from exc
        entries = manifest_data if isinstance(manifest_data, list) else [manifest_data]
        release_entry = next(
            (
                entry
                for entry in entries
                if isinstance(entry, dict)
                and isinstance(entry.get("version"), str)
                and _normalise_version(entry["version"]) == _normalise_version(expected_version)
            ),
            None,
        )
        if release_entry is None:
            raise EvaluationError("Model release metadata does not identify v1.0.0")
        break
    if release_entry is None:
        raise EvaluationError("Model release metadata is missing")
    if release_entry.get("model_name") != EXPECTED_MODEL_NAME:
        raise EvaluationError(f"Model release is not {EXPECTED_MODEL_NAME}")
    if "threshold" in release_entry and not math.isclose(
        float(release_entry["threshold"]), SERVING_THRESHOLD, rel_tol=0.0, abs_tol=1e-15
    ):
        raise EvaluationError("Model release threshold does not match serving threshold")
    try:
        relative_path = str(model_path.resolve().relative_to(PROJECT_ROOT)).replace("\\", "/")
    except ValueError:
        relative_path = str(model_path)
    safe_release_metadata = {
        key: release_entry[key]
        for key in ("version", "exported_at", "threshold", "eval_accuracy", "eval_macro_f1")
        if key in release_entry
    }
    return {
        "version": expected_version,
        "relative_path": relative_path,
        "label_mapping": {str(key): value for key, value in EXPECTED_ID2LABEL.items()},
        "threshold": SERVING_THRESHOLD,
        "release_metadata": safe_release_metadata,
    }


def binary_label_from_probabilities(prob_valid: float, prob_hoax: float) -> str:
    """Return the binary argmax label; ties follow the explicit valid-first order."""

    return max(LABEL_ORDER, key=lambda label: {"valid": prob_valid, "hoax": prob_hoax}[label])


def served_label_for_prediction(predicted_binary_label: str, confidence: float, threshold: float) -> tuple[str, bool]:
    if confidence < threshold:
        return "meragukan", True
    return predicted_binary_label, False


def _class_metrics(y_true: Iterable[str], y_pred: Iterable[str]) -> dict[str, Any]:
    true_values = list(y_true)
    pred_values = list(y_pred)
    confusion = [[0, 0], [0, 0]]
    index = {label: position for position, label in enumerate(LABEL_ORDER)}
    for actual, predicted in zip(true_values, pred_values):
        confusion[index[actual]][index[predicted]] += 1
    support = {label: sum(confusion[index[label]]) for label in LABEL_ORDER}
    per_class: dict[str, dict[str, float | int]] = {}
    for label in LABEL_ORDER:
        position = index[label]
        tp = confusion[position][position]
        predicted_positive = sum(confusion[row][position] for row in range(len(LABEL_ORDER)))
        actual_positive = support[label]
        precision = tp / predicted_positive if predicted_positive else 0.0
        recall = tp / actual_positive if actual_positive else 0.0
        f1 = 2 * precision * recall / (precision + recall) if precision + recall else 0.0
        per_class[label] = {
            "precision": precision,
            "recall": recall,
            "f1": f1,
            "support": actual_positive,
        }
    total = len(true_values)
    correct = sum(confusion[position][position] for position in range(len(LABEL_ORDER)))
    return {
        "samples": total,
        "accuracy": correct / total if total else None,
        "macro_precision": sum(per_class[label]["precision"] for label in LABEL_ORDER) / len(LABEL_ORDER)
        if total
        else None,
        "macro_recall": sum(per_class[label]["recall"] for label in LABEL_ORDER) / len(LABEL_ORDER)
        if total
        else None,
        "macro_f1": sum(per_class[label]["f1"] for label in LABEL_ORDER) / len(LABEL_ORDER)
        if total
        else None,
        "per_class": per_class,
        "confusion_matrix": confusion,
        "label_order": list(LABEL_ORDER),
        "zero_division_policy": "undefined precision/recall/F1 is recorded as 0.0",
    }


def compute_binary_metrics(rows: Iterable[dict[str, Any]]) -> dict[str, Any]:
    rows = list(rows)
    return _class_metrics(
        (str(row["true_label"]) for row in rows),
        (str(row["predicted_binary_label"]) for row in rows),
    )


def compute_abstention_metrics(rows: Iterable[dict[str, Any]], threshold: float) -> dict[str, Any]:
    rows = list(rows)
    covered = [row for row in rows if float(row["confidence"]) >= threshold]
    abstained = len(rows) - len(covered)
    covered_metrics = _class_metrics(
        (str(row["true_label"]) for row in covered),
        (str(row["predicted_binary_label"]) for row in covered),
    )
    return {
        "threshold": threshold,
        "total_samples": len(rows),
        "covered_samples": len(covered),
        "abstained_samples": abstained,
        "coverage": len(covered) / len(rows) if rows else None,
        "abstention_rate": abstained / len(rows) if rows else None,
        "covered_accuracy": covered_metrics["accuracy"],
        "covered_macro_precision": covered_metrics["macro_precision"],
        "covered_macro_recall": covered_metrics["macro_recall"],
        "covered_macro_f1": covered_metrics["macro_f1"],
        "covered_per_class": covered_metrics["per_class"],
        "label_order": list(LABEL_ORDER),
        "note": "meragukan is a serving abstention, not a third ground-truth or training class",
        "zero_division_policy": covered_metrics["zero_division_policy"],
    }


def _load_runtime(model_path: Path) -> ModelRuntime:
    settings = Settings(
        service_name="external-challenge-evaluator",
        service_version="evaluation-only",
        model_path=str(model_path),
        model_version=EXPECTED_MODEL_VERSION,
        internal_api_token=None,
        max_concurrency=1,
        max_text_length=20_000,
        max_sequence_length=512,
        local_files_only=True,
        require_release_manifest=False,
    )
    runtime = ModelRuntime(settings)
    runtime.load()
    if not runtime.ready:
        raise EvaluationError(f"Production model failed to load: {runtime.load_error or 'unknown error'}")
    if runtime.model_version is None or _normalise_version(runtime.model_version) != _normalise_version(EXPECTED_MODEL_VERSION):
        raise EvaluationError(f"Loaded model version is not {EXPECTED_MODEL_VERSION}: {runtime.model_version}")
    if runtime.label_map != EXPECTED_ID2LABEL:
        raise EvaluationError(f"Loaded model label mapping is not {EXPECTED_ID2LABEL}")
    if runtime.threshold is None or not math.isclose(runtime.threshold, SERVING_THRESHOLD, rel_tol=0.0, abs_tol=1e-15):
        raise EvaluationError("Loaded model threshold is not the serving threshold 0.99")
    return runtime


async def _infer_async(
    runtime: ModelRuntime,
    rows: tuple[dict[str, str], ...],
    batch_size: int = 1,
) -> list[dict[str, Any]]:
    predictions: list[dict[str, Any]] = []
    for start in range(0, len(rows), batch_size):
        for row in rows[start : start + batch_size]:
            result = await runtime.predict(row["text"])
            try:
                prob_valid = float(result.raw_scores["valid"])
                prob_hoax = float(result.raw_scores["hoax"])
            except (KeyError, TypeError, ValueError) as exc:
                raise EvaluationError(f"Model returned an invalid probability payload for {row['id']}") from exc
            if not all(math.isfinite(value) and 0.0 <= value <= 1.0 for value in (prob_valid, prob_hoax)):
                raise EvaluationError(f"Model returned invalid probabilities for {row['id']}")
            if not math.isclose(prob_valid + prob_hoax, 1.0, rel_tol=1e-6, abs_tol=1e-6):
                raise EvaluationError(f"Model probabilities do not sum to one for {row['id']}")
            predicted = binary_label_from_probabilities(prob_valid, prob_hoax)
            confidence = max(prob_valid, prob_hoax)
            served_label, abstained = served_label_for_prediction(predicted, confidence, SERVING_THRESHOLD)
            predictions.append(
                {
                    "id": row["id"],
                    "text": row["text"],
                    "true_label": row["label"],
                    "predicted_binary_label": predicted,
                    "prob_valid": prob_valid,
                    "prob_hoax": prob_hoax,
                    "confidence": confidence,
                    "correct": predicted == row["label"],
                    "served_label": served_label,
                    "abstained": abstained,
                    "text_source": row["text_source"],
                    "evidence_source": row["evidence_source"],
                    "evidence_url": row["evidence_url"],
                    "published_at": row["published_at"],
                    "topic": row["topic"],
                    "verification_method": row["verification_method"],
                    "notes": row["notes"],
                    "approved": row["approved"],
                }
            )
    return predictions


def infer_rows(
    runtime: ModelRuntime,
    rows: tuple[dict[str, str], ...],
    batch_size: int = 1,
) -> list[dict[str, Any]]:
    return asyncio.run(_infer_async(runtime, rows, batch_size=batch_size))


def _write_predictions(path: Path, predictions: list[dict[str, Any]]) -> None:
    with path.open("w", encoding="utf-8-sig", newline="") as handle:
        writer = csv.DictWriter(handle, fieldnames=PREDICTION_FIELDS, extrasaction="raise", lineterminator="\r\n")
        writer.writeheader()
        for prediction in predictions:
            row = dict(prediction)
            for field in ("prob_valid", "prob_hoax", "confidence"):
                row[field] = f"{float(row[field]):.12f}"
            row["correct"] = "1" if row["correct"] else "0"
            row["abstained"] = "1" if row["abstained"] else "0"
            writer.writerow(row)


def _write_confusion_matrix(path: Path, matrix: list[list[int]]) -> None:
    import matplotlib

    matplotlib.use("Agg")
    import matplotlib.pyplot as plt

    figure, axis = plt.subplots(figsize=(5.2, 4.5))
    image = axis.imshow(matrix, cmap="Blues")
    figure.colorbar(image, ax=axis, fraction=0.046, pad=0.04)
    axis.set_xticks(range(2), LABEL_ORDER)
    axis.set_yticks(range(2), LABEL_ORDER)
    axis.set_xlabel("Predicted")
    axis.set_ylabel("Actual")
    axis.set_title("External Challenge — Binary Confusion Matrix")
    for actual in range(2):
        for predicted in range(2):
            axis.text(predicted, actual, str(matrix[actual][predicted]), ha="center", va="center")
    figure.tight_layout()
    figure.savefig(path, dpi=160)
    plt.close(figure)


def _metric(value: Any) -> str:
    return "n/a" if value is None else f"{float(value):.6f}"


def _write_report(
    path: Path,
    dataset: FrozenDataset,
    model_metadata: dict[str, Any],
    quality: QualitySummary,
    binary: dict[str, Any],
    abstention: dict[str, Any],
    evaluation_time: str,
) -> None:
    manifest = dataset.manifest
    lines = [
        "# External Challenge Evaluation",
        "",
        "## Dataset",
        "",
        f"- Dataset: `{manifest['dataset_name']}` v{manifest['version']}",
        f"- File: `{dataset.path.as_posix()}`",
        f"- SHA-256: `{dataset.sha256}`",
        f"- Samples: `{len(dataset.rows)}` (valid={sum(row['label'] == 'valid' for row in dataset.rows)}, hoax={sum(row['label'] == 'hoax' for row in dataset.rows)})",
        "- Policy: evaluation-only; this snapshot was not used for training, validation, calibration, threshold tuning, or model selection.",
        "",
        "## Model",
        "",
        f"- Version: `{model_metadata['version']}`",
        f"- Release: `{model_metadata['relative_path']}`",
        "- Label mapping: `0=valid`, `1=hoax`",
        f"- Serving threshold: `{SERVING_THRESHOLD:.2f}`",
        f"- Evaluated at: `{evaluation_time}`",
        "",
        "## Binary Evaluation",
        "",
        f"- Accuracy: `{_metric(binary['accuracy'])}`",
        f"- Macro precision: `{_metric(binary['macro_precision'])}`",
        f"- Macro recall: `{_metric(binary['macro_recall'])}`",
        f"- Macro F1: `{_metric(binary['macro_f1'])}`",
        "",
        "Confusion matrix (rows=actual, columns=predicted; order: valid, hoax):",
        "",
        "| Actual \\ Predicted | valid | hoax |",
        "| --- | ---: | ---: |",
        f"| valid | {binary['confusion_matrix'][0][0]} | {binary['confusion_matrix'][0][1]} |",
        f"| hoax | {binary['confusion_matrix'][1][0]} | {binary['confusion_matrix'][1][1]} |",
        "",
        "| Class | Precision | Recall | F1 | Support |",
        "| --- | ---: | ---: | ---: | ---: |",
    ]
    for label in LABEL_ORDER:
        metrics = binary["per_class"][label]
        lines.append(
            f"| {label} | {_metric(metrics['precision'])} | {_metric(metrics['recall'])} | "
            f"{_metric(metrics['f1'])} | {metrics['support']} |"
        )
    lines.extend(
        [
            "",
            "## Serving Abstention",
            "",
            f"- Threshold: `{SERVING_THRESHOLD:.2f}`",
            f"- Covered samples: `{abstention['covered_samples']}`",
            f"- Abstained samples: `{abstention['abstained_samples']}`",
            f"- Coverage: `{_metric(abstention['coverage'])}`",
            f"- Abstention rate: `{_metric(abstention['abstention_rate'])}`",
            f"- Covered accuracy: `{_metric(abstention['covered_accuracy'])}`",
            f"- Covered macro precision: `{_metric(abstention['covered_macro_precision'])}`",
            f"- Covered macro recall: `{_metric(abstention['covered_macro_recall'])}`",
            f"- Covered macro F1: `{_metric(abstention['covered_macro_f1'])}`",
            "- `meragukan` is a serving abstention, not a third ground-truth or training class.",
            "- Binary metrics above do not apply the 0.99 threshold; thresholding is simulated only in this section.",
            "",
            "## Frozen-set Quality",
            "",
            f"- Exact duplicates: `{quality.exact_duplicates}`",
            f"- Challenge overlap at/above 0.65: `{quality.challenge_overlaps}`",
            f"- Training overlap at/above 0.65: `{quality.training_overlaps}`",
            f"- Maximum challenge similarity: `{quality.max_similarity_inside_challenge['similarity']:.6f}`",
            f"- Maximum training similarity: `{quality.max_similarity_vs_training['similarity']:.6f}`",
            f"- Verdict leakage: `{quality.verdict_leakage}`",
            "",
            "## Limitations",
            "",
            "- This is one frozen, source-balanced external challenge set; results should not be interpreted as universal model generalization.",
            "- Samples were prioritized after the training-dataset creation date, but the set is not a strict temporal holdout.",
            "- The dataset was not modified after observing model results, and no threshold or calibration search was performed.",
        ]
    )
    path.write_text("\n".join(lines) + "\n", encoding="utf-8")


def _validate_prediction_consistency(
    dataset: FrozenDataset,
    predictions: list[dict[str, Any]],
    binary: dict[str, Any],
    abstention: dict[str, Any],
) -> None:
    if len(predictions) != len(dataset.rows):
        raise EvaluationError("Prediction row count does not match frozen dataset")
    if [row["id"] for row in predictions] != [row["id"] for row in dataset.rows]:
        raise EvaluationError("Prediction IDs/order do not match frozen dataset")
    for prediction, source in zip(predictions, dataset.rows):
        if prediction["true_label"] != source["label"]:
            raise EvaluationError(f"True label changed for {source['id']}")
        if prediction["predicted_binary_label"] not in LABEL_ORDER:
            raise EvaluationError(f"Invalid binary prediction for {source['id']}")
        total_probability = prediction["prob_valid"] + prediction["prob_hoax"]
        if not math.isclose(total_probability, 1.0, rel_tol=1e-6, abs_tol=1e-6):
            raise EvaluationError(f"Probabilities do not sum to one for {source['id']}")
        expected_label = binary_label_from_probabilities(prediction["prob_valid"], prediction["prob_hoax"])
        if prediction["predicted_binary_label"] != expected_label:
            raise EvaluationError(f"Argmax mismatch for {source['id']}")
        expected_served, expected_abstained = served_label_for_prediction(
            expected_label, prediction["confidence"], SERVING_THRESHOLD
        )
        if (prediction["served_label"], prediction["abstained"]) != (expected_served, expected_abstained):
            raise EvaluationError(f"Serving threshold mismatch for {source['id']}")
        if prediction["correct"] != (expected_label == source["label"]):
            raise EvaluationError(f"Correctness mismatch for {source['id']}")
    if sum(sum(row) for row in binary["confusion_matrix"]) != len(dataset.rows):
        raise EvaluationError("Binary confusion matrix total is not 120")
    if abstention["covered_samples"] + abstention["abstained_samples"] != len(dataset.rows):
        raise EvaluationError("Abstention totals do not match dataset")


def build_parser() -> argparse.ArgumentParser:
    parser = argparse.ArgumentParser(description=__doc__)
    default_dir = PROJECT_ROOT / "datasets" / "challenge" / "external-challenge-v1"
    parser.add_argument("--dataset", type=Path, default=default_dir / "challenge.csv")
    parser.add_argument("--manifest", type=Path, default=default_dir / "manifest.json")
    parser.add_argument("--model-path", type=Path, default=PROJECT_ROOT / "models" / "indobert-hoax" / "v1.0.0")
    parser.add_argument("--output-dir", type=Path, default=default_dir / "results")
    parser.add_argument("--batch-size", type=int, default=1, help="bounded inference group size (default: 1)")
    parser.add_argument("--device", default="cpu", help="production runtime device; only cpu is supported by this evaluator")
    return parser


def run(args: argparse.Namespace) -> dict[str, Any]:
    if args.batch_size < 1:
        raise EvaluationError("batch-size must be positive")
    if args.device.lower() != "cpu":
        raise EvaluationError("This evaluation-only runner supports the production CPU runtime only")

    # Dataset integrity is deliberately checked before model metadata/load.
    dataset = validate_frozen_dataset(args.dataset.resolve(), args.manifest.resolve())
    quality = validate_dataset_quality(dataset, CORPUS_PATH)
    model_metadata = validate_model_metadata(args.model_path.resolve())
    runtime = _load_runtime(args.model_path.resolve())
    evaluation_time = datetime.now(timezone.utc).isoformat()
    predictions = infer_rows(runtime, dataset.rows, batch_size=args.batch_size)
    binary = compute_binary_metrics(predictions)
    abstention = compute_abstention_metrics(predictions, SERVING_THRESHOLD)
    _validate_prediction_consistency(dataset, predictions, binary, abstention)

    args.output_dir.mkdir(parents=True, exist_ok=True)
    predictions_path = args.output_dir / "predictions.csv"
    binary_path = args.output_dir / "binary_evaluation.json"
    abstention_path = args.output_dir / "abstention_evaluation.json"
    matrix_path = args.output_dir / "confusion_matrix.png"
    report_path = args.output_dir / "evaluation_report.md"
    _write_predictions(predictions_path, predictions)
    binary_payload = {
        "dataset": {
            "name": dataset.manifest["dataset_name"],
            "version": dataset.manifest["version"],
            "sha256": dataset.sha256,
            "rows": len(dataset.rows),
            "evaluation_only": True,
        },
        "model": model_metadata,
        "threshold_applied": False,
        "evaluated_at": evaluation_time,
        **binary,
    }
    abstention_payload = {
        "dataset": {
            "name": dataset.manifest["dataset_name"],
            "version": dataset.manifest["version"],
            "sha256": dataset.sha256,
            "rows": len(dataset.rows),
            "evaluation_only": True,
        },
        "model": model_metadata,
        "evaluated_at": evaluation_time,
        **abstention,
    }
    binary_path.write_text(json.dumps(binary_payload, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    abstention_path.write_text(json.dumps(abstention_payload, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    _write_confusion_matrix(matrix_path, binary["confusion_matrix"])
    _write_report(report_path, dataset, model_metadata, quality, binary, abstention, evaluation_time)
    return {
        "dataset": dataset,
        "quality": quality,
        "model": model_metadata,
        "binary": binary,
        "abstention": abstention,
        "predictions": predictions,
        "artifacts": (binary_path, abstention_path, predictions_path, matrix_path, report_path),
    }


def main(argv: list[str] | None = None) -> int:
    parser = build_parser()
    args = parser.parse_args(argv)
    try:
        result = run(args)
    except EvaluationError as exc:
        print(f"evaluation blocked: {exc}", file=sys.stderr)
        return 2
    binary = result["binary"]
    abstention = result["abstention"]
    print(f"dataset_sha256={result['dataset'].sha256}")
    print(f"model_version={result['model']['version']}")
    print(f"binary_accuracy={binary['accuracy']:.6f}")
    print(f"binary_macro_f1={binary['macro_f1']:.6f}")
    print(f"covered_samples={abstention['covered_samples']}")
    print(f"abstained_samples={abstention['abstained_samples']}")
    for artifact in result["artifacts"]:
        print(f"wrote={artifact}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
