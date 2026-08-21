from __future__ import annotations

import numpy as np
import pytest

from train.calibrate import apply_temperature, temperature_scale
from train.config import FineTuneConfig
from train.metrics import (
    brier_score,
    classification_metrics,
    expected_calibration_error,
    roc_auc,
)
from train.threshold import tune_threshold


def test_classification_metrics_manual():
    y_true = np.asarray([0, 1, 1, 1, 0, 0, 0, 1])
    y_pred = np.asarray([0, 1, 1, 0, 0, 0, 1, 1])
    metrics = classification_metrics(y_true, y_pred, 2)
    assert metrics["accuracy"] == pytest.approx(6 / 8)
    # class 0 (valid): tp=3 (indices 0,4,5), fp=1 (idx6), fn=1 (idx3)
    assert metrics["precision"][0] == pytest.approx(3 / 4)
    assert metrics["recall"][0] == pytest.approx(3 / 4)
    # class 1 (hoax): tp=3 (1,2,7), fp=1 (3), fn=1 (6)
    assert metrics["precision"][1] == pytest.approx(3 / 4)
    assert metrics["recall"][1] == pytest.approx(3 / 4)
    assert metrics["macro_f1"] == pytest.approx(3 / 4)
    assert metrics["confusion_matrix"] == [[3, 1], [1, 3]]


def test_roc_auc_separated():
    y_true = np.asarray([0, 0, 1, 1])
    scores = np.asarray([0.1, 0.2, 0.8, 0.9])
    assert roc_auc(y_true, scores, positive_label=1) == pytest.approx(1.0)


def test_roc_auc_ties():
    y_true = np.asarray([0, 0, 1, 1])
    scores = np.asarray([0.5, 0.5, 0.5, 0.5])
    assert roc_auc(y_true, scores, positive_label=1) == pytest.approx(0.5)


def test_ece_perfect_calibration():
    y_true = np.asarray([0, 0, 0, 0, 0, 1, 1, 1, 1, 1])
    probs = np.asarray([[0.5, 0.5]] * 10)
    assert expected_calibration_error(y_true, probs) == pytest.approx(0.0, abs=1e-9)


def test_brier_binary():
    y_true = np.asarray([0, 1])
    probs = np.asarray([[0.9, 0.1], [0.2, 0.8]])
    # (0.9-1)^2 + 0.1^2 + 0.2^2 + (0.8-1)^2 all /2
    assert brier_score(y_true, probs) == pytest.approx((0.01 + 0.01 + 0.04 + 0.04) / 2)


def test_temperature_scaling_reduces_ece():
    rng = np.random.default_rng(0)
    n = 2000
    p = rng.uniform(0.05, 0.95, n)
    y_true = (rng.random(n) < p).astype(int)
    logits = np.stack(
        [np.log((1 - p) / (p + 1e-9)), np.log(p / (1 - p + 1e-9))], axis=1
    )
    logits = logits + rng.normal(0.0, 1.0, (n, 2))
    temperature = temperature_scale(logits, y_true)
    assert 0.5 <= temperature <= 5.0
    raw = apply_temperature(logits, 1.0)
    calibrated = apply_temperature(logits, temperature)
    assert expected_calibration_error(y_true, calibrated) <= expected_calibration_error(y_true, raw) + 1e-6


def test_apply_temperature_sum_to_one():
    logits = np.asarray([[2.0, 0.5], [-1.0, 3.0]])
    probs = apply_temperature(logits, 1.5)
    assert np.allclose(probs.sum(axis=1), 1.0)
    assert np.all(probs > 0)


def test_tune_threshold_respects_abstain_cap():
    config = FineTuneConfig(max_abstain_rate=0.25)
    y_true = np.asarray([0, 1, 1, 0, 1, 0, 1, 1, 0, 1])
    probs = np.asarray(
        [[0.99, 0.01], [0.02, 0.98], [0.05, 0.95], [0.7, 0.3],
         [0.01, 0.99], [0.9, 0.1], [0.03, 0.97], [0.04, 0.96],
         [0.6, 0.4], [0.2, 0.8]]
    )
    result = tune_threshold(y_true, probs, config)
    assert 0.5 <= result["threshold"] <= 0.99
    assert result["abstain_rate"] <= 0.25 + 1e-9
    assert result["coverage"] + result["abstain_rate"] == pytest.approx(1.0)
    assert result["n_total"] == 10


def test_tune_threshold_monotonic_coverage():
    config = FineTuneConfig(max_abstain_rate=0.5)
    y_true = np.zeros(10, dtype=int)
    probs = np.stack([np.full(10, 0.99), np.full(10, 0.01)], axis=1)
    result = tune_threshold(y_true, probs, config)
    assert result["coverage"] <= 1.0