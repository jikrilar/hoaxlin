"""Create and validate a self-contained Hoaxlin BERT model release.

The source export is treated as immutable. Packaging copies exact bytes into a
new directory and generates a deterministic manifest alongside the payload.
The tool deliberately uses only the Python standard library so packaging does
not require importing or re-serializing the model.
"""

from __future__ import annotations

import argparse
import hashlib
import json
import math
import re
import shutil
import sys
import tempfile
from pathlib import Path, PureWindowsPath
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

# Canonical hashes independently frozen for known releases. New versions must
# be reviewed before being added rather than silently trusting a new source.
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
            f"Expected num_labels={EXPECTED_NUM_LABELS}, got {config.get('num_labels')!r}"
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

    threshold_data = load_json(directory / "threshold.json")
    threshold = threshold_data.get("threshold") if isinstance(threshold_data, dict) else None
    if not isinstance(threshold, (int, float)) or not math.isclose(
        float(threshold), EXPECTED_THRESHOLD, rel_tol=0.0, abs_tol=1e-15
    ):
        raise PackagingError(
            f"Expected threshold={EXPECTED_THRESHOLD}, got {threshold!r}"
        )

    calibration_data = load_json(directory / "calibration.json")
    temperature = (
        calibration_data.get("temperature")
        if isinstance(calibration_data, dict)
        else None
    )
    if not isinstance(temperature, (int, float)) or not math.isclose(
        float(temperature), EXPECTED_TEMPERATURE, rel_tol=0.0, abs_tol=1e-15
    ):
        raise PackagingError(
            f"Expected temperature={EXPECTED_TEMPERATURE}, got {temperature!r}"
        )

    return {
        "architecture": ARCHITECTURE,
        "num_labels": EXPECTED_NUM_LABELS,
        "id2label": id2label,
        "label2id": label2id,
        "threshold": float(threshold),
        "temperature": float(temperature),
    }


def source_manifest_entry(source: Path, version: str) -> tuple[Path, dict[str, Any]]:
    candidates = (source.parent / "manifest.json", source / "manifest.json")
    manifest_path = next((path for path in candidates if path.is_file()), None)
    if manifest_path is None:
        raise PackagingError("Source export manifest.json was not found")

    manifest = load_json(manifest_path)
    if not isinstance(manifest, list):
        raise PackagingError("Source export manifest must contain a list")
    source_version = version.removeprefix("v")
    entry = next(
        (
            item
            for item in manifest
            if isinstance(item, dict) and str(item.get("version")) == source_version
        ),
        None,
    )
    if entry is None:
        raise PackagingError(
            f"Source manifest has no exact entry for version {source_version}"
        )
    return manifest_path, entry


def validate_source(source: Path, version: str) -> dict[str, Any]:
    if not source.is_dir():
        raise PackagingError(f"Source artifact directory does not exist: {source}")
    for name in EXPORT_FILES:
        require_regular_file(source, name)

    contract = validate_model_contract(source)
    manifest_path, entry = source_manifest_entry(source, version)

    if entry.get("model_name") != MODEL_IDENTIFIER:
        raise PackagingError(
            f"Expected source model_name={MODEL_IDENTIFIER}, got {entry.get('model_name')!r}"
        )
    if entry.get("labels") != EXPECTED_LABELS:
        raise PackagingError(
            f"Expected source labels={EXPECTED_LABELS}, got {entry.get('labels')!r}"
        )
    if not math.isclose(
        float(entry.get("threshold", -1)),
        EXPECTED_THRESHOLD,
        rel_tol=0.0,
        abs_tol=1e-15,
    ):
        raise PackagingError("Source manifest threshold does not match the model contract")
    if not math.isclose(
        float(entry.get("temperature", -1)),
        EXPECTED_TEMPERATURE,
        rel_tol=0.0,
        abs_tol=1e-15,
    ):
        raise PackagingError("Source manifest temperature does not match the model contract")

    manifest_files = entry.get("files")
    if not isinstance(manifest_files, dict):
        raise PackagingError("Source manifest files must be an object")
    source_hashes: dict[str, str] = {}
    for name in EXPORT_FILES:
        expected_hash = manifest_files.get(name)
        if not isinstance(expected_hash, str):
            raise PackagingError(f"Source manifest does not hash {name}")
        actual_hash = sha256(source / name)
        if actual_hash != expected_hash.lower():
            raise PackagingError(
                f"Source checksum mismatch for {name}: expected {expected_hash}, got {actual_hash}"
            )
        source_hashes[name] = actual_hash

    known_hash = KNOWN_MODEL_HASHES.get(version)
    if known_hash is not None and source_hashes["model.safetensors"] != known_hash:
        raise PackagingError(
            "model.safetensors does not match the independently frozen release hash"
        )

    raw_checkpoint = str(entry.get("from_checkpoint", ""))
    checkpoint_name = (
        PureWindowsPath(raw_checkpoint).name
        if "\\" in raw_checkpoint
        else Path(raw_checkpoint).name
    )
    provenance = {
        "kind": "versioned-export",
        "source_manifest_sha256": sha256(manifest_path),
        "source_export_version": str(entry["version"]),
        "source_checkpoint": checkpoint_name or None,
        "base_model": entry.get("base_model"),
        "exported_at": entry.get("exported_at"),
    }
    return {"contract": contract, "hashes": source_hashes, "provenance": provenance}


def build_manifest(
    release: Path,
    version: str,
    source_validation: dict[str, Any],
) -> dict[str, Any]:
    files = {
        name: {
            "sha256": sha256(release / name),
            "size": (release / name).stat().st_size,
        }
        for name in sorted(EXPORT_FILES)
    }
    contract = source_validation["contract"]
    return {
        "schema_version": "1.0",
        "model": MODEL_IDENTIFIER,
        "version": version,
        "task": TASK,
        "architecture": contract["architecture"],
        "labels": EXPECTED_LABELS,
        "num_labels": contract["num_labels"],
        "threshold": contract["threshold"],
        "calibration": {
            "method": "temperature_scaling",
            "temperature": contract["temperature"],
        },
        "local_files_only": True,
        "critical_files": list(CRITICAL_FILES),
        "files": files,
        "provenance": source_validation["provenance"],
        "redistribution": {"public_release": "not_verified"},
    }


def contains_absolute_path(value: Any) -> bool:
    if isinstance(value, dict):
        return any(contains_absolute_path(item) for item in value.values())
    if isinstance(value, list):
        return any(contains_absolute_path(item) for item in value)
    if not isinstance(value, str):
        return False
    return bool(re.match(r"^[A-Za-z]:[\\/]", value)) or value.startswith("/")


def validate_release(release: Path) -> dict[str, Any]:
    if not release.is_dir():
        raise PackagingError(f"Release directory does not exist: {release}")
    manifest_path = require_regular_file(release, "manifest.json")
    manifest = load_json(manifest_path)
    if not isinstance(manifest, dict):
        raise PackagingError("Release manifest must contain an object")
    if contains_absolute_path(manifest):
        raise PackagingError("Release manifest must not contain absolute paths")

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
                f"Release manifest {key} must be {expected!r}, got {manifest.get(key)!r}"
            )
    version = manifest.get("version")
    if not isinstance(version, str) or not version.startswith("v"):
        raise PackagingError("Release manifest version must be a v-prefixed string")

    calibration = manifest.get("calibration")
    if not isinstance(calibration, dict) or not math.isclose(
        float(calibration.get("temperature", -1)),
        EXPECTED_TEMPERATURE,
        rel_tol=0.0,
        abs_tol=1e-15,
    ):
        raise PackagingError("Release manifest calibration temperature is invalid")
    if calibration.get("method") != "temperature_scaling":
        raise PackagingError("Release manifest calibration method is invalid")

    if manifest.get("critical_files") != list(CRITICAL_FILES):
        raise PackagingError("Release manifest critical_files contract is invalid")
    file_entries = manifest.get("files")
    if not isinstance(file_entries, dict) or set(file_entries) != set(EXPORT_FILES):
        raise PackagingError("Release manifest file list is incomplete or unexpected")

    actual_names = {path.name for path in release.iterdir() if path.is_file()}
    expected_names = set(EXPORT_FILES) | {"manifest.json"}
    if actual_names != expected_names:
        raise PackagingError(
            f"Release files must be exactly {sorted(expected_names)}, got {sorted(actual_names)}"
        )
    if any(path.is_dir() or path.is_symlink() for path in release.iterdir()):
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
                f"Checksum mismatch for {name}: expected {expected_hash}, got {actual_hash}"
            )

    known_hash = KNOWN_MODEL_HASHES.get(version)
    if known_hash is not None and file_entries["model.safetensors"]["sha256"] != known_hash:
        raise PackagingError("Release model hash does not match the frozen known hash")

    contract = validate_model_contract(release)
    return {
        "path": str(release.resolve()),
        "version": version,
        "files": len(file_entries),
        "payload_size": sum((release / name).stat().st_size for name in EXPORT_FILES),
        **contract,
    }


def package_model(source: Path, output: Path, version: str) -> dict[str, Any]:
    source = source.resolve()
    output = output.resolve()
    if output.exists():
        raise PackagingError(f"Output already exists; refusing to overwrite: {output}")
    if output == source or output.is_relative_to(source) or source.is_relative_to(output):
        raise PackagingError("Source and output directories must not contain each other")

    source_validation = validate_source(source, version)
    output.parent.mkdir(parents=True, exist_ok=True)
    with tempfile.TemporaryDirectory(
        prefix=f".{output.name}-packaging-", dir=output.parent
    ) as temporary:
        staging = Path(temporary) / output.name
        staging.mkdir()
        for name in EXPORT_FILES:
            shutil.copyfile(source / name, staging / name)
        manifest = build_manifest(staging, version, source_validation)
        (staging / "manifest.json").write_text(
            json.dumps(manifest, indent=2, sort_keys=True, ensure_ascii=False) + "\n",
            encoding="utf-8",
            newline="\n",
        )
        result = validate_release(staging)
        staging.replace(output)

    return {**result, "path": str(output)}


def parse_args(argv: list[str] | None = None) -> argparse.Namespace:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--source", type=Path, help="Immutable versioned source export")
    parser.add_argument("--output", type=Path, help="New self-contained release directory")
    parser.add_argument("--version", help="Immutable v-prefixed model version")
    parser.add_argument(
        "--validate-only",
        type=Path,
        metavar="RELEASE",
        help="Validate an existing release instead of packaging",
    )
    return parser.parse_args(argv)


def main(argv: list[str] | None = None) -> int:
    args = parse_args(argv)
    try:
        if args.validate_only is not None:
            if args.source is not None or args.output is not None or args.version is not None:
                raise PackagingError(
                    "--validate-only cannot be combined with --source, --output, or --version"
                )
            result = validate_release(args.validate_only.resolve())
        else:
            if args.source is None or args.output is None or args.version is None:
                raise PackagingError(
                    "--source, --output, and --version are required for packaging"
                )
            if not re.fullmatch(r"v\d+\.\d+\.\d+", args.version):
                raise PackagingError("--version must use the form vMAJOR.MINOR.PATCH")
            result = package_model(args.source, args.output, args.version)
    except PackagingError as exc:
        print(f"ERROR: {exc}", file=sys.stderr)
        return 1

    print(json.dumps(result, indent=2, sort_keys=True))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
