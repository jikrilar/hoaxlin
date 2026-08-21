from __future__ import annotations

import csv
import os
from collections.abc import Iterator
from datetime import datetime
from pathlib import Path

from dataset.normalize import clean_text, cut_claim
from dataset.schema import SourceMeta, SourceRecord
from dataset.sources.base import AdapterError, SourceAdapter

EXPECTED_COLUMNS = {
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
}

LINK_COUNTER_MARKERS = ("link counter", "link counter:", "tautan counter")


class KomdigiCsvAdapter(SourceAdapter):
    """Komdigi hoax-clarification export (``datasets/komdigi_hoaks.csv``).

    Every row is a hoax clarification published under
    ``/berita/berita-hoaks/detail/...``, so the label is implied: ``hoax``.
    The claim portion of each article is extracted with ``cut_claim`` so the
    verdict language ("Faktanya, klaim tersebut ...") never leaks into the
    training text.
    """

    name = "komdigi"
    kind = "hoax_clarification"
    license = (
        "Komdigi public information (komdigi.go.id); verify redistribution terms "
        "before publishing the derived dataset"
    )
    url = "https://www.komdigi.go.id/berita/berita-hoaks"

    def __init__(self, path: str | os.PathLike[str]) -> None:
        self.path = Path(path)
        if not self.path.is_file():
            raise AdapterError(f"CSV not found: {self.path}")

    def _parse_rows(self) -> list[dict[str, str]]:
        try:
            with self.path.open(encoding="utf-8-sig", newline="") as handle:
                reader = csv.DictReader(handle)
                if reader.fieldnames is None:
                    raise AdapterError(f"empty CSV: {self.path}")
                missing = EXPECTED_COLUMNS - set(reader.fieldnames)
                if missing:
                    raise AdapterError(
                        f"CSV missing columns {sorted(missing)} (found {sorted(reader.fieldnames)})"
                    )
                return list(reader)
        except UnicodeDecodeError as exc:
            raise AdapterError(f"CSV is not UTF-8 encoded: {self.path}") from exc
        except csv.Error as exc:
            raise AdapterError(f"malformed CSV: {self.path}: {exc}") from exc

    @staticmethod
    def _split_list(value: str | None) -> list[str]:
        if not value:
            return []
        parts: list[str] = []
        for part in value.split(","):
            item = part.strip()
            if item and item not in parts:
                parts.append(item)
        return parts

    @staticmethod
    def _parse_datetime(value: str | None) -> datetime | None:
        if not value:
            return None
        for fmt in ("%Y-%m-%d %H:%M:%S", "%Y-%m-%d"):
            try:
                return datetime.strptime(value.strip(), fmt)
            except ValueError:
                continue
        return None

    @staticmethod
    def _parse_int(value: str | None) -> int | None:
        if not value:
            return None
        try:
            return int(value)
        except ValueError:
            return None

    def read(self) -> Iterator[SourceRecord]:
        for row in self._parse_rows():
            full_text = clean_text(row.get("body_text") or "")
            lowered = full_text.lower()
            for marker in LINK_COUNTER_MARKERS:
                pos = lowered.find(marker)
                if pos >= 120:
                    full_text = full_text[:pos].strip()
                    break
            claim_text, cut = cut_claim(full_text)
            text = claim_text if cut else full_text
            title = clean_text(row.get("title") or "")
            if not text or not title:
                continue
            yield SourceRecord(
                source=self.name,
                source_type=self.kind,
                source_id=row["id"],
                raw_label="hoax",
                title=title,
                text=text,
                text_source="claim" if cut else "full",
                full_text=full_text,
                url=row.get("url") or None,
                published_at=self._parse_datetime(row.get("published_at")),
                view_count=self._parse_int(row.get("view_count")),
                category=row.get("category") or None,
                tags=self._split_list(row.get("tags")),
                topics=self._split_list(row.get("topics")),
                language="id",
                extra={
                    "slug": row.get("slug") or None,
                    "excerpt": clean_text(row.get("excerpt") or "") or None,
                },
            )

    def meta(self) -> SourceMeta:
        return SourceMeta(
            name=self.name,
            kind=self.kind,
            license=self.license,
            url=self.url,
            rows_ingested=len(self._parse_rows()),
        )