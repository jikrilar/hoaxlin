from __future__ import annotations

import numpy as np


def temperature_scale(logits: np.ndarray, y_true: np.ndarray, seed: int = 42) -> float:
    """Fit a temperature T on VALIDATION logits by minimizing the negative
    log-likelihood of the temperature-scaled softmax (Adam-free gradient
    descent in log-space, no scipy dependency).

    Let T = exp(t); z = logits / T; s = softmax(z). The NLL is
    NLL = -sum log s_y, and dNLL/dt = mean(u_y - sum_c s_c u_c) with
    u = z. Minimizing NLL over t keeps T on a log scale.
    """
    rng = np.random.default_rng(seed)
    log_t = float(np.log(np.clip(rng.normal(1.0, 0.1), 0.5, 2.0)))
    n = len(y_true)
    if n == 0:
        return 1.0
    one_hot = np.zeros_like(logits)
    one_hot[np.arange(n), y_true] = 1.0

    learning_rate = 0.05
    for _ in range(3000):
        z = logits * np.exp(-log_t)
        z -= z.max(axis=1, keepdims=True)
        exp_z = np.exp(z)
        sums = exp_z.sum(axis=1, keepdims=True)
        probs = exp_z / sums
        u_y = np.sum(one_hot * z, axis=1)
        dot = np.sum(probs * z, axis=1)
        gradient = float(np.mean(u_y - dot))
        log_t -= learning_rate * gradient
        log_t = float(np.clip(log_t, np.log(0.05), np.log(20.0)))
    return float(np.exp(log_t))


def apply_temperature(logits: np.ndarray, temperature: float) -> np.ndarray:
    scaled = logits / temperature
    scaled -= scaled.max(axis=1, keepdims=True)
    exp = np.exp(scaled)
    return exp / exp.sum(axis=1, keepdims=True)