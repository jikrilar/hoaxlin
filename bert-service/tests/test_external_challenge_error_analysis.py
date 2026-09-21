from __future__ import annotations

from scripts.analyze_external_challenge import (
    classify_error_type,
    confusion_counts,
    select_high_confidence_errors,
    select_low_confidence_correct,
    summarize_abstention,
    summarize_sources,
)


def _row(
    record_id: str,
    true_label: str,
    predicted_label: str,
    confidence: float,
    *,
    source: str = "Source A",
    abstained: bool = False,
) -> dict[str, object]:
    error_type = classify_error_type(true_label, predicted_label)
    return {
        "id": record_id,
        "true_label": true_label,
        "predicted_binary_label": predicted_label,
        "error_type": error_type,
        "confidence": confidence,
        "prob_valid": confidence if predicted_label == "valid" else 1 - confidence,
        "prob_hoax": confidence if predicted_label == "hoax" else 1 - confidence,
        "served_label": "meragukan" if abstained else predicted_label,
        "abstained": abstained,
        "evidence_source": source,
        "topic": "fixture",
        "text_length": 50,
    }


def test_error_type_uses_hoax_as_positive_class() -> None:
    assert classify_error_type("valid", "valid") == "correct"
    assert classify_error_type("hoax", "hoax") == "correct"
    assert classify_error_type("valid", "hoax") == "false_positive"
    assert classify_error_type("hoax", "valid") == "false_negative"


def test_confusion_aggregation_is_unambiguous() -> None:
    rows = [
        _row("tp", "hoax", "hoax", 0.9),
        _row("fp", "valid", "hoax", 0.8),
        _row("tn", "valid", "valid", 0.7),
        _row("fn", "hoax", "valid", 0.6),
    ]

    assert confusion_counts(rows) == {
        "true_positive": 1,
        "false_positive": 1,
        "true_negative": 1,
        "false_negative": 1,
    }


def test_source_summary_counts_predictions_and_abstention() -> None:
    rows = [
        _row("a", "valid", "valid", 0.8, abstained=True),
        _row("b", "valid", "hoax", 0.9),
        _row("c", "hoax", "hoax", 1.0, source="Source B"),
    ]

    summary = summarize_sources(rows)

    assert summary["Source A"] == {
        "total": 2,
        "correct": 1,
        "incorrect": 1,
        "accuracy": 0.5,
        "predicted_valid": 1,
        "predicted_hoax": 1,
        "abstained": 1,
        "abstention_rate": 0.5,
        "mean_confidence": 0.8500000000000001,
    }
    assert summary["Source B"]["accuracy"] == 1.0


def test_abstention_summary_splits_correct_and_incorrect() -> None:
    rows = [
        _row("a", "valid", "valid", 0.8, abstained=True),
        _row("b", "valid", "hoax", 0.9, abstained=True),
        _row("c", "hoax", "hoax", 1.0),
        _row("d", "hoax", "valid", 1.0),
    ]

    summary = summarize_abstention(rows)

    assert summary["total_abstained"] == 2
    assert summary["binary_correct_abstained"] == 1
    assert summary["binary_incorrect_abstained"] == 1
    assert summary["binary_correct_covered"] == 1
    assert summary["binary_incorrect_covered"] == 1


def test_confidence_selection_order_is_deterministic() -> None:
    rows = [
        _row("fp-low", "valid", "hoax", 0.7),
        _row("fp-high-b", "valid", "hoax", 0.99),
        _row("fp-high-a", "valid", "hoax", 0.99),
        _row("fn", "hoax", "valid", 0.8),
        _row("correct-high", "hoax", "hoax", 0.95),
        _row("correct-low-b", "valid", "valid", 0.55),
        _row("correct-low-a", "valid", "valid", 0.55),
    ]

    errors = select_high_confidence_errors(rows)
    low_correct = select_low_confidence_correct(rows)

    assert [row["id"] for row in errors["false_positive"]] == ["fp-high-a", "fp-high-b", "fp-low"]
    assert [row["id"] for row in errors["false_negative"]] == ["fn"]
    assert [row["id"] for row in low_correct] == ["correct-low-a", "correct-low-b", "correct-high"]
