from __future__ import annotations

from datetime import datetime

from dataset.reconcile import QualityConfig, quality_summary, reconcile, reconcile_label
from dataset.schema import SourceRecord


def _record(
    source_id: str,
    *,
    text: str,
    raw_label: str | None = "hoax",
    title: str = "Judul Berita",
    source: str = "komdigi",
) -> SourceRecord:
    return SourceRecord(
        source=source,
        source_type="hoax_clarification",
        source_id=source_id,
        raw_label=raw_label,
        title=title,
        text=text,
        url=f"https://example.com/{source_id}",
        published_at=datetime(2026, 1, 1),
    )


_LONG_ID_TEXT = (
    "Beredar unggahan di media sosial yang mengeklaim adanya undian berhadiah "
    "dari bank nasional dalam rangka hari kemerdekaan dan meminta masyarakat "
    "untuk mengisi kuesioner dengan data pribadi mereka sehingga perlu "
    "diwaspadai oleh seluruh warga negara Indonesia dan jangan sampai "
    "terjebak oleh modus penipuan yang mengatasnamakan lembaga keuangan resmi."
)


def test_reconcile_label_aliases():
    assert reconcile_label("HOAX") == "hoax"
    assert reconcile_label("hoaks") == "hoax"
    assert reconcile_label("salah") == "hoax"
    assert reconcile_label("valid") == "valid"
    assert reconcile_label("benar") == "valid"
    assert reconcile_label("fact") == "valid"
    assert reconcile_label("info") == "valid"
    assert reconcile_label(None) is None
    assert reconcile_label("???") is None
    assert reconcile_label("meragukan") is None


def test_reconcile_keeps_and_labels():
    result = reconcile([_record("1", text=_LONG_ID_TEXT), _record("2", text=_LONG_ID_TEXT)])
    assert len(result.records) == 1
    assert result.records[0].label == "hoax"
    assert result.records[0].source_hash
    assert result.records[0].record_hash
    assert len(result.rejected) == 1
    assert result.rejected[0].reason == "exact_duplicate"


def test_reconcile_rejects_short_text():
    result = reconcile([_record("1", text="hoaks singkat")])
    assert result.rejected[0].reason == "too_short"
    assert result.records == []


def test_reconcile_rejects_unresolved_label():
    result = reconcile([_record("1", text=_LONG_ID_TEXT, raw_label="bukan-hoax")])
    assert result.rejected[0].reason == "unresolved_label"
    assert result.records == []


def test_reconcile_rejects_non_indonesian():
    result = reconcile(
        [
            _record(
                "1",
                text=(
                    "the quick brown fox jumps over the lazy dog again now while "
                    "the cat sleeps on the warm window sill every single day "
                    "without any interruption from the busy street outside the "
                    "little cottage near the green forest and the quiet river "
                    "bank where the birds sing early in the morning every day"
                ),
            )
        ]
    )
    assert result.rejected[0].reason == "not_indonesian"


def test_reconcile_min_length_configurable():
    config = QualityConfig(min_text_length=30)
    text = "hoaks singkat yang beredar di media sosial dan tidak ada sumber yang jelas"
    result = reconcile([_record("1", text=text)], config)
    assert result.records and result.records[0].text == text


def test_reconcile_valid_label_kept():
    result = reconcile(
        [_record("1", text=_LONG_ID_TEXT, raw_label="valid", source="labeled:news")]
    )
    assert result.records[0].label == "valid"
    assert result.records[0].source == "labeled:news"


def test_quality_summary():
    result = reconcile([_record("1", text=_LONG_ID_TEXT)])
    summary = quality_summary(result.records)
    assert summary["total"] == 1
    assert summary["with_url"] == 1
    assert summary["with_date"] == 1