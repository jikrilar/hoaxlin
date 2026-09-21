from __future__ import annotations

import csv
import hashlib
import json
from pathlib import Path
import sys

import pytest

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))

from scripts.evaluate_external_challenge import (
    EXPECTED_SOURCES,
    PREDICTION_FIELDS,
    EvaluationError,
    binary_label_from_probabilities,
    compute_abstention_metrics,
    compute_binary_metrics,
    served_label_for_prediction,
    validate_frozen_dataset,
)


def _write_frozen_fixture(tmp_path: Path, *, mutate: dict[str, str] | None = None) -> tuple[Path, Path]:
    dataset_path = tmp_path / "challenge.csv"
    manifest_path = tmp_path / "manifest.json"
    fields = [
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
    ]
    rows: list[dict[str, str]] = []
    for source_index, source in enumerate(EXPECTED_SOURCES):
        label = "hoax" if source_index < 3 else "valid"
        for index in range(20):
            row = {
                "id": f"fixture-{source_index}-{index}",
                "text": f"Unique fixture statement {source_index} {index} with enough text.",
                "label": label,
                "text_source": "claim" if label == "hoax" else "primary_factual_statement",
                "evidence_source": source,
                "evidence_url": f"https://example.test/{source_index}/{index}",
                "published_at": "2026-09-01",
                "topic": "fixture",
                "verification_method": "fact_check" if label == "hoax" else "primary_source",
                "notes": "fixture",
                "approved": "1",
            }
            rows.append(row)
    for key, value in (mutate or {}).items():
        rows[0][key] = value
    with dataset_path.open("w", encoding="utf-8-sig", newline="") as handle:
        writer = csv.DictWriter(handle, fieldnames=fields, lineterminator="\r\n")
        writer.writeheader()
        writer.writerows(rows)
    digest = hashlib.sha256(dataset_path.read_bytes()).hexdigest()
    manifest = {
        "dataset_name": "external-challenge",
        "version": "1.0.0",
        "rows": 120,
        "labels": {"hoax": 60, "valid": 60},
        "sources": {source: 20 for source in EXPECTED_SOURCES},
        "file": {"sha256": digest},
        "quality": {
            "exact_duplicates": 0,
            "training_overlap_at_or_above_threshold": 0,
            "challenge_overlap_at_or_above_threshold": 0,
            "verdict_leakage": 0,
        },
        "usage_policy": {"evaluation_only": True},
    }
    manifest_path.write_text(json.dumps(manifest), encoding="utf-8")
    return dataset_path, manifest_path


def test_sha_mismatch_blocks_frozen_dataset(tmp_path: Path) -> None:
    dataset_path, manifest_path = _write_frozen_fixture(tmp_path)
    manifest = json.loads(manifest_path.read_text(encoding="utf-8"))
    manifest["file"]["sha256"] = "0" * 64
    manifest_path.write_text(json.dumps(manifest), encoding="utf-8")

    with pytest.raises(EvaluationError, match="SHA-256"):
        validate_frozen_dataset(dataset_path, manifest_path)


def test_invalid_label_blocks_frozen_dataset(tmp_path: Path) -> None:
    dataset_path, manifest_path = _write_frozen_fixture(tmp_path, mutate={"label": "meragukan"})

    with pytest.raises(EvaluationError, match="label counts|unsupported label"):
        validate_frozen_dataset(dataset_path, manifest_path)


def test_binary_prediction_uses_argmax_and_valid_wins_ties() -> None:
    assert binary_label_from_probabilities(0.8, 0.2) == "valid"
    assert binary_label_from_probabilities(0.2, 0.8) == "hoax"
    assert binary_label_from_probabilities(0.5, 0.5) == "valid"


def test_binary_metrics_use_two_class_ground_truth() -> None:
    rows = [
        {"true_label": "valid", "predicted_binary_label": "valid"},
        {"true_label": "valid", "predicted_binary_label": "hoax"},
        {"true_label": "hoax", "predicted_binary_label": "hoax"},
        {"true_label": "hoax", "predicted_binary_label": "valid"},
    ]
    metrics = compute_binary_metrics(rows)

    assert metrics["label_order"] == ["valid", "hoax"]
    assert metrics["confusion_matrix"] == [[1, 1], [1, 1]]
    assert metrics["accuracy"] == pytest.approx(0.5)
    assert metrics["macro_f1"] == pytest.approx(0.5)


def test_abstention_does_not_create_a_ground_truth_third_class() -> None:
    rows = [
        {"true_label": "valid", "predicted_binary_label": "valid", "confidence": 0.995},
        {"true_label": "hoax", "predicted_binary_label": "valid", "confidence": 0.4},
    ]
    served, abstained = served_label_for_prediction("valid", 0.4, 0.99)
    metrics = compute_abstention_metrics(rows, 0.99)

    assert (served, abstained) == ("meragukan", True)
    assert metrics["covered_samples"] == 1
    assert metrics["abstained_samples"] == 1
    assert metrics["label_order"] == ["valid", "hoax"]
    assert "meragukan" not in metrics["label_order"]


def test_prediction_schema_contains_required_provenance_fields() -> None:
    assert {
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
    }.issubset(PREDICTION_FIELDS)
