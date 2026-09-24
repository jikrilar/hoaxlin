"""Train and evaluate a fixed TF-IDF + Logistic Regression baseline.

The baseline is fitted exactly once on the versioned training split. Internal
test and frozen external challenge records are transform/evaluation-only. This
script performs no parameter search and does not read validation metrics for
model selection.
"""

from __future__ import annotations

import argparse
import csv
import hashlib
import json
import math
import sys
import time
import warnings
from collections import Counter
from dataclasses import dataclass
from importlib.metadata import version as package_version
from pathlib import Path
from typing import Any, Iterable, Sequence

import matplotlib
import numpy as np
from sklearn.exceptions import ConvergenceWarning
from sklearn.feature_extraction.text import TfidfVectorizer
from sklearn.linear_model import LogisticRegression
from sklearn.metrics import accuracy_score, confusion_matrix, precision_recall_fscore_support
from sklearn.pipeline import Pipeline

matplotlib.use("Agg")
import matplotlib.pyplot as plt  # noqa: E402

BERT_ROOT = Path(__file__).resolve().parents[1]
PROJECT_ROOT = BERT_ROOT.parent
if str(BERT_ROOT) not in sys.path:
    sys.path.insert(0, str(BERT_ROOT))

from scripts.evaluate_external_challenge import (  # noqa: E402
    EXPECTED_DATASET_VERSION,
    EXPECTED_MODEL_VERSION,
    LABEL_ORDER,
    EvaluationError,
    FrozenDataset,
    sha256_file,
    validate_frozen_dataset,
)

LABEL_TO_ID = {"valid": 0, "hoax": 1}
ID_TO_LABEL = {value: key for key, value in LABEL_TO_ID.items()}
RANDOM_SEED = 42
EXPECTED_TRAIN_ROWS = 6253
EXPECTED_VALIDATION_ROWS = 782
EXPECTED_INTERNAL_TEST_ROWS = 781
EXPECTED_EXTERNAL_ROWS = 120
TFIDF_CONFIG: dict[str, Any] = {
    "analyzer": "word",
    "lowercase": True,
    "ngram_range": (1, 2),
    "sublinear_tf": True,
    "token_pattern": r"(?u)\b\w\w+\b",
    "min_df": 1,
    "max_df": 1.0,
    "norm": "l2",
    "use_idf": True,
}
LOGISTIC_CONFIG: dict[str, Any] = {
    "C": 1.0,
    "class_weight": None,
    "max_iter": 2000,
    "penalty": "l2",
    "random_state": RANDOM_SEED,
    "solver": "liblinear",
    "tol": 1e-4,
}
INTERNAL_PREDICTION_FIELDS = (
    "id",
    "text",
    "true_label",
    "predicted_label",
    "prob_valid",
    "prob_hoax",
    "confidence",
    "correct",
    "text_source",
    "source",
    "url",
    "topic",
)
EXTERNAL_PREDICTION_FIELDS = (
    "id",
    "text",
    "true_label",
    "predicted_label",
    "prob_valid",
    "prob_hoax",
    "confidence",
    "correct",
    "text_source",
    "evidence_source",
    "evidence_url",
    "topic",
)


class BaselineError(EvaluationError):
    """Raised when a baseline data or methodology contract is violated."""


@dataclass(frozen=True, slots=True)
class DatasetSplit:
    name: str
    path: Path
    rows: tuple[dict[str, str], ...]
    sha256: str


@dataclass(frozen=True, slots=True)
class TrainedBaseline:
    pipeline: Pipeline
    training_seconds: float
    fit_rows: int
    fit_record_ids_sha256: str
    feature_count: int


def _labels(rows: Sequence[dict[str, str]]) -> Counter[str]:
    return Counter(row["label"] for row in rows)


def _ids_sha256(rows: Sequence[dict[str, str]]) -> str:
    payload = "\n".join(row["id"] for row in rows).encode("utf-8")
    return hashlib.sha256(payload).hexdigest()


def load_jsonl_split(path: Path, name: str, expected_rows: int) -> DatasetSplit:
    records: list[dict[str, str]] = []
    try:
        with path.open("r", encoding="utf-8") as handle:
            for line_number, line in enumerate(handle, start=1):
                if not line.strip():
                    continue
                item = json.loads(line)
                if not isinstance(item, dict):
                    raise BaselineError(f"{name} line {line_number} is not an object")
                record_id = str(item.get("record_id") or "").strip()
                text = str(item.get("text") or "")
                label = str(item.get("label") or "").strip()
                if not record_id or not text.strip() or label not in LABEL_TO_ID:
                    raise BaselineError(f"Invalid {name} record at line {line_number}")
                topics = item.get("topics")
                if isinstance(topics, list):
                    topic = ";".join(str(value) for value in topics)
                else:
                    topic = str(item.get("category") or "")
                records.append(
                    {
                        "id": record_id,
                        "text": text,
                        "label": label,
                        "text_source": str(item.get("text_source") or ""),
                        "source": str(item.get("source") or ""),
                        "url": str(item.get("url") or ""),
                        "topic": topic,
                    }
                )
    except (OSError, UnicodeError, json.JSONDecodeError) as exc:
        raise BaselineError(f"Unable to read {name} JSONL") from exc
    if len(records) != expected_rows:
        raise BaselineError(f"{name} must contain {expected_rows} rows, got {len(records)}")
    if len({row["id"] for row in records}) != len(records):
        raise BaselineError(f"{name} contains duplicate record IDs")
    if set(_labels(records)) != set(LABEL_ORDER):
        raise BaselineError(f"{name} labels must be valid/hoax")
    return DatasetSplit(name=name, path=path, rows=tuple(records), sha256=sha256_file(path))


def load_external_split(dataset: FrozenDataset) -> DatasetSplit:
    rows = tuple(
        {
            "id": row["id"],
            "text": row["text"],
            "label": row["label"],
            "text_source": row["text_source"],
            "evidence_source": row["evidence_source"],
            "evidence_url": row["evidence_url"],
            "topic": row["topic"],
        }
        for row in dataset.rows
    )
    return DatasetSplit(name="external_challenge", path=dataset.path, rows=rows, sha256=dataset.sha256)


def build_pipeline() -> Pipeline:
    return Pipeline(
        steps=[
            ("tfidf", TfidfVectorizer(**TFIDF_CONFIG)),
            ("logreg", LogisticRegression(**LOGISTIC_CONFIG)),
        ]
    )


def train_baseline(train: DatasetSplit) -> TrainedBaseline:
    pipeline = build_pipeline()
    texts = [row["text"] for row in train.rows]
    labels = np.asarray([LABEL_TO_ID[row["label"]] for row in train.rows], dtype=np.int64)
    started = time.perf_counter()
    with warnings.catch_warnings(record=True) as captured:
        warnings.simplefilter("always", ConvergenceWarning)
        pipeline.fit(texts, labels)
    convergence = [warning for warning in captured if issubclass(warning.category, ConvergenceWarning)]
    if convergence:
        raise BaselineError("Logistic Regression did not converge with max_iter=2000")
    elapsed = time.perf_counter() - started
    vectorizer: TfidfVectorizer = pipeline.named_steps["tfidf"]
    classifier: LogisticRegression = pipeline.named_steps["logreg"]
    if list(classifier.classes_) != [0, 1]:
        raise BaselineError(f"Unexpected fitted class ordering: {list(classifier.classes_)}")
    return TrainedBaseline(
        pipeline=pipeline,
        training_seconds=elapsed,
        fit_rows=len(train.rows),
        fit_record_ids_sha256=_ids_sha256(train.rows),
        feature_count=len(vectorizer.vocabulary_),
    )


def compute_metrics(true_ids: Sequence[int], predicted_ids: Sequence[int]) -> dict[str, Any]:
    precision, recall, f1, support = precision_recall_fscore_support(
        true_ids,
        predicted_ids,
        labels=[0, 1],
        average=None,
        zero_division=0,
    )
    macro_precision, macro_recall, macro_f1, _ = precision_recall_fscore_support(
        true_ids,
        predicted_ids,
        labels=[0, 1],
        average="macro",
        zero_division=0,
    )
    matrix = confusion_matrix(true_ids, predicted_ids, labels=[0, 1])
    return {
        "samples": len(true_ids),
        "accuracy": float(accuracy_score(true_ids, predicted_ids)),
        "macro_precision": float(macro_precision),
        "macro_recall": float(macro_recall),
        "macro_f1": float(macro_f1),
        "per_class": {
            label: {
                "precision": float(precision[index]),
                "recall": float(recall[index]),
                "f1": float(f1[index]),
                "support": int(support[index]),
            }
            for index, label in enumerate(LABEL_ORDER)
        },
        "confusion_matrix": matrix.astype(int).tolist(),
        "label_order": list(LABEL_ORDER),
        "confusion_axes": {"rows": "actual", "columns": "predicted"},
        "zero_division_policy": "undefined precision/recall/F1 is recorded as 0.0",
    }


def _model_state_digest(model: LogisticRegression) -> str:
    digest = hashlib.sha256()
    digest.update(np.asarray(model.coef_, dtype=np.float64).tobytes())
    digest.update(np.asarray(model.intercept_, dtype=np.float64).tobytes())
    digest.update(np.asarray(model.classes_, dtype=np.int64).tobytes())
    return digest.hexdigest()


def evaluate_split(trained: TrainedBaseline, split: DatasetSplit) -> tuple[dict[str, Any], list[dict[str, Any]]]:
    vectorizer: TfidfVectorizer = trained.pipeline.named_steps["tfidf"]
    classifier: LogisticRegression = trained.pipeline.named_steps["logreg"]
    state_before = _model_state_digest(classifier)
    features = vectorizer.transform([row["text"] for row in split.rows])
    probabilities = classifier.predict_proba(features)
    state_after = _model_state_digest(classifier)
    if state_before != state_after:
        raise BaselineError(f"Model parameters changed while evaluating {split.name}")
    if probabilities.shape != (len(split.rows), 2):
        raise BaselineError(f"Unexpected probability shape for {split.name}: {probabilities.shape}")
    if not np.all(np.isfinite(probabilities)) or not np.allclose(probabilities.sum(axis=1), 1.0, atol=1e-9):
        raise BaselineError(f"Invalid probabilities for {split.name}")
    predicted_ids = np.argmax(probabilities, axis=1)
    true_ids = np.asarray([LABEL_TO_ID[row["label"]] for row in split.rows], dtype=np.int64)
    predictions: list[dict[str, Any]] = []
    for source, truth, prediction, probability in zip(split.rows, true_ids, predicted_ids, probabilities):
        predicted_label = ID_TO_LABEL[int(prediction)]
        output = {
            "id": source["id"],
            "text": source["text"],
            "true_label": ID_TO_LABEL[int(truth)],
            "predicted_label": predicted_label,
            "prob_valid": float(probability[0]),
            "prob_hoax": float(probability[1]),
            "confidence": float(max(probability)),
            "correct": int(truth) == int(prediction),
            "text_source": source.get("text_source", ""),
            "source": source.get("source", ""),
            "url": source.get("url", ""),
            "evidence_source": source.get("evidence_source", ""),
            "evidence_url": source.get("evidence_url", ""),
            "topic": source.get("topic", ""),
        }
        predictions.append(output)
    metrics = compute_metrics(true_ids.tolist(), predicted_ids.tolist())
    if sum(sum(row) for row in metrics["confusion_matrix"]) != len(split.rows):
        raise BaselineError(f"Confusion matrix total does not match {split.name}")
    return metrics, predictions


def _json_safe_config(config: dict[str, Any]) -> dict[str, Any]:
    return {
        key: list(value) if isinstance(value, tuple) else value
        for key, value in config.items()
    }


def training_metadata(
    trained: TrainedBaseline,
    train: DatasetSplit,
    validation: DatasetSplit,
) -> dict[str, Any]:
    return {
        "name": "TF-IDF + Logistic Regression",
        "label_mapping": {label: identifier for label, identifier in LABEL_TO_ID.items()},
        "random_seed": RANDOM_SEED,
        "tfidf": _json_safe_config(TFIDF_CONFIG),
        "logistic_regression": _json_safe_config(LOGISTIC_CONFIG),
        "fit_split": "train.jsonl",
        "fit_rows": trained.fit_rows,
        "fit_record_ids_sha256": trained.fit_record_ids_sha256,
        "fit_dataset_sha256": train.sha256,
        "training_label_distribution": dict(_labels(train.rows)),
        "feature_count": trained.feature_count,
        "training_seconds": trained.training_seconds,
        "validation_used_for_selection": False,
        "validation_audit": {
            "rows": len(validation.rows),
            "sha256": validation.sha256,
            "vectorized": False,
            "used_for_selection": False,
        },
        "dependency_versions": {
            "python": sys.version.split()[0],
            "numpy": package_version("numpy"),
            "scikit-learn": package_version("scikit-learn"),
            "scipy": package_version("scipy"),
        },
    }


def evaluation_payload(
    metadata: dict[str, Any],
    split: DatasetSplit,
    metrics: dict[str, Any],
) -> dict[str, Any]:
    return {
        "baseline": metadata,
        "dataset": {
            "split": split.name,
            "path": str(split.path.resolve().relative_to(PROJECT_ROOT)).replace("\\", "/"),
            "sha256": split.sha256,
            "rows": len(split.rows),
            "label_distribution": dict(_labels(split.rows)),
            "fit_or_selection_use": False,
        },
        **metrics,
    }


def write_predictions(path: Path, rows: Sequence[dict[str, Any]], external: bool) -> None:
    fields = EXTERNAL_PREDICTION_FIELDS if external else INTERNAL_PREDICTION_FIELDS
    with path.open("w", encoding="utf-8-sig", newline="") as handle:
        writer = csv.DictWriter(handle, fieldnames=fields, extrasaction="ignore", lineterminator="\r\n")
        writer.writeheader()
        for source in rows:
            row = {field: source.get(field, "") for field in fields}
            for field in ("prob_valid", "prob_hoax", "confidence"):
                row[field] = f"{float(row[field]):.12f}"
            row["correct"] = "1" if row["correct"] else "0"
            writer.writerow(row)


def write_confusion_matrix(path: Path, matrix: list[list[int]], title: str) -> None:
    figure, axis = plt.subplots(figsize=(5.2, 4.5))
    image = axis.imshow(matrix, cmap="Blues")
    figure.colorbar(image, ax=axis, fraction=0.046, pad=0.04)
    axis.set_xticks(range(2), LABEL_ORDER)
    axis.set_yticks(range(2), LABEL_ORDER)
    axis.set_xlabel("Predicted")
    axis.set_ylabel("Actual")
    axis.set_title(title)
    for actual in range(2):
        for predicted in range(2):
            axis.text(predicted, actual, str(matrix[actual][predicted]), ha="center", va="center")
    figure.tight_layout()
    figure.savefig(path, dpi=160)
    plt.close(figure)


def _metric_subset(payload: dict[str, Any]) -> dict[str, float]:
    return {
        key: float(payload[key])
        for key in ("accuracy", "macro_precision", "macro_recall", "macro_f1")
    }


def _read_json(path: Path, description: str) -> dict[str, Any]:
    try:
        value = json.loads(path.read_text(encoding="utf-8"))
    except (OSError, UnicodeError, json.JSONDecodeError) as exc:
        raise BaselineError(f"Unable to read {description}") from exc
    if not isinstance(value, dict):
        raise BaselineError(f"{description} must be a JSON object")
    return value


def build_comparison(
    indobert_internal: dict[str, Any],
    indobert_external: dict[str, Any],
    baseline_internal: dict[str, Any],
    baseline_external: dict[str, Any],
    challenge_sha256: str,
) -> dict[str, Any]:
    if int(indobert_internal.get("n", -1)) != EXPECTED_INTERNAL_TEST_ROWS:
        raise BaselineError("IndoBERT internal evaluation row count is not 781")
    if indobert_external.get("dataset", {}).get("sha256") != challenge_sha256:
        raise BaselineError("IndoBERT external evaluation SHA does not match frozen challenge")
    if indobert_external.get("model", {}).get("version") != EXPECTED_MODEL_VERSION:
        raise BaselineError("IndoBERT external evaluation version is not v1.0.0")
    models = {
        "indobert_v1.0.0": {
            "display_name": "IndoBERT v1.0.0",
            "internal": _metric_subset(indobert_internal),
            "external": _metric_subset(indobert_external),
        },
        "tfidf_logistic_regression": {
            "display_name": "TF-IDF + Logistic Regression",
            "internal": _metric_subset(baseline_internal),
            "external": _metric_subset(baseline_external),
        },
    }
    for values in models.values():
        values["generalization_gap"] = {
            "accuracy": values["internal"]["accuracy"] - values["external"]["accuracy"],
            "macro_f1": values["internal"]["macro_f1"] - values["external"]["macro_f1"],
            "definition": "internal metric minus external metric",
        }
    return {
        "datasets": {
            "internal_test_rows": EXPECTED_INTERNAL_TEST_ROWS,
            "external_challenge_rows": EXPECTED_EXTERNAL_ROWS,
            "external_challenge_sha256": challenge_sha256,
        },
        "models": models,
        "methodology": {
            "external_challenge_use": "evaluation only",
            "parameter_search": False,
            "ranking_claim": False,
        },
    }


def _format_metric(value: Any) -> str:
    return f"{float(value):.6f}"


def _matrix_markdown(matrix: list[list[int]]) -> list[str]:
    return [
        "| Actual \\ Predicted | valid | hoax |",
        "| --- | ---: | ---: |",
        f"| valid | {matrix[0][0]} | {matrix[0][1]} |",
        f"| hoax | {matrix[1][0]} | {matrix[1][1]} |",
    ]


def _per_class_markdown(metrics: dict[str, Any]) -> list[str]:
    lines = [
        "| Class | Precision | Recall | F1 | Support |",
        "| --- | ---: | ---: | ---: | ---: |",
    ]
    for label in LABEL_ORDER:
        values = metrics["per_class"][label]
        lines.append(
            f"| {label} | {_format_metric(values['precision'])} | {_format_metric(values['recall'])} | "
            f"{_format_metric(values['f1'])} | {values['support']} |"
        )
    return lines


def render_comparison(comparison: dict[str, Any]) -> str:
    lines = [
        "# Perbandingan Model",
        "",
        "| Model | Internal Accuracy | Internal Macro Precision | Internal Macro Recall | Internal Macro-F1 | External Accuracy | External Macro Precision | External Macro Recall | External Macro-F1 |",
        "| --- | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: |",
    ]
    for values in comparison["models"].values():
        internal = values["internal"]
        external = values["external"]
        lines.append(
            f"| {values['display_name']} | {_format_metric(internal['accuracy'])} | "
            f"{_format_metric(internal['macro_precision'])} | {_format_metric(internal['macro_recall'])} | "
            f"{_format_metric(internal['macro_f1'])} | {_format_metric(external['accuracy'])} | "
            f"{_format_metric(external['macro_precision'])} | {_format_metric(external['macro_recall'])} | "
            f"{_format_metric(external['macro_f1'])} |"
        )
    lines.extend(
        [
            "",
            "## Generalization Gap",
            "",
            "Gap didefinisikan sebagai metrik internal dikurangi metrik external.",
            "",
            "| Model | Accuracy gap | Macro-F1 gap |",
            "| --- | ---: | ---: |",
        ]
    )
    for values in comparison["models"].values():
        gap = values["generalization_gap"]
        lines.append(
            f"| {values['display_name']} | {_format_metric(gap['accuracy'])} | {_format_metric(gap['macro_f1'])} |"
        )
    lines.extend(
        [
            "",
            "Perbandingan ini bersifat faktual. Tidak ada klaim ranking atau superioritas universal, dan external challenge tidak digunakan untuk tuning atau model selection.",
        ]
    )
    return "\n".join(lines) + "\n"


def render_baseline_report(
    metadata: dict[str, Any],
    internal: dict[str, Any],
    external: dict[str, Any],
    comparison: dict[str, Any],
) -> str:
    lines = [
        "# Evaluasi Model Pembanding",
        "",
        "## Model",
        "",
        "- TF-IDF word-level: lowercase, unigram+bigrams, sublinear TF, L2 normalization, token pattern `(?u)\\b\\w\\w+\\b`.",
        "- Logistic Regression: L2, liblinear, C=1.0, max_iter=2000, tol=1e-4, tanpa class weighting.",
        f"- Random seed: `{metadata['random_seed']}`.",
        f"- Vocabulary/features: `{metadata['feature_count']}`.",
        f"- Training time: `{metadata['training_seconds']:.6f}` detik.",
        f"- Dependency: scikit-learn `{metadata['dependency_versions']['scikit-learn']}`.",
        "- Konfigurasi ditetapkan sebelum external evaluation; tidak ada grid search atau tuning.",
        "",
        "## Dataset Training",
        "",
        f"- Train rows: `{metadata['fit_rows']}`.",
        f"- Distribusi label: `{json.dumps(metadata['training_label_distribution'], sort_keys=True)}`.",
        "- Vectorizer dan classifier hanya di-fit pada `train.jsonl`.",
        f"- Validation split `{metadata['validation_audit']['rows']}` baris hanya diaudit schema/count; tidak ditransform dan tidak digunakan untuk model selection.",
        "",
        "## Internal Test",
        "",
        f"- n: `{internal['samples']}`",
        f"- Accuracy: `{_format_metric(internal['accuracy'])}`",
        f"- Macro precision: `{_format_metric(internal['macro_precision'])}`",
        f"- Macro recall: `{_format_metric(internal['macro_recall'])}`",
        f"- Macro F1: `{_format_metric(internal['macro_f1'])}`",
        "",
        "Confusion matrix (baris=actual, kolom=predicted; urutan valid, hoax):",
        "",
    ]
    lines.extend(_matrix_markdown(internal["confusion_matrix"]))
    lines.extend(["", "Per-class metrics:", ""])
    lines.extend(_per_class_markdown(internal))
    lines.extend(
        [
            "",
            "## External Challenge",
            "",
            f"- n: `{external['samples']}`",
            f"- Accuracy: `{_format_metric(external['accuracy'])}`",
            f"- Macro precision: `{_format_metric(external['macro_precision'])}`",
            f"- Macro recall: `{_format_metric(external['macro_recall'])}`",
            f"- Macro F1: `{_format_metric(external['macro_f1'])}`",
            "",
            "Confusion matrix (baris=actual, kolom=predicted; urutan valid, hoax):",
            "",
        ]
    )
    lines.extend(_matrix_markdown(external["confusion_matrix"]))
    lines.extend(["", "Per-class metrics:", ""])
    lines.extend(_per_class_markdown(external))
    lines.extend(["", "## Perbandingan dengan IndoBERT", ""])
    comparison_table = render_comparison(comparison).splitlines()
    lines.extend(comparison_table[2:])
    lines.extend(
        [
            "",
            "## Keterbatasan",
            "",
            "- Baseline ini sederhana dan tidak menjalani extensive hyperparameter tuning.",
            "- External challenge hanya berisi 120 sampel, masing-masing 20 per sumber.",
            "- Challenge hanya digunakan untuk evaluasi final dan tidak digunakan untuk training, vocabulary fitting, model selection, atau parameter tuning.",
            "- Perbedaan performa internal dan external tidak membuktikan penyebab kausal tertentu.",
            "- Hasil tidak membuktikan superioritas universal salah satu algoritma.",
        ]
    )
    return "\n".join(lines) + "\n"


def _validate_prediction_rows(
    split: DatasetSplit,
    predictions: Sequence[dict[str, Any]],
    metrics: dict[str, Any],
) -> None:
    if len(predictions) != len(split.rows):
        raise BaselineError(f"Prediction count mismatch for {split.name}")
    if [row["id"] for row in predictions] != [row["id"] for row in split.rows]:
        raise BaselineError(f"Prediction IDs/order mismatch for {split.name}")
    for source, prediction in zip(split.rows, predictions):
        if prediction["true_label"] != source["label"]:
            raise BaselineError(f"True-label mismatch for {source['id']}")
        if prediction["text"] != source["text"]:
            raise BaselineError(f"Text changed for {source['id']}")
        probability_sum = float(prediction["prob_valid"]) + float(prediction["prob_hoax"])
        if not math.isclose(probability_sum, 1.0, rel_tol=1e-9, abs_tol=1e-9):
            raise BaselineError(f"Probability sum mismatch for {source['id']}")
        expected = "valid" if prediction["prob_valid"] >= prediction["prob_hoax"] else "hoax"
        if prediction["predicted_label"] != expected:
            raise BaselineError(f"Argmax mismatch for {source['id']}")
        if prediction["correct"] != (prediction["true_label"] == expected):
            raise BaselineError(f"Correctness mismatch for {source['id']}")
    if sum(sum(row) for row in metrics["confusion_matrix"]) != len(split.rows):
        raise BaselineError(f"Confusion total mismatch for {split.name}")


def build_parser() -> argparse.ArgumentParser:
    processed = PROJECT_ROOT / "datasets" / "processed" / "komdigi-antara-v1"
    challenge = PROJECT_ROOT / "datasets" / "challenge" / "external-challenge-v1"
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--train", type=Path, default=processed / "train.jsonl")
    parser.add_argument("--validation", type=Path, default=processed / "val.jsonl")
    parser.add_argument("--test", type=Path, default=processed / "test.jsonl")
    parser.add_argument("--challenge", type=Path, default=challenge / "challenge.csv")
    parser.add_argument("--manifest", type=Path, default=challenge / "manifest.json")
    parser.add_argument("--indobert-internal", type=Path, default=PROJECT_ROOT / "models" / "indobert-hoax" / "v1.0.0" / "evaluation.json")
    parser.add_argument("--indobert-external", type=Path, default=challenge / "results" / "binary_evaluation.json")
    parser.add_argument("--output-dir", type=Path, default=challenge / "results")
    return parser


def run(args: argparse.Namespace) -> dict[str, Any]:
    train = load_jsonl_split(args.train.resolve(), "train", EXPECTED_TRAIN_ROWS)
    validation = load_jsonl_split(args.validation.resolve(), "validation", EXPECTED_VALIDATION_ROWS)
    internal_test = load_jsonl_split(args.test.resolve(), "internal_test", EXPECTED_INTERNAL_TEST_ROWS)
    frozen = validate_frozen_dataset(args.challenge.resolve(), args.manifest.resolve())
    if frozen.manifest.get("version") != EXPECTED_DATASET_VERSION:
        raise BaselineError("Unexpected external challenge version")
    external = load_external_split(frozen)
    if len(external.rows) != EXPECTED_EXTERNAL_ROWS:
        raise BaselineError("External challenge must contain 120 rows")

    trained = train_baseline(train)
    metadata = training_metadata(trained, train, validation)
    internal_metrics, internal_predictions = evaluate_split(trained, internal_test)
    _validate_prediction_rows(internal_test, internal_predictions, internal_metrics)
    model: LogisticRegression = trained.pipeline.named_steps["logreg"]
    state_before_external = _model_state_digest(model)
    external_metrics, external_predictions = evaluate_split(trained, external)
    if _model_state_digest(model) != state_before_external:
        raise BaselineError("External challenge changed fitted model parameters")
    _validate_prediction_rows(external, external_predictions, external_metrics)

    internal_payload = evaluation_payload(metadata, internal_test, internal_metrics)
    external_payload = evaluation_payload(metadata, external, external_metrics)
    external_payload["dataset"]["frozen_manifest_version"] = frozen.manifest["version"]
    external_payload["dataset"]["evaluation_only"] = True
    indobert_internal = _read_json(args.indobert_internal.resolve(), "IndoBERT internal evaluation")
    indobert_external = _read_json(args.indobert_external.resolve(), "IndoBERT external evaluation")
    comparison = build_comparison(
        indobert_internal,
        indobert_external,
        internal_payload,
        external_payload,
        frozen.sha256,
    )

    args.output_dir.mkdir(parents=True, exist_ok=True)
    paths = {
        "internal_evaluation": args.output_dir / "baseline_internal_evaluation.json",
        "external_evaluation": args.output_dir / "baseline_external_evaluation.json",
        "internal_predictions": args.output_dir / "baseline_internal_predictions.csv",
        "external_predictions": args.output_dir / "baseline_external_predictions.csv",
        "internal_confusion": args.output_dir / "baseline_internal_confusion_matrix.png",
        "external_confusion": args.output_dir / "baseline_external_confusion_matrix.png",
        "baseline_report": args.output_dir / "baseline_evaluation_report.md",
        "comparison_json": args.output_dir / "model_comparison.json",
        "comparison_report": args.output_dir / "model_comparison.md",
    }
    paths["internal_evaluation"].write_text(
        json.dumps(internal_payload, ensure_ascii=False, indent=2) + "\n", encoding="utf-8"
    )
    paths["external_evaluation"].write_text(
        json.dumps(external_payload, ensure_ascii=False, indent=2) + "\n", encoding="utf-8"
    )
    write_predictions(paths["internal_predictions"], internal_predictions, external=False)
    write_predictions(paths["external_predictions"], external_predictions, external=True)
    write_confusion_matrix(
        paths["internal_confusion"], internal_metrics["confusion_matrix"], "Baseline — Internal Test"
    )
    write_confusion_matrix(
        paths["external_confusion"], external_metrics["confusion_matrix"], "Baseline — External Challenge"
    )
    paths["comparison_json"].write_text(
        json.dumps(comparison, ensure_ascii=False, indent=2) + "\n", encoding="utf-8"
    )
    paths["comparison_report"].write_text(render_comparison(comparison), encoding="utf-8")
    paths["baseline_report"].write_text(
        render_baseline_report(metadata, internal_metrics, external_metrics, comparison), encoding="utf-8"
    )
    return {
        "train": train,
        "validation": validation,
        "internal_test": internal_test,
        "external": external,
        "metadata": metadata,
        "internal_metrics": internal_metrics,
        "external_metrics": external_metrics,
        "comparison": comparison,
        "paths": paths,
    }


def main(argv: list[str] | None = None) -> int:
    args = build_parser().parse_args(argv)
    try:
        result = run(args)
    except EvaluationError as exc:
        print(f"baseline evaluation blocked: {exc}", file=sys.stderr)
        return 2
    print(f"train_rows={len(result['train'].rows)}")
    print(f"validation_rows_audited={len(result['validation'].rows)}")
    print(f"internal_rows={result['internal_metrics']['samples']}")
    print(f"internal_accuracy={result['internal_metrics']['accuracy']:.6f}")
    print(f"internal_macro_f1={result['internal_metrics']['macro_f1']:.6f}")
    print(f"external_rows={result['external_metrics']['samples']}")
    print(f"external_accuracy={result['external_metrics']['accuracy']:.6f}")
    print(f"external_macro_f1={result['external_metrics']['macro_f1']:.6f}")
    print(f"challenge_sha256={result['external'].sha256}")
    for path in result["paths"].values():
        print(f"wrote={path}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
