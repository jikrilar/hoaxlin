from __future__ import annotations

import json
from pathlib import Path

from app.config import Settings
from app.inference import ModelRuntime


def _runtime(tmp_path: Path, evaluation: object, *, manifest_version: str = "v9.9.9") -> ModelRuntime:
    source = tmp_path / "model"
    source.mkdir()
    (source / "evaluation.json").write_text(json.dumps(evaluation), encoding="utf-8")
    (source / "manifest.json").write_text(
        json.dumps({"version": manifest_version, "provenance": {"exported_at": "2026-01-02T00:00:00+00:00"}}),
        encoding="utf-8",
    )
    settings = Settings(
        service_name="test",
        service_version="0.1.0",
        model_path=str(source),
        model_version=manifest_version,
        internal_api_token="test-token",
        max_concurrency=1,
        max_text_length=1000,
        max_sequence_length=512,
        local_files_only=True,
    )
    runtime = ModelRuntime(settings)
    runtime.model_version = manifest_version
    return runtime


def test_evaluation_metadata_is_versioned_and_safe(tmp_path: Path) -> None:
    runtime = _runtime(
        tmp_path,
        {
            "model_version": "v9.9.9",
            "accuracy": 0.91,
            "macro_f1": 0.89,
            "n": 42,
            "dataset_name": "fixture",
            "dataset_version": "v2",
            "split": "held-out test",
        },
    )

    runtime._load_evaluation_metadata()

    assert runtime.evaluation_metadata["model_version"] == "v9.9.9"
    assert runtime.evaluation_metadata["accuracy"] == 0.91
    assert runtime.evaluation_metadata["sample_count"] == 42
    assert runtime.evaluation_metadata["dataset_name"] == "fixture"
    assert runtime.evaluation_metadata["exported_at"] == "2026-01-02T00:00:00+00:00"
    assert "path" not in json.dumps(runtime.evaluation_metadata).lower()


def test_mismatched_evaluation_artifact_is_not_reported_as_active(tmp_path: Path) -> None:
    runtime = _runtime(
        tmp_path,
        {"model_version": "v1.0.0", "accuracy": 1.0, "n": 100},
    )

    runtime._load_evaluation_metadata()

    assert runtime.evaluation_metadata == {}


def test_malformed_optional_evaluation_does_not_fail_runtime(tmp_path: Path) -> None:
    runtime = _runtime(tmp_path, {"accuracy": "secret-not-a-number", "n": -1})
    (Path(runtime.settings.model_path) / "evaluation.json").write_text("not-json", encoding="utf-8")

    runtime._load_evaluation_metadata()

    assert runtime.evaluation_metadata == {}


def test_malformed_numeric_evaluation_does_not_fail_runtime(tmp_path: Path) -> None:
    runtime = _runtime(
        tmp_path,
        {"accuracy": float("inf"), "macro_f1": float("nan"), "n": float("inf")},
    )

    runtime._load_evaluation_metadata()

    assert runtime.evaluation_metadata["accuracy"] is None
    assert runtime.evaluation_metadata["macro_f1"] is None
    assert runtime.evaluation_metadata["sample_count"] is None
