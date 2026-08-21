from __future__ import annotations

import json
from collections import Counter
from pathlib import Path

import typer

from dataset.dedup import NearDuplicateClusterer
from dataset.manifest import build_data_card, build_manifest, sha256_file, write_manifest
from dataset.reconcile import QualityConfig, quality_summary, reconcile
from dataset.schema import SourceMeta, SplitConfig
from dataset.sources import KomdigiCsvAdapter, LabeledCsvAdapter, SourceAdapter
from dataset.split import split_dataset

app = typer.Typer(
    help="Prepare the Indonesian hoax-news dataset (schema -> dedup -> reconcile -> split -> manifest).",
    add_completion=False,
    no_args_is_help=True,
)

JSONL_ENCODING = "utf-8"


def _write_jsonl(path: Path, rows: list[dict]) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    with path.open("w", encoding=JSONL_ENCODING) as handle:
        for row in rows:
            handle.write(json.dumps(row, ensure_ascii=False, sort_keys=True) + "\n")


def _input_digests(paths: list[Path]) -> list[tuple[str, str]]:
    return [(str(path), sha256_file(path)) for path in paths]


def _load_adapters(source: Path, extra_sources: list[Path]) -> list[SourceAdapter]:
    adapters: list[SourceAdapter] = [KomdigiCsvAdapter(source)]
    for extra in extra_sources:
        adapters.append(LabeledCsvAdapter(extra))
    return adapters


def _run_pipeline(
    source: Path,
    extra_sources: list[Path],
    out: Path,
    *,
    train_ratio: float,
    val_ratio: float,
    test_ratio: float,
    seed: int,
    split_mode: str,
    min_text_length: int,
    min_id_ratio: float,
    min_similarity: float,
    n_permutations: int,
    n_bands: int,
    dataset_name: str,
    version: str,
    methodology_notes: list[str],
) -> dict:
    split_config = SplitConfig(
        mode=split_mode,  # type: ignore[arg-type]
        train_ratio=train_ratio,
        val_ratio=val_ratio,
        test_ratio=test_ratio,
        seed=seed,
    )
    quality_config = QualityConfig(
        min_text_length=min_text_length, min_id_ratio=min_id_ratio
    )

    adapters = _load_adapters(source, extra_sources)
    sources_meta: list[SourceMeta] = []
    source_records = []
    for adapter in adapters:
        records = list(adapter.read())
        source_records.extend(records)
        meta = adapter.meta()
        meta.rows_ingested = len(records)
        sources_meta.append(meta)
    typer.echo(f"Ingested {len(source_records)} source records from {len(adapters)} adapter(s).")

    reconciled = reconcile(source_records, quality_config)
    records = reconciled.records
    rejected = reconciled.rejected
    typer.echo(
        f"Reconciliation: kept {len(records)}, rejected {len(rejected)} "
        f"({reconciled.rejection_summary()})."
    )

    clusterer = NearDuplicateClusterer(
        n_permutations=n_permutations, n_bands=n_bands, seed=seed, min_similarity=min_similarity
    )
    for record in records:
        clusterer.add(record.record_id, record.text)
    cluster_map = clusterer.clusters()
    for record in records:
        record.claim_group = cluster_map[record.record_id]
    near_pairs = clusterer.confirmed_pairs()
    near_duplicate_ids = {doc_id for pair in near_pairs for doc_id in pair}
    exact_removed = sum(1 for r in rejected if r.reason == "exact_duplicate")
    dedup_stats = {
        "exact_removed": exact_removed,
        "near_duplicate_pairs": len(near_pairs),
        "near_duplicate_docs": len(near_duplicate_ids),
        "claim_groups": len(set(cluster_map.values())),
        "min_similarity": min_similarity,
        "n_permutations": n_permutations,
        "n_bands": n_bands,
    }

    by_split, split_summary = split_dataset(records, split_config)

    for meta, adapter in zip(sources_meta, adapters):
        meta.rows_kept = sum(1 for r in records if r.source == meta.name)

    label_counts = Counter(r.label for r in records)
    if set(label_counts) != {"hoax", "valid"}:
        typer.echo(
            typer.style(
                "WARNING: training needs both 'hoax' and 'valid' classes; "
                f"found {dict(label_counts)}. Add a valid-news source with "
                "--extra-source before fine-tuning (C3).",
                fg=typer.colors.YELLOW,
            )
        )

    out.mkdir(parents=True, exist_ok=True)
    _write_jsonl(out / "all.jsonl", [r.to_jsonl() for r in records])
    _write_jsonl(
        out / "rejected.jsonl",
        [r.model_dump(mode="json") for r in rejected],
    )
    for name in ("train", "val", "test"):
        _write_jsonl(out / f"{name}.jsonl", [r.to_jsonl() for r in by_split[name]])

    manifest = build_manifest(
        dataset_name=dataset_name,
        version=version,
        sources=sources_meta,
        input_files=_input_digests([source, *extra_sources]),
        all_records=records,
        rejected=rejected,
        split_config=split_config,
        split_summary=split_summary,
        dedup_stats=dedup_stats,
        rejection_counts=reconciled.rejection_summary(),
        quality=quality_summary(records),
        quality_config=quality_config,
        output_dir=out,
        methodology_notes=methodology_notes,
    )
    write_manifest(out, manifest)
    (out / "data_card.md").write_text(
        build_data_card(manifest, dataset_name, version), encoding=JSONL_ENCODING
    )

    for info in manifest["artifacts"]:
        typer.echo(f"  {info['path']}: {info['rows']} rows, sha256 {info['sha256'][:16]}...")
    typer.echo(f"Manifest: {out / 'manifest.json'}")
    return manifest


@app.command()
def prepare(
    source: Path = typer.Option(
        ..., "--source", "-s", exists=True, dir_okay=False, help="Komdigi hoax CSV export."
    ),
    extra_source: list[Path] = typer.Option(
        [],
        "--extra-source",
        exists=True,
        dir_okay=False,
        help="Optional labeled CSV (text+label columns) for extra classes, e.g. valid news. Repeatable.",
    ),
    out: Path = typer.Option(
        Path("datasets/processed/komdigi-hoaks-v1"),
        "--out",
        "-o",
        help="Output directory for split artifacts and manifest.",
    ),
    dataset_name: str = typer.Option("komdigi-hoaks", "--dataset-name"),
    version: str = typer.Option("1.0.0", "--version"),
    split_mode: str = typer.Option(
        "group", "--split-mode", help="'group' (stratified by claim group) or 'time' (newest holdout)."
    ),
    train_ratio: float = typer.Option(0.8, "--train-ratio", min=0.0, max=1.0),
    val_ratio: float = typer.Option(0.1, "--val-ratio", min=0.0, max=1.0),
    test_ratio: float = typer.Option(0.1, "--test-ratio", min=0.0, max=1.0),
    seed: int = typer.Option(42, "--seed"),
    min_text_length: int = typer.Option(100, "--min-text-len"),
    min_id_ratio: float = typer.Option(0.20, "--min-id-ratio", min=0.0, max=1.0),
    min_similarity: float = typer.Option(
        0.65, "--min-similarity", min=0.0, max=1.0, help="Near-duplicate Jaccard threshold."
    ),
    n_permutations: int = typer.Option(128, "--n-permutations"),
    n_bands: int = typer.Option(16, "--n-bands"),
    note: list[str] = typer.Option(
        [], "--note", help="Methodology note recorded in the manifest. Repeatable."
    ),
) -> None:
    """Run the full dataset preparation pipeline."""
    if abs(train_ratio + val_ratio + test_ratio - 1.0) > 1e-9:
        raise typer.BadParameter("--train-ratio + --val-ratio + --test-ratio must sum to 1.0")
    if split_mode not in ("group", "time"):
        raise typer.BadParameter("--split-mode must be 'group' or 'time'")
    if n_permutations % n_bands != 0:
        raise typer.BadParameter("--n-permutations must be divisible by --n-bands")

    notes = [
        "Training text is the extracted claim portion of each fact-check article; "
        "verdict/evidence language is excluded to avoid label leakage.",
        "Near-duplicate claims form claim groups; groups never cross train/val/test.",
    ] + note
    _run_pipeline(
        source,
        extra_source,
        out,
        train_ratio=train_ratio,
        val_ratio=val_ratio,
        test_ratio=test_ratio,
        seed=seed,
        split_mode=split_mode,
        min_text_length=min_text_length,
        min_id_ratio=min_id_ratio,
        min_similarity=min_similarity,
        n_permutations=n_permutations,
        n_bands=n_bands,
        dataset_name=dataset_name,
        version=version,
        methodology_notes=notes,
    )


if __name__ == "__main__":
    app()