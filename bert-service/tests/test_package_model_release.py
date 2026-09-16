from __future__ import annotations

import hashlib
import json
from pathlib import Path

import pytest

from scripts.package_model_release import (
    EXPORT_FILES,
    PackagingError,
    package_model,
    validate_release,
)
from app.config import Settings
from app.inference import ModelRuntime


def _sha256(path: Path) -> str:
    return hashlib.sha256(path.read_bytes()).hexdigest()


def _write_source(root: Path) -> Path:
    source = root / "indobert-hoax" / "v9.9.9"
    source.mkdir(parents=True)
    (source / "model.safetensors").write_bytes(b"synthetic test weight bytes")
    (source / "config.json").write_text(
        json.dumps(
            {
                "architectures": ["BertForSequenceClassification"],
                "num_labels": 2,
                "id2label": {"0": "valid", "1": "hoax"},
                "label2id": {"valid": 0, "hoax": 1},
            }
        ),
        encoding="utf-8",
    )
    (source / "tokenizer.json").write_text("{}", encoding="utf-8")
    (source / "tokenizer_config.json").write_text("{}", encoding="utf-8")
    (source / "threshold.json").write_text(
        json.dumps({"threshold": 0.99}), encoding="utf-8"
    )
    (source / "calibration.json").write_text(
        json.dumps(
            {
                "temperature": 0.8706620666110391,
                "method": "temperature_scaling",
            }
        ),
        encoding="utf-8",
    )
    (source / "evaluation.json").write_text("{}", encoding="utf-8")
    (source / "MODEL_CARD.md").write_text("# Synthetic test model\n", encoding="utf-8")

    manifest = [
        {
            "version": "9.9.9",
            "model_name": "indobert-hoax",
            "base_model": "verified/test-base",
            "labels": ["valid", "hoax"],
            "exported_at": "2026-01-01T00:00:00+00:00",
            "from_checkpoint": r"C:\training\checkpoint-1",
            "temperature": 0.8706620666110391,
            "threshold": 0.99,
            "files": {name: _sha256(source / name) for name in EXPORT_FILES},
        }
    ]
    (source.parent / "manifest.json").write_text(
        json.dumps(manifest), encoding="utf-8"
    )
    return source


def test_package_is_self_contained_and_detects_corruption(tmp_path: Path):
    source = _write_source(tmp_path / "source")
    source_hashes = {name: _sha256(source / name) for name in EXPORT_FILES}
    output = tmp_path / "release" / "v9.9.9"

    package_model(source, output, "v9.9.9")

    assert {path.name for path in output.iterdir()} == set(EXPORT_FILES) | {
        "manifest.json"
    }
    assert {name: _sha256(source / name) for name in EXPORT_FILES} == source_hashes
    assert validate_release(output)["version"] == "v9.9.9"
    assert "C:\\training" not in (output / "manifest.json").read_text(encoding="utf-8")

    (output / "threshold.json").write_text(
        json.dumps({"threshold": 0.98}), encoding="utf-8"
    )
    with pytest.raises(PackagingError, match="Checksum mismatch"):
        validate_release(output)


def test_release_manifest_is_required_and_version_is_exact(tmp_path: Path):
    source = _write_source(tmp_path / "source")
    output = tmp_path / "release" / "v9.9.9"
    package_model(source, output, "v9.9.9")

    with pytest.raises(PackagingError, match="version must be 'v1.0.0'"):
        validate_release(output, expected_version="v1.0.0")

    (output / "manifest.json").unlink()
    with pytest.raises(PackagingError, match="manifest.json"):
        validate_release(output)


def test_runtime_strict_mode_rejects_source_without_internal_manifest(
    tmp_path: Path,
):
    source = _write_source(tmp_path / "source")
    settings = Settings(
        service_name="test",
        service_version="0.1.0",
        model_path=str(source),
        model_version="v9.9.9",
        internal_api_token="temporary-test-token",
        max_concurrency=1,
        max_text_length=1_000,
        max_sequence_length=512,
        local_files_only=True,
        require_release_manifest=True,
    )

    runtime = ModelRuntime(settings)
    runtime.load()

    assert runtime.status == "failed"
    assert runtime.load_error is not None
    assert "manifest.json" in runtime.load_error
