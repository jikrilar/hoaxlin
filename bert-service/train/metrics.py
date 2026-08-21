from __future__ import annotations

import numpy as np


def confusion_matrix(
    y_true: np.ndarray, y_pred: np.ndarray, n_classes: int
) -> np.ndarray:
    matrix = np.zeros((n_classes, n_classes), dtype=int)
    np.add.at(matrix, (y_true, y_pred), 1)
    return matrix


def classification_metrics(
    y_true: np.ndarray, y_pred: np.ndarray, n_classes: int
) -> dict[str, float]:
    """Accuracy plus per-class and macro precision/recall/F1 (numpy only)."""
    matrix = confusion_matrix(y_true, y_pred, n_classes)
    accuracy = float(np.mean(y_true == y_pred))
    tp = np.diag(matrix).astype(float)
    fp = matrix.sum(axis=0) - tp
    fn = matrix.sum(axis=1) - tp
    precision = np.where(tp + fp > 0, tp / np.maximum(tp + fp, 1), 0.0)
    recall = np.where(tp + fn > 0, tp / np.maximum(tp + fn, 1), 0.0)
    f1 = np.where(
        precision + recall > 0,
        2 * precision * recall / np.maximum(precision + recall, 1e-9),
        0.0,
    )
    return {
        "accuracy": accuracy,
        "precision": [float(v) for v in precision],
        "recall": [float(v) for v in recall],
        "f1": [float(v) for v in f1],
        "macro_precision": float(np.mean(precision)),
        "macro_recall": float(np.mean(recall)),
        "macro_f1": float(np.mean(f1)),
        "confusion_matrix": matrix.tolist(),
    }


def roc_auc(y_true: np.ndarray, scores: np.ndarray, positive_label: int = 1) -> float:
    """Binary ROC-AUC for one class (handles ties via rank averaging)."""
    labels = (y_true == positive_label).astype(int)
    if labels.min() == labels.max():
        return float("nan")
    order = np.argsort(scores, kind="stable")
    sorted_scores = scores[order]
    sorted_labels = labels[order]
    n_pos = int(sorted_labels.sum())
    n_neg = int(len(labels)) - n_pos
    if n_pos == 0 or n_neg == 0:
        return float("nan")
    ranks = np.empty_like(sorted_scores, dtype=float)
    i = 0
    while i < len(sorted_scores):
        j = i
        while j < len(sorted_scores) and sorted_scores[j] == sorted_scores[i]:
            j += 1
        ranks[i:j] = (i + j - 1) / 2.0 + 1
        i = j
    return float((ranks[sorted_labels == 1].sum() - n_pos * (n_pos + 1) / 2) / (n_pos * n_neg))


def expected_calibration_error(
    y_true: np.ndarray, probs: np.ndarray, n_bins: int = 10
) -> float:
    """ECE over the winning-class probability (standard 10-bin scheme)."""
    confidence = np.max(probs, axis=1)
    predicted = np.argmax(probs, axis=1)
    correct = (predicted == y_true).astype(float)
    boundaries = np.linspace(0.0, 1.0, n_bins + 1)
    total = len(y_true)
    if total == 0:
        return 0.0
    ece = 0.0
    for i in range(n_bins):
        lo, hi = boundaries[i], boundaries[i + 1]
        in_bin = (confidence > lo) & (confidence <= hi)
        if i == 0:
            in_bin = (confidence >= lo) & (confidence <= hi)
        count = int(in_bin.sum())
        if count == 0:
            continue
        acc = float(correct[in_bin].mean())
        conf = float(confidence[in_bin].mean())
        ece += count / total * abs(acc - conf)
    return float(ece)


def brier_score(y_true: np.ndarray, probs: np.ndarray) -> float:
    """Multi-class Brier score."""
    n = len(y_true)
    if n == 0:
        return float("nan")
    one_hot = np.zeros_like(probs)
    one_hot[np.arange(n), y_true] = 1.0
    return float(np.mean(np.sum((probs - one_hot) ** 2, axis=1)))