from __future__ import annotations

from typing import Any

from dataset.schema import CanonicalRecord, SplitConfig


def _group_stats(records: list[CanonicalRecord]) -> dict[str, dict[str, int]]:
    stats: dict[str, dict[str, int]] = {}
    for record in records:
        group = stats.setdefault(record.claim_group, {})
        group[record.label] = group.get(record.label, 0) + 1
    return stats


def _assign_groups_greedy(
    group_ids: list[str],
    groups: dict[str, dict[str, int]],
    targets: dict[str, dict[str, int]],
    fold_order: tuple[str, str, str],
) -> dict[str, str]:
    """Label-aware greedy group-stratified assignment.

    Each group is placed into the fold that is furthest below its per-label
    target fraction (mirrors the StratifiedGroupKFold heuristic). Groups never
    cross splits.
    """
    total_rows = sum(sum(counts.values()) for counts in groups.values())
    label_set = sorted({label for counts in groups.values() for label in counts})
    row_targets = {name: sum(targets[name].values()) for name in fold_order}
    slack = 0.25
    assigned_rows = {name: 0 for name in fold_order}
    assigned_labels = {name: {label: 0 for label in label_set} for name in fold_order}
    assignment: dict[str, str] = {}

    def relative_deficit(name: str, counts: dict[str, int]) -> float:
        worst = -float("inf")
        for label, amount in counts.items():
            target = targets[name].get(label, 0)
            current = assigned_labels[name][label]
            worst = max(worst, (target - current) / max(1, target))
        return worst

    for group_id in group_ids:
        group_counts = groups[group_id]
        group_size = sum(group_counts.values())

        def overflow(name: str) -> int:
            return max(0, assigned_rows[name] + group_size - row_targets[name])

        candidates = [
            name for name in fold_order if overflow(name) <= row_targets[name] * slack
        ]
        if not candidates:
            candidates = sorted(fold_order, key=overflow)

        best = max(
            candidates,
            key=lambda name: (
                relative_deficit(name, group_counts),
                -overflow(name),
                -assigned_rows[name],
            ),
        )
        assignment[group_id] = best
        assigned_rows[best] += group_size
        for label, amount in group_counts.items():
            assigned_labels[best][label] += amount
    return assignment


def _assign_groups_by_time(
    group_ids: list[str],
    groups: dict[str, dict[str, int]],
    time_map: dict[str, float],
    ratios: dict[str, float],
    total_rows: int,
    fold_order: tuple[str, str, str],
) -> dict[str, str]:
    """Group-aware chronological assignment: train gets the oldest claims,
    test the newest (simulated deployment). Groups never cross splits."""
    targets = {
        name: round(total_rows * ratio) for name, ratio in zip(fold_order, ratios)
    }
    ordered = sorted(
        group_ids,
        key=lambda g: (time_map.get(g, float("-inf")), -sum(groups[g].values()), g),
    )
    assignment: dict[str, str] = {}
    cursor = 0
    for idx, name in enumerate(fold_order):
        consumed = 0
        while cursor < len(ordered):
            size = sum(groups[ordered[cursor]].values())
            if idx < len(fold_order) - 1 and consumed + size > targets[name] and consumed > 0:
                break
            assignment[ordered[cursor]] = name
            cursor += 1
            consumed += size
        if cursor >= len(ordered):
            break
    for group_id in ordered[cursor:]:
        assignment[group_id] = fold_order[-1]
    return assignment


def split_dataset(
    records: list[CanonicalRecord],
    config: SplitConfig,
) -> tuple[dict[str, list[CanonicalRecord]], dict[str, Any]]:
    """Split records into train/val/test without leaking claim groups.

    Returns ``(by_split, summary)``. Two modes:

    - ``group``: greedy group-stratified assignment — label proportions are
      preserved across folds and no claim group appears in two splits.
    - ``time``: chronological holdout — the newest ~``test_ratio`` rows become
      test, simulating deployment on future claims; group integrity holds.
    """
    config.validate_ratios()
    if not records:
        raise ValueError("cannot split an empty record set")
    fold_order = ("train", "val", "test")
    ratios = (config.train_ratio, config.val_ratio, config.test_ratio)
    groups = _group_stats(records)

    if config.mode == "time":
        time_map: dict[str, float] = {}
        for record in records:
            if record.published_at is None:
                continue
            ts = record.published_at.timestamp()
            time_map[record.claim_group] = max(time_map.get(record.claim_group, 0.0), ts)
        assignment = _assign_groups_by_time(
            list(groups), groups, time_map, ratios, len(records), fold_order
        )
    else:
        label_totals: dict[str, int] = {}
        for record in records:
            label_totals[record.label] = label_totals.get(record.label, 0) + 1
        targets = {
            name: {label: round(count * ratio) for label, count in label_totals.items()}
            for name, ratio in zip(fold_order, ratios)
        }
        group_ids = sorted(
            groups, key=lambda g: (-sum(groups[g].values()), g)
        )
        assignment = _assign_groups_greedy(group_ids, groups, targets, fold_order)

    by_split: dict[str, list[CanonicalRecord]] = {"train": [], "val": [], "test": []}
    for record in records:
        by_split[assignment[record.claim_group]].append(record)

    summary: dict[str, Any] = {
        "mode": config.mode,
        "ratios": dict(zip(fold_order, ratios)),
        "total": len(records),
        "groups": len(groups),
        "splits": {},
    }
    for name in fold_order:
        split_records = by_split[name]
        label_counts: dict[str, int] = {}
        for record in split_records:
            label_counts[record.label] = label_counts.get(record.label, 0) + 1
        summary["splits"][name] = {
            "rows": len(split_records),
            "label_counts": dict(sorted(label_counts.items())),
            "groups": len({r.claim_group for r in split_records}),
        }
    return by_split, summary