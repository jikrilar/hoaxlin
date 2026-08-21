from __future__ import annotations

import hashlib
import json
from dataclasses import dataclass, field

from dataset.dedup import exact_dedup
from dataset.normalize import is_indonesian, normalize_key, sha1_hex
from dataset.schema import (
    LABEL_ALIASES,
    CANONICAL_LABELS,
    CanonicalRecord,
    RejectedRecord,
    SourceRecord,
)

MIN_TEXT_LENGTH = 100
MIN_ID_RATIO = 0.20


@dataclass(slots=True)
class QualityConfig:
    min_text_length: int = MIN_TEXT_LENGTH
    min_id_ratio: float = MIN_ID_RATIO


@dataclass(slots=True)
class ReconciliationResult:
    records: list[CanonicalRecord] = field(default_factory=list)
    rejected: list[RejectedRecord] = field(default_factory=list)
    label_counts: dict[str, int] = field(default_factory=dict)
    rejection_counts: dict[str, int] = field(default_factory=dict)

    def rejection_summary(self) -> dict[str, int]:
        return dict(sorted(self.rejection_counts.items()))


def reconcile_label(raw: str | None) -> str | None:
    if raw is None:
        return None
    normalized = raw.strip().lower().rstrip(".:")
    return LABEL_ALIASES.get(normalized)


def _record_hash(record: CanonicalRecord) -> str:
    payload = record.model_dump(mode="json", exclude={"record_hash"})
    return hashlib.sha256(
        json.dumps(payload, sort_keys=True, ensure_ascii=False).encode("utf-8")
    ).hexdigest()


def reconcile(
    source_records: list[SourceRecord],
    config: QualityConfig | None = None,
) -> ReconciliationResult:
    config = config or QualityConfig()
    result = ReconciliationResult()

    for source_record in source_records:
        text = (source_record.text or "").strip()
        title = (source_record.title or "").strip()
        label = reconcile_label(source_record.raw_label)

        if label is None:
            result.rejected.append(
                RejectedRecord(
                    record_id=source_record.source_id,
                    source=source_record.source,
                    reason="unresolved_label",
                    title=title or "(no title)",
                    detail=f"raw label: {source_record.raw_label!r}",
                )
            )
            continue
        if len(text) < config.min_text_length:
            result.rejected.append(
                RejectedRecord(
                    record_id=source_record.source_id,
                    source=source_record.source,
                    reason="too_short",
                    title=title or "(no title)",
                    detail=f"text length {len(text)} < {config.min_text_length}",
                )
            )
            continue
        if not is_indonesian(text, config.min_id_ratio):
            result.rejected.append(
                RejectedRecord(
                    record_id=source_record.source_id,
                    source=source_record.source,
                    reason="not_indonesian",
                    title=title or "(no title)",
                    detail="Indonesian stopword ratio below threshold",
                )
            )
            continue

        canonical = CanonicalRecord(
            record_id=f"{source_record.source}:{source_record.source_id}",
            source=source_record.source,
            source_type=source_record.source_type,
            label=label,  # type: ignore[arg-type]
            title=title,
            text=text,
            text_source=source_record.text_source,
            full_text=source_record.full_text or source_record.text,
            url=source_record.url,
            published_at=source_record.published_at,
            view_count=source_record.view_count,
            category=source_record.category,
            tags=source_record.tags,
            topics=source_record.topics,
            language=source_record.language,
            claim_group=source_record.source_id,
            source_hash=sha1_hex(text),
            record_hash="",
        )
        canonical.record_hash = _record_hash(canonical)
        result.records.append(canonical)

    ids = [r.record_id for r in result.records]
    texts = [r.text for r in result.records]
    dedup = exact_dedup(ids, texts)
    kept_ids = set(dedup.kept)

    kept_by_id = {r.record_id: r for r in result.records}
    first_by_removed = dict(zip(dedup.removed, dedup.kept))
    for record_id in dedup.removed:
        kept = kept_by_id[record_id]
        result.rejected.append(
            RejectedRecord(
                record_id=record_id,
                source=kept.source,
                reason="exact_duplicate",
                title=kept.title,
                detail=f"duplicate of {first_by_removed[record_id]}",
            )
        )
    result.records = [r for r in result.records if r.record_id in kept_ids]

    for record in result.records:
        result.label_counts[record.label] = result.label_counts.get(record.label, 0) + 1
    for rejected in result.rejected:
        result.rejection_counts[rejected.reason] = (
            result.rejection_counts.get(rejected.reason, 0) + 1
        )
    return result


def quality_summary(records: list[CanonicalRecord]) -> dict[str, int]:
    return {
        "total": len(records),
        "with_claim_text": sum(1 for r in records if r.text_source == "claim"),
        "with_full_text": sum(1 for r in records if r.text_source == "full"),
        "with_url": sum(1 for r in records if r.url),
        "with_date": sum(1 for r in records if r.published_at),
        "with_topic": sum(1 for r in records if r.topics),
    }