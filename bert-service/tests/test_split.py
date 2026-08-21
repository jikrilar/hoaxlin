from __future__ import annotations

from datetime import datetime, timedelta

import pytest

from dataset.schema import CanonicalRecord, SplitConfig
from dataset.split import split_dataset


def _record(i: int, label: str, group: str, days_ago: int = 100) -> CanonicalRecord:
    text = f"Beredar unggahan di media sosial dengan narasi yang mengeklaim hal ke-{i}"
    return CanonicalRecord(
        record_id=f"r{i}",
        source="komdigi",
        source_type="hoax_clarification",
        label=label,  # type: ignore[arg-type]
        title=f"Judul {i}",
        text=text,
        full_text=text,
        url=f"https://example.com/{i}",
        published_at=datetime(2026, 1, 1) - timedelta(days=days_ago),
        claim_group=group,
        source_hash=f"h{i}",
        record_hash=f"rh{i}",
    )


def _records(per_group: int = 4) -> list[CanonicalRecord]:
    records = []
    for g in range(10):
        for j in range(per_group):
            records.append(_record(g * per_group + j, "hoax", f"group-{g}"))
    return records


def _mixed_labels() -> list[CanonicalRecord]:
    records = []
    for g in range(10):
        for j in range(4):
            label = "hoax" if (g + j) % 2 == 0 else "valid"
            records.append(_record(g * 4 + j, label, f"group-{g}"))
    return records


def test_group_split_never_splits_groups():
    config = SplitConfig(train_ratio=0.8, val_ratio=0.1, test_ratio=0.1, seed=42)
    by_split, summary = split_dataset(_records(), config)
    groups_by_split = {name: {r.claim_group for r in rows} for name, rows in by_split.items()}
    for name_a in groups_by_split:
        for name_b in groups_by_split:
            if name_a != name_b:
                assert groups_by_split[name_a].isdisjoint(groups_by_split[name_b])
    assert summary["total"] == 40
    assert sum(v["rows"] for v in summary["splits"].values()) == 40


def test_group_split_balances_labels():
    config = SplitConfig(train_ratio=0.8, val_ratio=0.1, test_ratio=0.1, seed=7)
    by_split, _ = split_dataset(_mixed_labels(), config)
    for name, rows in by_split.items():
        counts = {}
        for r in rows:
            counts[r.label] = counts.get(r.label, 0) + 1
        total = sum(counts.values())
        if total >= 5:
            ratio = counts.get("valid", 0) / total
            assert 0.3 <= ratio <= 0.7, (name, counts)


def test_group_split_stratifies_single_label_groups():
    """Single-label group sets (the real-data shape: claim groups per class)
    must still stratify per class, not just per row count."""
    records = []
    for i in range(80):
        label = "hoax" if i % 2 == 0 else "valid"
        records.append(_record(i, label, f"group-{i}", days_ago=100 - i // 2))
    config = SplitConfig(train_ratio=0.8, val_ratio=0.1, test_ratio=0.1, seed=42)
    by_split, summary = split_dataset(records, config)
    for name in ("train", "val", "test"):
        counts = summary["splits"][name]["label_counts"]
        assert counts.get("hoax", 0) >= 2, (name, counts)
        assert counts.get("valid", 0) >= 2, (name, counts)
        ratio = counts["valid"] / sum(counts.values())
        assert 0.35 <= ratio <= 0.65, (name, counts)


def test_group_split_deterministic():
    config = SplitConfig(train_ratio=0.8, val_ratio=0.1, test_ratio=0.1, seed=42)
    a, _ = split_dataset(_records(), config)
    b, _ = split_dataset(_records(), config)
    for name in ("train", "val", "test"):
        assert {r.record_id for r in a[name]} == {r.record_id for r in b[name]}


def test_time_split_holds_out_newest():
    records = []
    for i in range(40):
        records.append(_record(i, "hoax", f"group-{i}", days_ago=100 - i))
    config = SplitConfig(
        mode="time", train_ratio=0.8, val_ratio=0.1, test_ratio=0.1, seed=42
    )
    by_split, summary = split_dataset(records, config)
    test_max_age = max(r.published_at for r in by_split["test"])
    train_max_age = max(r.published_at for r in by_split["train"])
    assert test_max_age >= train_max_age
    for name in ("train", "val", "test"):
        assert summary["splits"][name]["rows"] > 0, name
    assert summary["splits"]["test"]["rows"] >= 3


def test_time_split_respects_groups():
    records = []
    for g in range(5):
        for j in range(4):
            records.append(_record(g * 4 + j, "hoax", f"group-{g}", days_ago=100 - g))
    config = SplitConfig(
        mode="time", train_ratio=0.7, val_ratio=0.15, test_ratio=0.15, seed=1
    )
    by_split, _ = split_dataset(records, config)
    groups_by_split = {name: {r.claim_group for r in rows} for name, rows in by_split.items()}
    for name_a in groups_by_split:
        for name_b in groups_by_split:
            if name_a != name_b:
                assert groups_by_split[name_a].isdisjoint(groups_by_split[name_b])


def test_split_ratio_validation():
    with pytest.raises(ValueError):
        SplitConfig(train_ratio=0.5, val_ratio=0.5, test_ratio=0.5).validate_ratios()


def test_split_empty_records_raises():
    with pytest.raises(ValueError):
        split_dataset([], SplitConfig(train_ratio=0.8, val_ratio=0.1, test_ratio=0.1))