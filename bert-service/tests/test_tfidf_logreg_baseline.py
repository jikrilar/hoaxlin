from __future__ import annotations

import csv
from pathlib import Path

import numpy as np
import pytest

from scripts.evaluate_tfidf_logreg_baseline import (
    EXTERNAL_PREDICTION_FIELDS,
    ID_TO_LABEL,
    LABEL_TO_ID,
    DatasetSplit,
    compute_metrics,
    evaluate_split,
    train_baseline,
    write_predictions,
)


def _split(name: str, rows: list[tuple[str, str, str]]) -> DatasetSplit:
    materialized = tuple(
        {
            "id": record_id,
            "text": text,
            "label": label,
            "text_source": "fixture",
            "source": "fixture",
            "url": f"https://example.test/{record_id}",
            "evidence_source": "fixture",
            "evidence_url": f"https://example.test/{record_id}",
            "topic": "fixture",
        }
        for record_id, text, label in rows
    )
    return DatasetSplit(name=name, path=Path(f"{name}.fixture"), rows=materialized, sha256="fixture")


def _training_split() -> DatasetSplit:
    return _split(
        "train",
        [
            ("valid-1", "laporan resmi ekonomi tumbuh stabil", "valid"),
            ("valid-2", "data resmi cuaca telah diterbitkan", "valid"),
            ("valid-3", "bank mengumumkan kebijakan resmi", "valid"),
            ("hoax-1", "klaim palsu bohong beredar luas", "hoax"),
            ("hoax-2", "informasi palsu tanpa bukti", "hoax"),
            ("hoax-3", "narasi bohong menyesatkan masyarakat", "hoax"),
        ],
    )


def test_label_mapping_is_fixed_and_binary() -> None:
    assert LABEL_TO_ID == {"valid": 0, "hoax": 1}
    assert ID_TO_LABEL == {0: "valid", 1: "hoax"}


def test_metric_calculation_and_confusion_ordering() -> None:
    metrics = compute_metrics([0, 0, 1, 1], [0, 1, 1, 0])

    assert metrics["accuracy"] == 0.5
    assert metrics["macro_precision"] == 0.5
    assert metrics["macro_recall"] == 0.5
    assert metrics["macro_f1"] == 0.5
    assert metrics["label_order"] == ["valid", "hoax"]
    assert metrics["confusion_matrix"] == [[1, 1], [1, 1]]
    assert metrics["confusion_axes"] == {"rows": "actual", "columns": "predicted"}


def test_training_fit_boundary_excludes_external_text() -> None:
    train = _training_split()
    external = _split(
        "external",
        [("external-1", "externalonlytoken sumber berbeda", "valid")],
    )
    trained = train_baseline(train)
    vectorizer = trained.pipeline.named_steps["tfidf"]
    classifier = trained.pipeline.named_steps["logreg"]
    coefficients_before = classifier.coef_.copy()

    assert trained.fit_rows == len(train.rows)
    assert "externalonlytoken" not in vectorizer.vocabulary_

    evaluate_split(trained, external)

    assert "externalonlytoken" not in vectorizer.vocabulary_
    np.testing.assert_array_equal(classifier.coef_, coefficients_before)


def test_predictions_follow_argmax_and_probabilities_sum_to_one() -> None:
    trained = train_baseline(_training_split())
    evaluation = _split(
        "test",
        [
            ("test-valid", "laporan resmi telah diterbitkan", "valid"),
            ("test-hoax", "klaim palsu tanpa bukti", "hoax"),
        ],
    )

    metrics, predictions = evaluate_split(trained, evaluation)

    assert metrics["samples"] == 2
    for prediction in predictions:
        assert prediction["prob_valid"] + prediction["prob_hoax"] == pytest.approx(1.0)
        expected = "valid" if prediction["prob_valid"] >= prediction["prob_hoax"] else "hoax"
        assert prediction["predicted_label"] == expected
        assert prediction["correct"] == (prediction["true_label"] == expected)


def test_external_prediction_artifact_schema(tmp_path: Path) -> None:
    trained = train_baseline(_training_split())
    external = _split("external", [("external-1", "data resmi terbaru", "valid")])
    _, predictions = evaluate_split(trained, external)
    output = tmp_path / "predictions.csv"

    write_predictions(output, predictions, external=True)

    with output.open(encoding="utf-8-sig", newline="") as handle:
        reader = csv.DictReader(handle)
        rows = list(reader)
    assert tuple(reader.fieldnames or ()) == EXTERNAL_PREDICTION_FIELDS
    assert len(rows) == 1
    assert rows[0]["id"] == "external-1"
    assert rows[0]["text"] == external.rows[0]["text"]


def test_training_is_reproducible_with_fixed_configuration() -> None:
    train = _training_split()
    evaluation = _split(
        "test",
        [
            ("test-1", "laporan ekonomi resmi", "valid"),
            ("test-2", "narasi palsu menyesatkan", "hoax"),
        ],
    )

    first = train_baseline(train)
    second = train_baseline(train)
    _, first_predictions = evaluate_split(first, evaluation)
    _, second_predictions = evaluate_split(second, evaluation)

    first_probabilities = [(row["prob_valid"], row["prob_hoax"]) for row in first_predictions]
    second_probabilities = [(row["prob_valid"], row["prob_hoax"]) for row in second_predictions]
    np.testing.assert_allclose(first_probabilities, second_probabilities, rtol=0.0, atol=1e-12)
