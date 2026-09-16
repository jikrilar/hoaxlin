"""Run the isolated BERT container contract and D1 equivalence checks."""

from __future__ import annotations

import argparse
import json
import math
import os
import sys
import time
import urllib.error
import urllib.request
from typing import Any

EXPECTED_VERSION = "v1.0.0"
EXPECTED_THRESHOLD = 0.99
EXPECTED_TEMPERATURE = 0.8706620666110391
EXPECTED_LABELS = ["valid", "hoax"]

D1_FIXTURES = (
    {
        "text": "Pemerintah Kota Bandung mengumumkan jadwal pelayanan administrasi baru mulai Senin melalui situs resminya.",
        "label": "hoax",
        "confidence": 0.9993522763252258,
        "raw_scores": {
            "valid": 0.0006477105198428035,
            "hoax": 0.9993522763252258,
        },
    },
    {
        "text": "Beredar pesan berantai yang menyebut semua pengguna WhatsApp harus membayar biaya bulanan mulai besok.",
        "label": "hoax",
        "confidence": 0.9999455213546753,
        "raw_scores": {
            "valid": 5.452663390315138e-05,
            "hoax": 0.9999455213546753,
        },
    },
    {
        "text": "The national statistics agency published its quarterly economic report on Friday.",
        "label": "meragukan",
        "confidence": 0.9861741065979004,
        "raw_scores": {
            "valid": 0.013825835660099983,
            "hoax": 0.9861741065979004,
        },
    },
)


class SmokeTestError(RuntimeError):
    """Raised when the running container violates the D2 contract."""


def request_json(
    base_url: str,
    path: str,
    *,
    token: str | None = None,
    payload: dict[str, Any] | None = None,
) -> tuple[int, dict[str, Any]]:
    data = None
    headers = {"Accept": "application/json"}
    if payload is not None:
        data = json.dumps(payload).encode("utf-8")
        headers["Content-Type"] = "application/json"
    if token is not None:
        headers["Authorization"] = f"Bearer {token}"
    request = urllib.request.Request(
        f"{base_url.rstrip('/')}{path}", data=data, headers=headers
    )
    try:
        with urllib.request.urlopen(request, timeout=10) as response:
            return response.status, json.loads(response.read().decode("utf-8"))
    except urllib.error.HTTPError as exc:
        return exc.code, json.loads(exc.read().decode("utf-8"))


def wait_until_ready(base_url: str, timeout: float) -> dict[str, Any]:
    deadline = time.monotonic() + timeout
    last_status: int | None = None
    while time.monotonic() < deadline:
        try:
            last_status, body = request_json(base_url, "/health/ready")
            if last_status == 200:
                return body
        except (OSError, ValueError):
            pass
        time.sleep(1)
    raise SmokeTestError(
        f"readiness did not reach HTTP 200 within {timeout}s; last={last_status}"
    )


def require(condition: bool, message: str) -> None:
    if not condition:
        raise SmokeTestError(message)


def run(base_url: str, timeout: float, tolerance: float) -> dict[str, Any]:
    token = os.getenv("BERT_INTERNAL_API_TOKEN", "")
    require(bool(token), "BERT_INTERNAL_API_TOKEN is not configured")

    ready = wait_until_ready(base_url, timeout)
    live_status, live = request_json(base_url, "/health/live")
    version_status, version = request_json(base_url, "/version")
    require(live_status == 200 and live.get("status") == "ok", "liveness failed")
    require(version_status == 200, "version endpoint failed")
    require(ready.get("model_version") == EXPECTED_VERSION, "readiness version mismatch")
    require(version.get("model_version") == EXPECTED_VERSION, "model version mismatch")
    require(version.get("model_status") == "ready", "model is not ready")
    require(version.get("model_labels") == EXPECTED_LABELS, "label map mismatch")
    require(version.get("threshold") == EXPECTED_THRESHOLD, "threshold mismatch")
    require(
        math.isclose(
            float(version.get("temperature")),
            EXPECTED_TEMPERATURE,
            rel_tol=0.0,
            abs_tol=1e-15,
        ),
        "temperature mismatch",
    )

    no_token_status, _ = request_json(
        base_url, "/predict", payload={"text": D1_FIXTURES[0]["text"]}
    )
    wrong_token_status, _ = request_json(
        base_url,
        "/predict",
        token="deliberately-invalid-d2-token",
        payload={"text": D1_FIXTURES[0]["text"]},
    )
    require(no_token_status == 401, f"no-token request returned {no_token_status}")
    require(
        wrong_token_status == 401,
        f"wrong-token request returned {wrong_token_status}",
    )

    comparisons = []
    for index, fixture in enumerate(D1_FIXTURES, start=1):
        status, prediction = request_json(
            base_url,
            "/predict",
            token=token,
            payload={"text": fixture["text"]},
        )
        require(status == 200, f"fixture {index} prediction returned HTTP {status}")
        confidence_delta = abs(
            float(prediction["confidence_score"]) - float(fixture["confidence"])
        )
        raw_score_deltas = {
            label: abs(float(prediction["raw_scores"][label]) - expected)
            for label, expected in fixture["raw_scores"].items()
        }
        passed = (
            prediction["label"] == fixture["label"]
            and confidence_delta <= tolerance
            and all(delta <= tolerance for delta in raw_score_deltas.values())
        )
        require(passed, f"fixture {index} differs from the D1 baseline")
        comparisons.append(
            {
                "fixture": index,
                "label": prediction["label"],
                "confidence": prediction["confidence_score"],
                "confidence_delta": confidence_delta,
                "raw_scores": prediction["raw_scores"],
                "raw_score_deltas": raw_score_deltas,
                "result": "PASS",
            }
        )

    return {
        "liveness": "PASS",
        "readiness": "PASS",
        "version": version,
        "authentication": {
            "no_token_status": no_token_status,
            "wrong_token_status": wrong_token_status,
            "correct_token": "PASS",
        },
        "tolerance": tolerance,
        "comparisons": comparisons,
        "result": "PASS",
    }


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--base-url", default="http://127.0.0.1:8001")
    parser.add_argument("--wait-seconds", type=float, default=180.0)
    parser.add_argument("--tolerance", type=float, default=1e-7)
    return parser.parse_args()


def main() -> int:
    args = parse_args()
    if args.wait_seconds <= 0:
        print("ERROR: --wait-seconds must be positive", file=sys.stderr)
        return 2
    if not math.isfinite(args.tolerance) or args.tolerance < 0:
        print("ERROR: --tolerance must be finite and non-negative", file=sys.stderr)
        return 2
    try:
        result = run(args.base_url, args.wait_seconds, args.tolerance)
    except (OSError, ValueError, KeyError, SmokeTestError) as exc:
        print(f"ERROR: {exc}", file=sys.stderr)
        return 1
    print(json.dumps(result, indent=2, ensure_ascii=False))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
