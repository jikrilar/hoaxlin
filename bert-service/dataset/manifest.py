from __future__ import annotations

import hashlib
import json
from datetime import datetime, timezone
from pathlib import Path
from typing import Any

from dataset import __version__ as package_version
from dataset.reconcile import QualityConfig
from dataset.schema import CanonicalRecord, SourceMeta, SplitConfig

ARTIFACT_NAMES = ("all.jsonl", "train.jsonl", "val.jsonl", "test.jsonl", "rejected.jsonl")


def sha256_file(path: Path) -> str:
    digest = hashlib.sha256()
    with path.open("rb") as handle:
        for chunk in iter(lambda: handle.read(1 << 20), b""):
            digest.update(chunk)
    return digest.hexdigest()


def artifact_info(directory: Path, name: str) -> dict[str, Any]:
    path = directory / name
    if not path.is_file():
        return {"path": name, "rows": 0, "sha256": None}
    rows = sum(1 for _ in path.open(encoding="utf-8") if _.strip())
    return {"path": name, "rows": rows, "sha256": sha256_file(path)}


def build_manifest(
    *,
    dataset_name: str,
    version: str,
    sources: list[SourceMeta],
    input_files: list[tuple[str, str]],
    all_records: list[CanonicalRecord],
    rejected: list[Any],
    split_config: SplitConfig,
    split_summary: dict[str, Any],
    dedup_stats: dict[str, Any],
    rejection_counts: dict[str, int],
    quality: dict[str, int],
    quality_config: QualityConfig,
    output_dir: Path,
    methodology_notes: list[str],
) -> dict[str, Any]:
    label_counts: dict[str, int] = {}
    for record in all_records:
        label_counts[record.label] = label_counts.get(record.label, 0) + 1

    manifest: dict[str, Any] = {
        "dataset": {
            "name": dataset_name,
            "version": version,
            "schema_version": "1.0.0",
            "created_at": datetime.now(timezone.utc).isoformat(),
            "generator": {"package": "hoax-bert-service.dataset", "version": package_version},
            "languages": ["id"],
            "label_schema": {
                "classes": ["hoax", "valid"],
                "notes": (
                    "Binary training labels; the deployment system derives "
                    "'meragukan' from a confidence threshold, it is not a trainable class."
                ),
            },
        },
        "sources": [source.model_dump(mode="json") for source in sources],
        "input_files": [
            {"path": str(path), "sha256": digest} for path, digest in input_files
        ],
        "processing": {
            "normalization": [
                "NFKC unicode normalization",
                "HTML stripping",
                "whitespace collapsing",
                "lowercased alphanumeric tokens for dedup keys",
            ],
            "claim_extraction": (
                "Komdigi articles are cut at the first verdict/evidence marker "
                "(e.g. 'Faktanya', 'dilansir dari', 'link counter') so verdict "
                "boilerplate does not leak into training text; records where no "
                "marker occurs before 120 chars keep the full text (text_source=full)."
            ),
            "dedup": {
                "exact": "sha1 of normalized text; first occurrence wins",
                "near_duplicate": (
                    "MinHash (word trigrams) + banded LSH, Jaccard confirmation"
                ),
                **dedup_stats,
            },
            "quality_gates": {
                "min_text_length": quality_config.min_text_length,
                "min_id_ratio": quality_config.min_id_ratio,
            },
        },
        "split": split_config.model_dump(mode="json") | split_summary,
        "stats": {
            "records_kept": len(all_records),
            "label_counts": dict(sorted(label_counts.items())),
            "rejected": rejection_counts,
            "quality": quality,
        },
        "methodology_notes": methodology_notes,
        "artifacts": [artifact_info(output_dir, name) for name in ARTIFACT_NAMES],
    }
    return manifest


def write_manifest(directory: Path, manifest: dict[str, Any]) -> Path:
    directory.mkdir(parents=True, exist_ok=True)
    path = directory / "manifest.json"
    path.write_text(
        json.dumps(manifest, indent=2, ensure_ascii=False, sort_keys=True) + "\n",
        encoding="utf-8",
    )
    return path


def build_data_card(manifest: dict[str, Any], dataset_name: str, version: str) -> str:
    split = manifest["split"]
    stats = manifest["stats"]
    label_dist = ", ".join(f"{k}: {v}" for k, v in stats["label_counts"].items())
    rows = "\n".join(
        f"| {name} | {info['rows']} | {info['sha256'][:12] or 'n/a'} |"
        for name, info in zip(ARTIFACT_NAMES, manifest["artifacts"])
    )
    sources = "; ".join(f"{s['name']} ({s['rows_kept']} kept)" for s in manifest["sources"])
    return f"""# Data Card — {dataset_name} v{version}

## Summary
- Rows kept: {stats['records_kept']} | Labels: {label_dist}
- Sources: {sources}
- Language: Indonesian (id)

## Intended use
Binary hoax/valid classification with IndoBERT. Do **not** train on the full
text of fact-check articles: the verdict language in clarifications leaks the
label. Use the extracted `text` field (claim portion); `full_text` is kept for
inspection only.

## Splitting
- Mode: {split['mode']} ({split['ratios']})
- Claim groups (dedup clusters) never cross splits — no event leakage.
- `time` mode holds out the newest claims as test to simulate deployment drift.

## Provenance
- Input files and every artifact are SHA-256 hashed in `manifest.json`.
- Verify redistribution rights of each source before publishing the dataset.

## Loading (HuggingFace)
```python
from datasets import load_dataset
ds = load_dataset("json", data_files={{
    "train": "train.jsonl", "validation": "val.jsonl", "test": "test.jsonl"}})
```

## Artifacts
| file | rows | sha256 |
|---|---|---|
{rows}
"""