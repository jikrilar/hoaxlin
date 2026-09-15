"""Strict validation for self-contained Hoaxlin BERT model releases."""

from __future__ import annotations

import hashlib
import json
import math
import re
from pathlib import Path
from typing import Any

MODEL_IDENTIFIER = "indobert-hoax"
TASK = "text-classification"
ARCHITECTURE = "BertForSequenceClassification"
EXPECTED_ID2LABEL = {0: "valid", 1: "hoax"}
EXPECTED_LABEL2ID = {"valid": 0, "hoax": 1}
EXPECTED_LABELS = ["valid", "hoax"]
EXPECTED_NUM_LABELS = 2
EXPECTED_THRESHOLD = 0.99
EXPECTED_TEMPERATURE = 0.8706620666110391

CRITICAL_FILES = (
    "model.safetensors",
    "config.json",
    "tokenizer.json",
    "tokenizer_config.json",
    "threshold.json",
    "calibration.json",
)
EXPORT_FILES = CRITICAL_FILES + ("evaluation.json", "MODEL_CARD.md")

# A new release must be reviewed before its weight hash is frozen here.
KNOWN_MODEL_HASHES = {
    "v1.0.0": "0fea71d7d5fd18c61f4e854d1059bb19ce62e3ec3f573718166417e18f2a1e40",
}


class PackagingError(RuntimeError):
    """Raised when a source or packaged release violates the contract."""


def sha256(path: Path) -> str:
    digest = hashlib.sha256()
    with path.open("rb") as handle:
        for chunk in iter(lambda: handle.read(1 << 20), b""):
            digest.update(chunk)
    return digest.hexdigest()


def load_json(path: Path) -> Any:
    try:
        return json.loads(path.read_text(encoding="utf-8"))
    except (OSError, UnicodeError, json.JSONDecodeError) as exc:
        raise PackagingError(f"Invalid JSON file {path}: {exc}") from exc


def require_regular_file(directory: Path, name: str) -> Path:
    path = directory / name
    if not path.is_file() or path.is_symlink():
        raise PackagingError(f"Required regular file is missing: {path}")
    return path


def _required_number(data: Any, key: str, source: str) -> float:
    value = data.get(key) if isinstance(data, dict) else None
    if not isinstance(value, (int, float)) or isinstance(value, bool):
        raise PackagingError(f"{source} {key} must be numeric, got {value!r}")
    number = float(value)
    if not math.isfinite(number):
        raise PackagingError(f"{source} {key} must be finite, got {value!r}")
    return number


def validate_model_contract(directory: Path) -> dict[str, Any]:
    for name in CRITICAL_FILES:
        require_regular_file(directory, name)

    config = load_json(directory / "config.json")
    if not isinstance(config, dict):
        raise PackagingError("config.json must contain an object")
    architectures = config.get("architectures")
    if not isinstance(architectures, list) or ARCHITECTURE not in architectures:
        raise PackagingError(
            f"Expected architecture {ARCHITECTURE}, got {architectures!r}"
        )
    if config.get("num_labels") != EXPECTED_NUM_LABELS:
        raise PackagingError(
            f"Expected num_labels={EXPECTED_NUM_LABELS}, "
            f"got {config.get('num_labels')!r}"
        )

    raw_id2label = config.get("id2label")
    if not isinstance(raw_id2label, dict):
        raise PackagingError("config.json id2label must be an object")
    try:
        id2label = {int(key): str(value) for key, value in raw_id2label.items()}
    except (TypeError, ValueError) as exc:
        raise PackagingError("config.json id2label has invalid entries") from exc
    if id2label != EXPECTED_ID2LABEL:
        raise PackagingError(
            f"Expected id2label={EXPECTED_ID2LABEL}, got {id2label}"
        )

    raw_label2id = config.get("label2id")
    if not isinstance(raw_label2id, dict):
        raise PackagingError("config.json label2id must be an object")
    try:
        label2id = {str(key): int(value) for key, value in raw_label2id.items()}
    except (TypeError, ValueError) as exc:
        raise PackagingError("config.json label2id has invalid entries") from exc
    if label2id != EXPECTED_LABEL2ID:
        raise PackagingError(
            f"Expected label2id={EXPECTED_LABEL2ID}, got {label2id}"
        )

    threshold = _required_number(
        load_json(directory / "threshold.json"), "threshold", "threshold.json"
    )
    if not math.isclose(
        threshold, EXPECTED_THRESHOLD, rel_tol=0.0, abs_tol=1e-15
    ):
        raise PackagingError(
            f"Expected threshold={EXPECTED_THRESHOLD}, got {threshold!r}"
        )

    temperature = _required_number(
        load_json(directory / "calibration.json"),
        "temperature",
        "calibration.json",
    )
    if not math.isclose(
        temperature, EXPECTED_TEMPERATURE, rel_tol=0.0, abs_tol=1e-15
    ):
        raise PackagingError(
            f"Expected temperature={EXPECTED_TEMPERATURE}, got {temperature!r}"
        )

    return {
        "architecture": ARCHITECTURE,
        "num_labels": EXPECTED_NUM_LABELS,
        "id2label": id2label,
        "label2id": label2id,
        "threshold": threshold,
        "temperature": temperature,
    }


def contains_absolute_path(value: Any) -> bool:
    if isinstance(value, dict):
        return any(contains_absolute_path(item) for item in value.values())
    if isinstance(value, list):
        return any(contains_absolute_path(item) for item in value)
    if not isinstance(value, str):
        return False
    return bool(re.match(r"^[A-Za-z]:[\\/]", value)) or value.startswith("/")


def validate_release(
    release: Path,
    *,
    expected_version: str | None = None,
) -> dict[str, Any]:
    """Validate a complete immutable release and return its verified contract."""

    if not release.is_dir() or release.is_symlink():
        raise PackagingError(f"Release directory does not exist: {release}")
    manifest_path = require_regular_file(release, "manifest.json")
    manifest = load_json(manifest_path)
    if not isinstance(manifest, dict):
        raise PackagingError("Release manifest must contain an object")
    if contains_absolute_path(manifest):
        raise PackagingError("Release manifest must not contain absolute paths")
    if manifest.get("schema_version") != "1.0":
        raise PackagingError("Release manifest schema_version must be '1.0'")

    expected_top_level = {
        "model": MODEL_IDENTIFIER,
        "task": TASK,
        "architecture": ARCHITECTURE,
        "labels": EXPECTED_LABELS,
        "num_labels": EXPECTED_NUM_LABELS,
        "threshold": EXPECTED_THRESHOLD,
        "local_files_only": True,
    }
    for key, expected in expected_top_level.items():
        if manifest.get(key) != expected:
            raise PackagingError(
                f"Release manifest {key} must be {expected!r}, "
                f"got {manifest.get(key)!r}"
            )

    version = manifest.get("version")
    if not isinstance(version, str) or not re.fullmatch(r"v\d+\.\d+\.\d+", version):
        raise PackagingError("Release manifest version must use vMAJOR.MINOR.PATCH")
    if expected_version is not None and version != expected_version:
        raise PackagingError(
            f"Release manifest version must be {expected_version!r}, got {version!r}"
        )

    calibration = manifest.get("calibration")
    temperature = _required_number(
        calibration, "temperature", "Release manifest calibration"
    )
    if not math.isclose(
        temperature, EXPECTED_TEMPERATURE, rel_tol=0.0, abs_tol=1e-15
    ):
        raise PackagingError("Release manifest calibration temperature is invalid")
    if not isinstance(calibration, dict) or calibration.get("method") != "temperature_scaling":
        raise PackagingError("Release manifest calibration method is invalid")

    if manifest.get("critical_files") != list(CRITICAL_FILES):
        raise PackagingError("Release manifest critical_files contract is invalid")
    file_entries = manifest.get("files")
    if not isinstance(file_entries, dict) or set(file_entries) != set(EXPORT_FILES):
        raise PackagingError("Release manifest file list is incomplete or unexpected")

    contents = list(release.iterdir())
    actual_names = {path.name for path in contents if path.is_file()}
    expected_names = set(EXPORT_FILES) | {"manifest.json"}
    if actual_names != expected_names:
        raise PackagingError(
            f"Release files must be exactly {sorted(expected_names)}, "
            f"got {sorted(actual_names)}"
        )
    if any(path.is_dir() or path.is_symlink() for path in contents):
        raise PackagingError("Release directory must not contain directories or symlinks")

    for name in EXPORT_FILES:
        path = require_regular_file(release, name)
        metadata = file_entries[name]
        if not isinstance(metadata, dict):
            raise PackagingError(f"Release manifest metadata for {name} is invalid")
        if metadata.get("size") != path.stat().st_size:
            raise PackagingError(f"Size mismatch for {name}")
        expected_hash = metadata.get("sha256")
        actual_hash = sha256(path)
        if not isinstance(expected_hash, str) or actual_hash != expected_hash.lower():
            raise PackagingError(
                f"Checksum mismatch for {name}: "
                f"expected {expected_hash}, got {actual_hash}"
            )

    known_hash = KNOWN_MODEL_HASHES.get(version)
    actual_model_hash = file_entries["model.safetensors"]["sha256"]
    if known_hash is not None and actual_model_hash != known_hash:
        raise PackagingError("Release model hash does not match the frozen known hash")

    contract = validate_model_contract(release)
    return {
        "path": str(release.resolve()),
        "version": version,
        "files": len(file_entries),
        "payload_size": sum((release / name).stat().st_size for name in EXPORT_FILES),
        "model_sha256": actual_model_hash,
        **contract,
    }
