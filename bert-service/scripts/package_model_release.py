"""Create and validate a self-contained Hoaxlin BERT model release.

The source export is treated as immutable. Packaging copies exact bytes into a
new directory and generates a deterministic manifest alongside the payload.
The tool deliberately uses only the Python standard library so packaging does
not require importing or re-serializing the model.
"""

from __future__ import annotations

import argparse
import json
import math
import re
import shutil
import sys
import tempfile
from pathlib import Path, PureWindowsPath
from typing import Any

SERVICE_ROOT = Path(__file__).resolve().parents[1]
if str(SERVICE_ROOT) not in sys.path:
    sys.path.insert(0, str(SERVICE_ROOT))

from app.model_release import (  # noqa: E402
    CRITICAL_FILES,
    EXPECTED_LABELS,
    EXPECTED_TEMPERATURE,
    EXPECTED_THRESHOLD,
    EXPORT_FILES,
    KNOWN_MODEL_HASHES,
    MODEL_IDENTIFIER,
    TASK,
    PackagingError,
    load_json,
    require_regular_file,
    sha256,
    validate_model_contract,
    validate_release,
)


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
        result = validate_release(staging, expected_version=version)
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
