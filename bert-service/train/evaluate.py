from __future__ import annotations

import json
from pathlib import Path

import numpy as np

from train.config import FineTuneConfig
from train.metrics import (
    brier_score,
    classification_metrics,
    expected_calibration_error,
    roc_auc,
)


def _softmax(logits: np.ndarray) -> np.ndarray:
    logits = logits - logits.max(axis=1, keepdims=True)
    exp = np.exp(logits)
    return exp / exp.sum(axis=1, keepdims=True)


def evaluate_checkpoint(
    config: FineTuneConfig,
    checkpoint_dir: Path,
    temperature: float,
    split_file: str = "test.jsonl",
) -> dict:
    """Full held-out evaluation: logits from the best checkpoint, temperature
    scaling applied, then metrics, calibration stats and per-class curves."""
    import torch
    from datasets import load_dataset
    from transformers import AutoModelForSequenceClassification, AutoTokenizer

    torch.set_num_threads(config.num_threads)
    dataset = load_dataset(
        "json", data_dir=str(config.data_dir), data_files={"test": split_file}
    )["test"]
    tokenizer = AutoTokenizer.from_pretrained(config.base_model)
    model = AutoModelForSequenceClassification.from_pretrained(checkpoint_dir)
    model.eval()

    all_logits: list[np.ndarray] = []
    all_labels: list[int] = []
    texts: list[str] = []
    with torch.inference_mode():
        for i in range(0, len(dataset), config.eval_batch_size):
            batch = dataset[i : i + config.eval_batch_size]
            encoded = tokenizer(
                batch["text"],
                truncation=True,
                max_length=config.max_length,
                padding="max_length",
                return_tensors="pt",
            )
            logits = model(**encoded).logits.detach().cpu().numpy()
            all_logits.append(logits)
            all_labels.extend(config.label2id[v] if isinstance(v, str) else int(v) for v in batch["label"])
            texts.extend(batch["text"])
    logits = np.concatenate(all_logits)
    y_true = np.asarray(all_labels)
    probs = _softmax(logits)
    calib_probs = _softmax(logits / temperature)
    predictions = np.argmax(probs, axis=1)

    metrics = classification_metrics(y_true, predictions, len(config.labels))
    report: dict = {
        "n": int(len(y_true)),
        "class_names": config.labels,
        "accuracy": metrics["accuracy"],
        "macro_precision": metrics["macro_precision"],
        "macro_recall": metrics["macro_recall"],
        "macro_f1": metrics["macro_f1"],
        "per_class": {
            name: {
                "precision": metrics["precision"][i],
                "recall": metrics["recall"][i],
                "f1": metrics["f1"][i],
            }
            for i, name in enumerate(config.labels)
        },
        "confusion_matrix": metrics["confusion_matrix"],
        "auc": {
            name: roc_auc(y_true, probs[:, i], positive_label=i)
            for i, name in enumerate(config.labels)
        },
        "calibration": {
            "temperature": temperature,
            "ece_raw": expected_calibration_error(y_true, probs),
            "ece_calibrated": expected_calibration_error(y_true, calib_probs),
            "brier_raw": brier_score(y_true, probs),
            "brier_calibrated": brier_score(y_true, calib_probs),
            "reliability": _reliability_curve(y_true, calib_probs, n_bins=10),
        },
    }
    return report


def _reliability_curve(y_true: np.ndarray, probs: np.ndarray, n_bins: int = 10) -> list[dict]:
    confidence = np.max(probs, axis=1)
    predicted = np.argmax(probs, axis=1)
    correct = (predicted == y_true).astype(float)
    boundaries = np.linspace(0.0, 1.0, n_bins + 1)
    curve = []
    for i in range(n_bins):
        lo, hi = boundaries[i], boundaries[i + 1]
        in_bin = (confidence >= lo) & (confidence <= hi) if i == 0 else (confidence > lo) & (confidence <= hi)
        count = int(in_bin.sum())
        if count == 0:
            continue
        curve.append(
            {
                "confidence": round(float(confidence[in_bin].mean()), 4),
                "accuracy": round(float(correct[in_bin].mean()), 4),
                "count": count,
            }
        )
    return curve


def render_report(config: FineTuneConfig, report: dict, out_dir: Path) -> Path:
    """Write evaluation_report.md, evaluation.json, confusion matrix PNG and
    reliability diagram into ``out_dir``."""
    out_dir.mkdir(parents=True, exist_ok=True)
    (out_dir / "evaluation.json").write_text(
        json.dumps(report, indent=2, ensure_ascii=False), encoding="utf-8"
    )

    import matplotlib

    matplotlib.use("Agg")
    import matplotlib.pyplot as plt

    labels = report["class_names"]
    matrix = np.asarray(report["confusion_matrix"])
    fig, ax = plt.subplots(figsize=(5.5, 4.5))
    im = ax.imshow(matrix, cmap="Blues")
    ax.set_xticks(range(len(labels)), labels)
    ax.set_yticks(range(len(labels)), labels)
    ax.set_xlabel("Predicted")
    ax.set_ylabel("Actual")
    for i in range(len(labels)):
        for j in range(len(labels)):
            ax.text(j, i, str(matrix[i, j]), ha="center", va="center", color="black")
    fig.colorbar(im, ax=ax)
    fig.tight_layout()
    fig.savefig(out_dir / "confusion_matrix.png", dpi=150)
    plt.close(fig)

    calib = report["calibration"]
    # reliability diagram (calibrated probabilities, 10 bins)
    curve = calib.get("reliability", [])
    fig, ax = plt.subplots(figsize=(5.5, 4.5))
    ax.plot([0, 1], [0, 1], "k--", linewidth=1, label="Perfectly calibrated")
    if curve:
        ax.plot(
            [p["confidence"] for p in curve],
            [p["accuracy"] for p in curve],
            "o-",
            linewidth=1.5,
            markersize=4,
            label="Model",
        )
    ax.set_xlabel("Confidence")
    ax.set_ylabel("Accuracy")
    ax.set_title("Reliability diagram (test set)")
    ax.legend()
    fig.tight_layout()
    fig.savefig(out_dir / "reliability_diagram.png", dpi=150)
    plt.close(fig)

    per_class = report["per_class"]
    lines = [
        "# Evaluation Report — %s v%s" % (config.model_name, config.version),
        "",
        "## Held-out test set (n=%d)" % report["n"],
        "",
        "- Accuracy: %.4f" % report["accuracy"],
        "- Macro precision: %.4f" % report["macro_precision"],
        "- Macro recall: %.4f" % report["macro_recall"],
        "- Macro F1: %.4f" % report["macro_f1"],
        "",
        "## Per-class precision/recall/F1",
        "",
        "| class | precision | recall | f1 | auc |",
        "|---|---|---|---|---|",
    ]
    for name in labels:
        p = per_class[name]
        lines.append(
            "| %s | %.4f | %.4f | %.4f | %s |"
            % (name, p["precision"], p["recall"], p["f1"], _fmt_auc(report["auc"][name]))
        )
    lines += [
        "",
        "## Confusion matrix",
        "",
        "```",
    ]
    for row in matrix:
        lines.append("  " + "  ".join(f"{v:6d}" for v in row))
    lines += [
        "```",
        "",
        "![confusion matrix](confusion_matrix.png)",
        "",
        "## Probability calibration (temperature scaling)",
        "",
        "- Temperature: %.4f" % calib["temperature"],
        "- ECE raw -> calibrated: %.4f -> %.4f" % (calib["ece_raw"], calib["ece_calibrated"]),
        "- Brier raw -> calibrated: %.4f -> %.4f"
        % (calib["brier_raw"], calib["brier_calibrated"]),
        "",
        "![reliability diagram](reliability_diagram.png)",
        "",
    ]
    (out_dir / "evaluation_report.md").write_text(
        "\n".join(lines) + "\n", encoding="utf-8"
    )
    return out_dir / "evaluation_report.md"


def _fmt_auc(value: float) -> str:
    return "n/a" if value != value else "%.4f" % value