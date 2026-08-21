from __future__ import annotations

import csv
import os
from collections.abc import Iterator
from datetime import datetime, timezone
from pathlib import Path

from dataset.normalize import clean_text
from dataset.schema import SourceMeta, SourceRecord
from dataset.sources.base import AdapterError, SourceAdapter

REQUIRED_COLUMNS = {"text", "label"}


class LabeledCsvAdapter(SourceAdapter):
    """Generic adapter for labeled text corpora (e.g. a valid-news export).

    Drop-in source for the ``valid`` class: any CSV with at least ``text``
    and ``label`` columns (plus optional ``title``, ``url``, ``published_at``,
    ``category``, ``topics``, ``id``) is accepted. Labels are reconciled by
    :mod:`dataset.reconcile`.
    """

    name = "labeled_csv"
    kind = "labeled_export"
    license = "varies by file; record in the source manifest"
    url = None

    def __init__(self, path: str | os.PathLike[str], source_name: str | None = None) -> None:
        self.path = Path(path)
        self.source_name = source_name or f"labeled:{self.path.stem}"
        if not self.path.is_file():
            raise AdapterError(f"CSV not found: {self.path}")

    def _parse_rows(self) -> list[dict[str, str]]:
        try:
            with self.path.open(encoding="utf-8-sig", newline="") as handle:
                reader = csv.DictReader(handle)
                if reader.fieldnames is None:
                    raise AdapterError(f"empty CSV: {self.path}")
                missing = REQUIRED_COLUMNS - set(reader.fieldnames)
                if missing:
                    raise AdapterError(
                        f"CSV missing columns {sorted(missing)} (found {sorted(reader.fieldnames)})"
                    )
                return list(reader)
        except UnicodeDecodeError as exc:
            raise AdapterError(f"CSV is not UTF-8 encoded: {self.path}") from exc
        except csv.Error as exc:
            raise AdapterError(f"malformed CSV: {self.path}: {exc}") from exc

    def _parse_datetime(self, value: str | None) -> datetime | None:
        if not value:
            return None
        try:
            parsed = datetime.fromisoformat(value.strip().replace("Z", "+00:00"))
        except ValueError:
            return None
        return parsed.astimezone(timezone.utc) if parsed.tzinfo else parsed

    def read(self) -> Iterator[SourceRecord]:
        for idx, row in enumerate(self._parse_rows()):
            text = clean_text(row.get("text") or "")
            title = clean_text(row.get("title") or "") or text[:120]
            if not text:
                continue
            yield SourceRecord(
                source=self.source_name,
                source_type=self.kind,
                source_id=row.get("id") or f"row-{idx}",
                raw_label=row.get("label"),
                title=title,
                text=text,
                url=row.get("url") or None,
                published_at=self._parse_datetime(row.get("published_at")),
                view_count=None,
                category=row.get("category") or None,
                tags=[],
                topics=[],
                language=row.get("language") or "id",
            )

    def meta(self) -> SourceMeta:
        return SourceMeta(
            name=self.source_name,
            kind=self.kind,
            license=self.license,
            url=self.url,
            rows_ingested=len(self._parse_rows()),
        )