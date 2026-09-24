"""Produce reproducible descriptive error analysis for the frozen challenge set.

This script only reads the frozen dataset and existing inference artifacts. It
does not load a model, run inference, tune a threshold, calibrate, or train.
"""

from __future__ import annotations

import argparse
import csv
import json
import math
import sys
from collections import Counter
from pathlib import Path
from statistics import fmean, median
from typing import Any, Iterable, Sequence

BERT_ROOT = Path(__file__).resolve().parents[1]
PROJECT_ROOT = BERT_ROOT.parent
if str(BERT_ROOT) not in sys.path:
    sys.path.insert(0, str(BERT_ROOT))

from scripts.evaluate_external_challenge import (  # noqa: E402
    EXPECTED_MODEL_VERSION,
    LABEL_ORDER,
    SERVING_THRESHOLD,
    EvaluationError,
    FrozenDataset,
    compute_abstention_metrics,
    compute_binary_metrics,
    read_csv,
    served_label_for_prediction,
    validate_frozen_dataset,
)

ERROR_ANALYSIS_FIELDS = (
    "id",
    "text",
    "true_label",
    "predicted_binary_label",
    "error_type",
    "prob_valid",
    "prob_hoax",
    "confidence",
    "served_label",
    "abstained",
    "text_source",
    "evidence_source",
    "evidence_url",
    "topic",
    "text_length",
)
PREDICTION_REQUIRED_FIELDS = (
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
    "topic",
)


def classify_error_type(true_label: str, predicted_label: str) -> str:
    if true_label not in LABEL_ORDER or predicted_label not in LABEL_ORDER:
        raise EvaluationError("Error analysis only supports valid/hoax labels")
    if true_label == predicted_label:
        return "correct"
    if true_label == "valid" and predicted_label == "hoax":
        return "false_positive"
    return "false_negative"


def confusion_counts(rows: Iterable[dict[str, Any]]) -> dict[str, int]:
    counts = {"true_positive": 0, "false_positive": 0, "true_negative": 0, "false_negative": 0}
    for row in rows:
        actual = str(row["true_label"])
        predicted = str(row["predicted_binary_label"])
        if actual == "hoax" and predicted == "hoax":
            counts["true_positive"] += 1
        elif actual == "valid" and predicted == "hoax":
            counts["false_positive"] += 1
        elif actual == "valid" and predicted == "valid":
            counts["true_negative"] += 1
        elif actual == "hoax" and predicted == "valid":
            counts["false_negative"] += 1
        else:
            raise EvaluationError(f"Unsupported confusion pair: {actual}/{predicted}")
    return counts


def numeric_summary(values: Iterable[float | int]) -> dict[str, float | int | None]:
    materialized = list(values)
    if not materialized:
        return {"count": 0, "mean": None, "median": None, "min": None, "max": None}
    return {
        "count": len(materialized),
        "mean": fmean(materialized),
        "median": median(materialized),
        "min": min(materialized),
        "max": max(materialized),
    }


def _is_correct(row: dict[str, Any]) -> bool:
    return str(row["true_label"]) == str(row["predicted_binary_label"])


def summarize_sources(rows: Sequence[dict[str, Any]]) -> dict[str, dict[str, Any]]:
    result: dict[str, dict[str, Any]] = {}
    for source in sorted({str(row["evidence_source"]) for row in rows}):
        subset = [row for row in rows if row["evidence_source"] == source]
        correct = sum(_is_correct(row) for row in subset)
        abstained = sum(bool(row["abstained"]) for row in subset)
        result[source] = {
            "total": len(subset),
            "correct": correct,
            "incorrect": len(subset) - correct,
            "accuracy": correct / len(subset) if subset else None,
            "predicted_valid": sum(row["predicted_binary_label"] == "valid" for row in subset),
            "predicted_hoax": sum(row["predicted_binary_label"] == "hoax" for row in subset),
            "abstained": abstained,
            "abstention_rate": abstained / len(subset) if subset else None,
            "mean_confidence": fmean(float(row["confidence"]) for row in subset) if subset else None,
        }
    return result


def summarize_labels(rows: Sequence[dict[str, Any]]) -> dict[str, dict[str, Any]]:
    result: dict[str, dict[str, Any]] = {}
    for label in LABEL_ORDER:
        subset = [row for row in rows if row["true_label"] == label]
        correct = sum(_is_correct(row) for row in subset)
        confidence = numeric_summary(float(row["confidence"]) for row in subset)
        result[label] = {
            "total": len(subset),
            "correct": correct,
            "incorrect": len(subset) - correct,
            "accuracy_recall": correct / len(subset) if subset else None,
            "mean_confidence": confidence["mean"],
            "median_confidence": confidence["median"],
            "abstained": sum(bool(row["abstained"]) for row in subset),
        }
    return result


def summarize_topics(rows: Sequence[dict[str, Any]]) -> dict[str, dict[str, Any]]:
    result: dict[str, dict[str, Any]] = {}
    for topic in sorted({str(row["topic"]) for row in rows}):
        subset = [row for row in rows if row["topic"] == topic]
        correct = sum(_is_correct(row) for row in subset)
        result[topic] = {
            "total": len(subset),
            "correct": correct,
            "incorrect": len(subset) - correct,
            "accuracy": correct / len(subset) if subset else None,
            "abstained": sum(bool(row["abstained"]) for row in subset),
        }
    return result


def summarize_abstention(rows: Sequence[dict[str, Any]]) -> dict[str, Any]:
    abstained_rows = [row for row in rows if bool(row["abstained"])]
    covered_rows = [row for row in rows if not bool(row["abstained"])]
    abstained_correct = sum(_is_correct(row) for row in abstained_rows)
    covered_correct = sum(_is_correct(row) for row in covered_rows)
    return {
        "threshold": SERVING_THRESHOLD,
        "total_abstained": len(abstained_rows),
        "true_label_distribution": dict(Counter(str(row["true_label"]) for row in abstained_rows)),
        "binary_correct_abstained": abstained_correct,
        "binary_incorrect_abstained": len(abstained_rows) - abstained_correct,
        "binary_correct_covered": covered_correct,
        "binary_incorrect_covered": len(covered_rows) - covered_correct,
        "source_distribution": dict(Counter(str(row["evidence_source"]) for row in abstained_rows)),
        "topic_distribution": dict(Counter(str(row["topic"]) for row in abstained_rows)),
    }


def confidence_summaries(rows: Sequence[dict[str, Any]]) -> dict[str, dict[str, float | int | None]]:
    groups = {
        "correct": [row for row in rows if _is_correct(row)],
        "incorrect": [row for row in rows if not _is_correct(row)],
        "false_positive": [row for row in rows if row["error_type"] == "false_positive"],
        "false_negative": [row for row in rows if row["error_type"] == "false_negative"],
        "valid": [row for row in rows if row["true_label"] == "valid"],
        "hoax": [row for row in rows if row["true_label"] == "hoax"],
    }
    return {
        name: numeric_summary(float(row["confidence"]) for row in subset)
        for name, subset in groups.items()
    }


def text_length_summaries(rows: Sequence[dict[str, Any]]) -> dict[str, dict[str, float | int | None]]:
    groups = {
        "correct": [row for row in rows if _is_correct(row)],
        "incorrect": [row for row in rows if not _is_correct(row)],
        "valid_correct": [row for row in rows if row["true_label"] == "valid" and _is_correct(row)],
        "valid_incorrect": [row for row in rows if row["true_label"] == "valid" and not _is_correct(row)],
        "hoax_correct": [row for row in rows if row["true_label"] == "hoax" and _is_correct(row)],
        "hoax_incorrect": [row for row in rows if row["true_label"] == "hoax" and not _is_correct(row)],
        "abstained": [row for row in rows if bool(row["abstained"])],
        "covered": [row for row in rows if not bool(row["abstained"])],
    }
    return {
        name: numeric_summary(int(row["text_length"]) for row in subset)
        for name, subset in groups.items()
    }


def _compact_prediction(row: dict[str, Any]) -> dict[str, Any]:
    return {
        "id": row["id"],
        "true_label": row["true_label"],
        "predicted_binary_label": row["predicted_binary_label"],
        "error_type": row["error_type"],
        "confidence": row["confidence"],
        "prob_valid": row["prob_valid"],
        "prob_hoax": row["prob_hoax"],
        "served_label": row["served_label"],
        "abstained": row["abstained"],
        "evidence_source": row["evidence_source"],
        "topic": row["topic"],
        "text_length": row["text_length"],
    }


def select_high_confidence_errors(rows: Sequence[dict[str, Any]]) -> dict[str, list[dict[str, Any]]]:
    false_positives = sorted(
        (row for row in rows if row["error_type"] == "false_positive"),
        key=lambda row: (-float(row["confidence"]), str(row["id"])),
    )[:10]
    false_negatives = sorted(
        (row for row in rows if row["error_type"] == "false_negative"),
        key=lambda row: (-float(row["confidence"]), str(row["id"])),
    )
    if len(false_negatives) > 10:
        false_negatives = false_negatives[:10]
    return {
        "false_positive": [_compact_prediction(row) for row in false_positives],
        "false_negative": [_compact_prediction(row) for row in false_negatives],
    }


def select_low_confidence_correct(rows: Sequence[dict[str, Any]]) -> list[dict[str, Any]]:
    selected = sorted(
        (row for row in rows if row["error_type"] == "correct"),
        key=lambda row: (float(row["confidence"]), str(row["id"])),
    )[:10]
    return [_compact_prediction(row) for row in selected]


def _load_json(path: Path, description: str) -> dict[str, Any]:
    try:
        value = json.loads(path.read_text(encoding="utf-8"))
    except (OSError, UnicodeError, json.JSONDecodeError) as exc:
        raise EvaluationError(f"Unable to read {description}") from exc
    if not isinstance(value, dict):
        raise EvaluationError(f"{description} must contain a JSON object")
    return value


def _parse_bool(value: str, field: str, record_id: str) -> bool:
    normalized = value.strip().lower()
    if normalized in {"1", "true"}:
        return True
    if normalized in {"0", "false"}:
        return False
    raise EvaluationError(f"Invalid {field} value for {record_id}")


def load_and_validate_predictions(
    path: Path,
    dataset: FrozenDataset,
    binary_evaluation: dict[str, Any],
    abstention_evaluation: dict[str, Any],
) -> list[dict[str, Any]]:
    fields, raw_rows = read_csv(path)
    missing_fields = [field for field in PREDICTION_REQUIRED_FIELDS if field not in fields]
    if missing_fields:
        raise EvaluationError(f"predictions.csv is missing fields: {missing_fields}")
    if len(raw_rows) != 120:
        raise EvaluationError(f"predictions.csv must contain 120 rows, got {len(raw_rows)}")
    dataset_by_id = {row["id"]: row for row in dataset.rows}
    prediction_ids = [row["id"] for row in raw_rows]
    if len(set(prediction_ids)) != len(prediction_ids):
        raise EvaluationError("predictions.csv contains duplicate IDs")
    if set(prediction_ids) != set(dataset_by_id):
        raise EvaluationError("Prediction IDs do not exactly match challenge.csv")

    rows: list[dict[str, Any]] = []
    for raw in raw_rows:
        record_id = raw["id"]
        source = dataset_by_id[record_id]
        if raw["true_label"] != source["label"]:
            raise EvaluationError(f"True label mismatch for {record_id}")
        for field in ("text", "text_source", "evidence_source", "evidence_url", "topic"):
            if raw[field] != source[field]:
                raise EvaluationError(f"Prediction provenance mismatch for {record_id}:{field}")
        if raw["predicted_binary_label"] not in LABEL_ORDER:
            raise EvaluationError(f"Invalid predicted label for {record_id}")
        try:
            prob_valid = float(raw["prob_valid"])
            prob_hoax = float(raw["prob_hoax"])
            confidence = float(raw["confidence"])
        except ValueError as exc:
            raise EvaluationError(f"Invalid probability for {record_id}") from exc
        if not all(math.isfinite(value) and 0.0 <= value <= 1.0 for value in (prob_valid, prob_hoax, confidence)):
            raise EvaluationError(f"Probability outside [0, 1] for {record_id}")
        if not math.isclose(prob_valid + prob_hoax, 1.0, rel_tol=1e-6, abs_tol=1e-6):
            raise EvaluationError(f"Probabilities do not sum to one for {record_id}")
        expected_prediction = "valid" if prob_valid >= prob_hoax else "hoax"
        if raw["predicted_binary_label"] != expected_prediction:
            raise EvaluationError(f"Argmax mismatch for {record_id}")
        if not math.isclose(confidence, max(prob_valid, prob_hoax), rel_tol=1e-9, abs_tol=1e-9):
            raise EvaluationError(f"Confidence mismatch for {record_id}")
        expected_served, expected_abstained = served_label_for_prediction(
            expected_prediction, confidence, SERVING_THRESHOLD
        )
        abstained = _parse_bool(raw["abstained"], "abstained", record_id)
        if raw["served_label"] != expected_served or abstained != expected_abstained:
            raise EvaluationError(f"Serving threshold mismatch for {record_id}")
        correct = _parse_bool(raw["correct"], "correct", record_id)
        if correct != (raw["true_label"] == expected_prediction):
            raise EvaluationError(f"Correctness mismatch for {record_id}")
        rows.append(
            {
                "id": record_id,
                "text": raw["text"],
                "true_label": raw["true_label"],
                "predicted_binary_label": expected_prediction,
                "error_type": classify_error_type(raw["true_label"], expected_prediction),
                "prob_valid": prob_valid,
                "prob_hoax": prob_hoax,
                "confidence": confidence,
                "served_label": raw["served_label"],
                "abstained": abstained,
                "text_source": raw["text_source"],
                "evidence_source": raw["evidence_source"],
                "evidence_url": raw["evidence_url"],
                "topic": raw["topic"],
                "text_length": len(raw["text"]),
            }
        )

    binary = compute_binary_metrics(rows)
    abstention = compute_abstention_metrics(rows, SERVING_THRESHOLD)
    for key in ("accuracy", "macro_precision", "macro_recall", "macro_f1"):
        if not math.isclose(float(binary[key]), float(binary_evaluation[key]), rel_tol=0.0, abs_tol=1e-12):
            raise EvaluationError(f"Binary metric mismatch: {key}")
    if binary["confusion_matrix"] != binary_evaluation.get("confusion_matrix"):
        raise EvaluationError("Binary confusion matrix does not match predictions.csv")
    for key in (
        "covered_samples",
        "abstained_samples",
        "coverage",
        "abstention_rate",
        "covered_accuracy",
        "covered_macro_precision",
        "covered_macro_recall",
        "covered_macro_f1",
    ):
        actual = abstention[key]
        expected = abstention_evaluation.get(key)
        if isinstance(actual, float):
            if not math.isclose(actual, float(expected), rel_tol=0.0, abs_tol=1e-12):
                raise EvaluationError(f"Abstention metric mismatch: {key}")
        elif actual != expected:
            raise EvaluationError(f"Abstention metric mismatch: {key}")
    return rows


def build_summary(
    dataset: FrozenDataset,
    rows: Sequence[dict[str, Any]],
    binary_evaluation: dict[str, Any],
) -> dict[str, Any]:
    confusion = confusion_counts(rows)
    correct = sum(_is_correct(row) for row in rows)
    incorrect = len(rows) - correct
    if confusion != {
        "true_positive": 55,
        "false_positive": 54,
        "true_negative": 6,
        "false_negative": 5,
    }:
        raise EvaluationError(f"Unexpected frozen evaluation confusion counts: {confusion}")
    if sum(confusion.values()) != len(rows) or correct + incorrect != len(rows):
        raise EvaluationError("Error-analysis totals are inconsistent")
    model_metadata = binary_evaluation.get("model")
    if not isinstance(model_metadata, dict) or model_metadata.get("version") != EXPECTED_MODEL_VERSION:
        raise EvaluationError("Binary evaluation model version is not v1.0.0")
    release_metadata = model_metadata.get("release_metadata")
    if not isinstance(release_metadata, dict):
        release_metadata = {}
    return {
        "dataset": {
            "name": dataset.manifest["dataset_name"],
            "version": dataset.manifest["version"],
            "sha256": dataset.sha256,
            "rows": len(rows),
            "evaluation_only": True,
        },
        "model": {
            "version": model_metadata["version"],
            "serving_threshold": SERVING_THRESHOLD,
        },
        "source_evaluation_time": binary_evaluation.get("evaluated_at"),
        "total": len(rows),
        "correct": correct,
        "incorrect": incorrect,
        "confusion": confusion,
        "per_label": summarize_labels(rows),
        "per_source": summarize_sources(rows),
        "per_topic": summarize_topics(rows),
        "confidence": confidence_summaries(rows),
        "text_length": text_length_summaries(rows),
        "abstention": summarize_abstention(rows),
        "high_confidence_errors": select_high_confidence_errors(rows),
        "low_confidence_correct_predictions": select_low_confidence_correct(rows),
        "performance_context": {
            "internal_held_out_accuracy": release_metadata.get("eval_accuracy"),
            "internal_held_out_macro_f1": release_metadata.get("eval_macro_f1"),
            "external_accuracy": binary_evaluation["accuracy"],
            "external_macro_f1": binary_evaluation["macro_f1"],
        },
    }


def _format_number(value: Any) -> str:
    return "n/a" if value is None else f"{float(value):.6f}"


def _format_stats(stats: dict[str, Any]) -> str:
    return (
        f"n={stats['count']}, mean={_format_number(stats['mean'])}, "
        f"median={_format_number(stats['median'])}, min={stats['min']}, max={stats['max']}"
    )


def _safe_excerpt(text: str, limit: int = 220) -> str:
    normalized = " ".join(text.split()).replace("|", "\\|")
    return normalized if len(normalized) <= limit else normalized[: limit - 1].rstrip() + "…"


def _error_table(rows: Sequence[dict[str, Any]], indexed_rows: dict[str, dict[str, Any]]) -> list[str]:
    lines = [
        "| ID | Sumber | Topik | Confidence | Served | Teks |",
        "| --- | --- | --- | ---: | --- | --- |",
    ]
    for item in rows:
        full = indexed_rows[str(item["id"])]
        lines.append(
            f"| {item['id']} | {item['evidence_source']} | {item['topic']} | "
            f"{_format_number(item['confidence'])} | {item['served_label']} | {_safe_excerpt(full['text'])} |"
        )
    return lines


def render_report(summary: dict[str, Any], rows: Sequence[dict[str, Any]]) -> str:
    confusion = summary["confusion"]
    per_label = summary["per_label"]
    abstention = summary["abstention"]
    performance = summary["performance_context"]
    indexed_rows = {str(row["id"]): row for row in rows}
    lines = [
        "# Analisis Kesalahan External Challenge",
        "",
        "## Ringkasan",
        "",
        f"- Dataset: `external-challenge` v{summary['dataset']['version']} ({summary['total']} sampel).",
        f"- Model: `indobert-hoax` {summary['model']['version']}.",
        f"- Internal held-out: accuracy `{_format_number(performance['internal_held_out_accuracy'])}`, macro F1 `{_format_number(performance['internal_held_out_macro_f1'])}`.",
        f"- External challenge: accuracy `{_format_number(performance['external_accuracy'])}`, macro F1 `{_format_number(performance['external_macro_f1'])}`.",
        f"- Correct `{summary['correct']}`, incorrect `{summary['incorrect']}`.",
        f"- Dengan `hoax` sebagai positive class: TP `{confusion['true_positive']}`, FP `{confusion['false_positive']}`, TN `{confusion['true_negative']}`, FN `{confusion['false_negative']}`.",
        "",
        "## False Positive",
        "",
        f"Terdapat `{confusion['false_positive']}` sampel valid yang diprediksi hoax. Sepuluh false positive dengan confidence tertinggi:",
        "",
    ]
    lines.extend(_error_table(summary["high_confidence_errors"]["false_positive"], indexed_rows))
    lines.extend(
        [
            "",
            "Pola deskriptifnya terlihat pada sumber primer valid: model menghasilkan prediksi hoax pada sebagian besar sampel valid. Analisis ini tidak membuktikan penyebab kausal atau bahwa model mengenali sumber tertentu.",
            "",
            "## False Negative",
            "",
            f"Terdapat `{confusion['false_negative']}` sampel hoax yang diprediksi valid. Seluruh false negative:",
            "",
        ]
    )
    lines.extend(_error_table(summary["high_confidence_errors"]["false_negative"], indexed_rows))
    lines.extend(
        [
            "",
            "Kelima false negative berasal dari AFP Fact Check Indonesia, seluruhnya berstatus `meragukan`, dan confidence-nya berada pada rentang 0.579962–0.867498. Ini adalah pola pada sampel yang tersedia, bukan bukti sebab-akibat sumber.",
            "",
            "## Analisis Sumber",
            "",
            "| Sumber | Total | Correct | Incorrect | Accuracy | Pred valid | Pred hoax | Abstained | Abstention rate | Mean confidence |",
            "| --- | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: |",
        ]
    )
    for source, values in summary["per_source"].items():
        lines.append(
            f"| {source} | {values['total']} | {values['correct']} | {values['incorrect']} | "
            f"{_format_number(values['accuracy'])} | {values['predicted_valid']} | {values['predicted_hoax']} | "
            f"{values['abstained']} | {_format_number(values['abstention_rate'])} | {_format_number(values['mean_confidence'])} |"
        )
    lines.extend(
        [
            "",
            "Performa buruk terlihat terkonsentrasi pada tiga sumber valid, tetapi desain dataset juga mengikat label dengan kelompok sumber. Karena itu hasil ini hanya konsisten dengan kemungkinan bias sumber/domain, bukan bukti bahwa model menghafal sumber.",
            "",
            "## Analisis Label",
            "",
            "| Label | Total | Correct | Incorrect | Accuracy/Recall | Mean confidence | Median confidence | Abstained |",
            "| --- | ---: | ---: | ---: | ---: | ---: | ---: | ---: |",
        ]
    )
    for label in LABEL_ORDER:
        values = per_label[label]
        lines.append(
            f"| {label} | {values['total']} | {values['correct']} | {values['incorrect']} | "
            f"{_format_number(values['accuracy_recall'])} | {_format_number(values['mean_confidence'])} | "
            f"{_format_number(values['median_confidence'])} | {values['abstained']} |"
        )
    lines.extend(
        [
            "",
            "Recall valid (`0.100000`) jauh lebih rendah daripada recall hoax (`0.916667`). Secara langsung, model memprediksi hoax untuk 109 dari 120 sampel. Data ini menunjukkan kecenderungan prediksi pada challenge ini, tetapi tidak sendiri menjelaskan penyebabnya.",
            "",
            "## Analisis Panjang Teks",
            "",
            "Panjang dihitung sebagai jumlah karakter Unicode pada teks asli.",
            "",
            "| Kelompok | Statistik |",
            "| --- | --- |",
        ]
    )
    for group, values in summary["text_length"].items():
        lines.append(f"| {group} | {_format_stats(values)} |")
    lines.extend(
        [
            "",
            "Statistik panjang bersifat eksploratif; tidak dilakukan pencarian batas panjang atau pemilihan subset.",
            "",
            "## Analisis Topik",
            "",
            "| Topik | Total | Correct | Incorrect | Accuracy | Abstained |",
            "| --- | ---: | ---: | ---: | ---: | ---: |",
        ]
    )
    for topic, values in summary["per_topic"].items():
        lines.append(
            f"| {topic} | {values['total']} | {values['correct']} | {values['incorrect']} | "
            f"{_format_number(values['accuracy'])} | {values['abstained']} |"
        )
    lines.extend(
        [
            "",
            "Topik dengan jumlah sampel kecil tidak mendukung kesimpulan performa yang kuat; sample count selalu disertakan.",
            "",
            "## Analisis Confidence",
            "",
            "| Kelompok | Statistik |",
            "| --- | --- |",
        ]
    )
    for group, values in summary["confidence"].items():
        lines.append(f"| {group} | {_format_stats(values)} |")
    lines.extend(
        [
            "",
            "Sepuluh prediksi benar dengan confidence terendah:",
            "",
        ]
    )
    lines.extend(_error_table(summary["low_confidence_correct_predictions"], indexed_rows))
    lines.extend(
        [
            "",
            "## Analisis Abstention",
            "",
            f"- Threshold serving tetap `0.99`; tidak dicari atau diuji threshold alternatif.",
            f"- Total meragukan: `{abstention['total_abstained']}`.",
            f"- Binary correct yang diabstain: `{abstention['binary_correct_abstained']}`.",
            f"- Binary incorrect yang diabstain: `{abstention['binary_incorrect_abstained']}`.",
            f"- Binary correct yang covered: `{abstention['binary_correct_covered']}`.",
            f"- Binary incorrect yang covered: `{abstention['binary_incorrect_covered']}`.",
            f"- Distribusi true label abstention: `{json.dumps(abstention['true_label_distribution'], ensure_ascii=False, sort_keys=True)}`.",
            f"- Distribusi sumber abstention: `{json.dumps(abstention['source_distribution'], ensure_ascii=False, sort_keys=True)}`.",
            f"- Distribusi topik abstention: `{json.dumps(abstention['topic_distribution'], ensure_ascii=False, sort_keys=True)}`.",
            "",
            "Threshold 0.99 menahan prediksi benar dan salah. Perbandingan jumlah di atas bersifat deskriptif dan tidak digunakan untuk optimasi threshold.",
            "",
            "## Temuan Utama",
            "",
            "Observasi langsung dari data:",
            "",
            f"- Terdapat `{summary['incorrect']}` kesalahan dari `{summary['total']}` sampel, didominasi `{confusion['false_positive']}` false positive.",
            "- Recall valid adalah `0.100000`, sedangkan recall hoax `0.916667`.",
            "- Model memprediksi hoax pada 109 sampel dan valid pada 11 sampel.",
            f"- Abstention menahan `{abstention['binary_incorrect_abstained']}` prediksi salah dan `{abstention['binary_correct_abstained']}` prediksi benar.",
            "- Terdapat generalization gap besar antara evaluasi internal held-out dan external challenge.",
            "",
            "Interpretasi yang masih berupa kemungkinan:",
            "",
            "- Hasil external konsisten dengan kemungkinan source/domain shift karena sumber dan gaya teks challenge berbeda dari corpus training.",
            "- Ketimpangan prediksi valid/hoax konsisten dengan kemungkinan bias sumber/domain atau fitur gaya, tetapi analisis ini tidak membuktikan model hanya menghafal sumber.",
            "- Perbedaan confidence antara kelompok benar dan salah dapat menunjukkan confidence yang belum selaras dengan correctness external; tidak dilakukan recalibration.",
            "",
            "## Keterbatasan",
            "",
            "- External challenge hanya berisi 120 sampel, masing-masing 20 per sumber.",
            "- Tidak seluruh data merupakan strict temporal holdout.",
            "- Ground truth valid berasal dari official primary sources, sedangkan ground truth hoax berasal dari artikel fact-check dengan verdict eksplisit.",
            "- Masih terdapat perbedaan panjang dan gaya teks antar kelompok.",
            "- Source/domain challenge berbeda dari Komdigi (hoax) dan ANTARA (valid) pada training corpus.",
            "- Karena label dan kelompok sumber saling terikat, efek label, sumber, topik, dan gaya tidak dapat dipisahkan secara kausal dari analisis ini.",
            "",
            "## Implikasi",
            "",
            "Hasil ini dapat digunakan untuk laporan TA, analisis generalisasi, evaluasi usefulness status `meragukan`, dan dasar perbandingan dengan baseline pada tahap terpisah. Frozen challenge ini tetap evaluation-only dan tidak digunakan untuk training, calibration, threshold tuning, atau model selection.",
        ]
    )
    return "\n".join(lines) + "\n"


def write_error_csv(path: Path, rows: Sequence[dict[str, Any]]) -> None:
    with path.open("w", encoding="utf-8-sig", newline="") as handle:
        writer = csv.DictWriter(handle, fieldnames=ERROR_ANALYSIS_FIELDS, extrasaction="raise", lineterminator="\r\n")
        writer.writeheader()
        for source in rows:
            row = {field: source[field] for field in ERROR_ANALYSIS_FIELDS}
            for field in ("prob_valid", "prob_hoax", "confidence"):
                row[field] = f"{float(row[field]):.12f}"
            row["abstained"] = "1" if row["abstained"] else "0"
            writer.writerow(row)


def build_parser() -> argparse.ArgumentParser:
    base = PROJECT_ROOT / "datasets" / "challenge" / "external-challenge-v1"
    results = base / "results"
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--dataset", type=Path, default=base / "challenge.csv")
    parser.add_argument("--manifest", type=Path, default=base / "manifest.json")
    parser.add_argument("--predictions", type=Path, default=results / "predictions.csv")
    parser.add_argument("--binary-evaluation", type=Path, default=results / "binary_evaluation.json")
    parser.add_argument("--abstention-evaluation", type=Path, default=results / "abstention_evaluation.json")
    parser.add_argument("--output-dir", type=Path, default=results)
    return parser


def run(args: argparse.Namespace) -> dict[str, Any]:
    dataset = validate_frozen_dataset(args.dataset.resolve(), args.manifest.resolve())
    binary_evaluation = _load_json(args.binary_evaluation.resolve(), "binary evaluation")
    abstention_evaluation = _load_json(args.abstention_evaluation.resolve(), "abstention evaluation")
    if binary_evaluation.get("dataset", {}).get("sha256") != dataset.sha256:
        raise EvaluationError("Binary evaluation dataset SHA does not match frozen dataset")
    if abstention_evaluation.get("dataset", {}).get("sha256") != dataset.sha256:
        raise EvaluationError("Abstention evaluation dataset SHA does not match frozen dataset")
    if not math.isclose(float(abstention_evaluation.get("threshold", -1)), SERVING_THRESHOLD, abs_tol=1e-15):
        raise EvaluationError("Abstention artifact threshold is not 0.99")
    rows = load_and_validate_predictions(
        args.predictions.resolve(), dataset, binary_evaluation, abstention_evaluation
    )
    summary = build_summary(dataset, rows, binary_evaluation)

    args.output_dir.mkdir(parents=True, exist_ok=True)
    json_path = args.output_dir / "error_analysis.json"
    csv_path = args.output_dir / "error_analysis.csv"
    report_path = args.output_dir / "error_analysis_report.md"
    json_path.write_text(json.dumps(summary, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    write_error_csv(csv_path, rows)
    report_path.write_text(render_report(summary, rows), encoding="utf-8")
    return {"dataset": dataset, "rows": rows, "summary": summary, "artifacts": [json_path, csv_path, report_path]}


def main(argv: list[str] | None = None) -> int:
    args = build_parser().parse_args(argv)
    try:
        result = run(args)
    except EvaluationError as exc:
        print(f"error analysis blocked: {exc}", file=sys.stderr)
        return 2
    summary = result["summary"]
    print(f"dataset_sha256={result['dataset'].sha256}")
    print(f"total={summary['total']}")
    print(f"correct={summary['correct']}")
    print(f"incorrect={summary['incorrect']}")
    for key, value in summary["confusion"].items():
        print(f"{key}={value}")
    for artifact in result["artifacts"]:
        print(f"wrote={artifact}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
