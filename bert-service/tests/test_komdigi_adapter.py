from __future__ import annotations

import csv
from pathlib import Path

import pytest

from dataset.sources import KomdigiCsvAdapter

HEADER = [
    "id",
    "url",
    "title",
    "slug",
    "published_at",
    "view_count",
    "excerpt",
    "body_html",
    "body_text",
    "main_image_url",
    "category",
    "tags",
    "topics",
]

CLAIM_BODY = (
    "Beredar unggahan di media sosial yang mengeklaim adanya undian berhadiah "
    "dari bank nasional dalam rangka perayaan hari kemerdekaan dan meminta "
    "masyarakat mengisi kuesioner dengan data pribadi. Faktanya, klaim tersebut "
    "tidak benar dan telah dibantah oleh pihak bank melalui akun resmi mereka. "
    "Link Counter: https://example.com/cek-fakta"
)


def _write_csv(path: Path, rows: list[dict]) -> Path:
    with path.open("w", encoding="utf-8", newline="") as handle:
        writer = csv.DictWriter(handle, fieldnames=HEADER)
        writer.writeheader()
        writer.writerows(rows)
    return path


def _row(record_id: str, *, body: str | None = None, title: str | None = None) -> dict:
    return {
        "id": record_id,
        "url": f"https://www.komdigi.go.id/berita/berita-hoaks/detail/hoaks-{record_id}",
        "title": title or f"[HOAKS] Judul {record_id}",
        "slug": f"hoaks-{record_id}",
        "published_at": "2026-08-01 10:00:00",
        "view_count": "123",
        "excerpt": "",
        "body_html": f"<p>{body if body is not None else CLAIM_BODY}</p>",
        "body_text": body if body is not None else CLAIM_BODY,
        "main_image_url": "",
        "category": "Klarifikasi Hoaks",
        "tags": "hoaks hari ini",
        "topics": "Hoaks",
    }


def test_komdigi_adapter_reads_and_extracts_claim(tmp_path: Path):
    csv_path = _write_csv(tmp_path / "sample.csv", [_row("1")])
    adapter = KomdigiCsvAdapter(csv_path)
    records = list(adapter.read())
    assert len(records) == 1
    record = records[0]
    assert record.source == "komdigi"
    assert record.raw_label == "hoax"
    assert record.source_id == "1"
    assert record.language == "id"
    assert record.published_at is not None
    assert record.view_count == 123
    assert record.topics == ["Hoaks"]
    assert record.tags == ["hoaks hari ini"]
    assert "faktanya" not in record.text.lower()
    assert "link counter" not in record.text.lower()
    assert record.text_source == "claim"
    assert record.title == "[HOAKS] Judul 1"


def test_komdigi_adapter_rejects_rows_without_body(tmp_path: Path):
    row = _row("2", body="")
    csv_path = _write_csv(tmp_path / "sample.csv", [row])
    adapter = KomdigiCsvAdapter(csv_path)
    assert list(adapter.read()) == []


def test_komdigi_adapter_skips_link_counter_boilerplate_in_full_text(tmp_path: Path):
    body = (
        "Beredar unggahan di media sosial yang mengeklaim adanya undian berhadiah "
        "dari bank nasional dalam rangka perayaan hari kemerdekaan. Faktanya, "
        "klaim tersebut tidak benar dan telah dibantah. Link Counter: "
        "https://example.com/cek-fakta"
    )
    csv_path = _write_csv(tmp_path / "sample.csv", [_row("3", body=body)])
    record = list(KomdigiCsvAdapter(csv_path).read())[0]
    assert "faktanya" not in record.text.lower()
    assert "faktanya" in record.full_text.lower()
    assert "link counter" not in record.full_text.lower()
    assert "https://example.com" not in record.full_text


def test_komdigi_adapter_missing_columns_raises(tmp_path: Path):
    path = tmp_path / "bad.csv"
    path.write_text("id,title\n1,Judul\n", encoding="utf-8")
    with pytest.raises(Exception):
        list(KomdigiCsvAdapter(path).read())


def test_komdigi_adapter_missing_file_raises(tmp_path: Path):
    with pytest.raises(Exception):
        KomdigiCsvAdapter(tmp_path / "nope.csv")