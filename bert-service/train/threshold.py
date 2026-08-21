from __future__ import annotations

import numpy as np

from train.config import FineTuneConfig
from train.metrics import classification_metrics


def tune_threshold(
    y_true: np.ndarray,
    probs: np.ndarray,
    config: FineTuneConfig,
) -> dict:
    """Tune the ``meragukan`` confidence threshold on VALIDATION ONLY.

    Predictions with confidence < threshold are abstained (reported as
    ``meragukan`` downstream). The threshold maximizes macro-F1 over covered
    predictions while keeping the abstain rate at most
    ``config.max_abstain_rate``.
    """
    confidence = np.max(probs, axis=1)
    predicted = np.argmax(probs, axis=1)
    n = len(y_true)
    if n == 0:
        raise ValueError("cannot tune threshold on an empty set")

    thresholds = np.linspace(config.threshold_min, config.threshold_max, config.threshold_steps)
    best: tuple[float, float, float, float] | None = None
    rows: list[dict] = []
    for tau in thresholds:
        covered = confidence >= tau
        n_covered = int(covered.sum())
        abstain_rate = 1.0 - n_covered / n
        if abstain_rate > config.max_abstain_rate:
            continue
        if n_covered == 0:
            continue
        metrics = classification_metrics(
            y_true[covered], predicted[covered], len(config.labels)
        )
        macro_f1 = metrics["macro_f1"]
        accuracy = metrics["accuracy"]
        candidate = (macro_f1, accuracy, n_covered, float(tau))
        if best is None or candidate > best:
            best = candidate
        rows.append(
            {
                "threshold": round(float(tau), 4),
                "coverage": round(n_covered / n, 4),
                "abstain_rate": round(abstain_rate, 4),
                "accuracy": round(accuracy, 4),
                "macro_f1": round(macro_f1, 4),
            }
        )

    if best is None:
        raise ValueError("no feasible threshold found; relax max_abstain_rate")
    _, accuracy, n_covered, tau = best
    return {
        "threshold": round(tau, 4),
        "accuracy": accuracy,
        "coverage": round(n_covered / n, 4),
        "abstain_rate": round(1.0 - n_covered / n, 4),
        "n_covered": int(n_covered),
        "n_total": int(n),
        "search": rows,
    }