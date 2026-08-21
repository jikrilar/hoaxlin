from __future__ import annotations

from datetime import datetime
from enum import Enum
from typing import Any, Literal

from pydantic import BaseModel, ConfigDict, Field, field_validator

Label = Literal["hoax", "valid"]

CANONICAL_LABELS: tuple[str, ...] = ("hoax", "valid")

# Label aliases reconciled to canonical labels. ``meragukan`` (uncertain) is
# intentionally excluded from the training set: the deployment system derives
# "meragukan" from confidence thresholds, it is not a trainable class.
LABEL_ALIASES: dict[str, str] = {
    "hoax": "hoax",
    "hoaks": "hoax",
    "hoaks:": "hoax",
    "hoax:": "hoax",
    "false": "hoax",
    "salah": "hoax",
    "keliru": "hoax",
    "misinformasi": "hoax",
    "disinformasi": "hoax",
    "penipuan": "hoax",
    "valid": "valid",
    "true": "valid",
    "benar": "valid",
    "fakta": "valid",
    "fact": "valid",
    "info": "valid",
}

SCHEMA_VERSION = "1.0.0"


class SplitName(str, Enum):
    train = "train"
    val = "val"
    test = "test"


class SourceRecord(BaseModel):
    """Raw record produced by a source adapter, before canonicalization."""

    model_config = ConfigDict(extra="allow")

    source: str
    source_type: str
    source_id: str
    raw_label: str | None = None
    title: str
    text: str
    text_source: Literal["claim", "full"] = "full"
    full_text: str | None = None
    url: str | None = None
    published_at: datetime | None = None
    view_count: int | None = None
    category: str | None = None
    tags: list[str] = Field(default_factory=list)
    topics: list[str] = Field(default_factory=list)
    language: str = "id"
    extra: dict[str, Any] = Field(default_factory=dict)


class CanonicalRecord(BaseModel):
    """One deduplicated, reconciled, ready-to-train record."""

    model_config = ConfigDict(extra="forbid")

    record_id: str
    source: str
    source_type: str
    label: Label
    title: str
    text: str
    text_source: Literal["claim", "full"] = "full"
    full_text: str
    url: str | None = None
    published_at: datetime | None = None
    view_count: int | None = None
    category: str | None = None
    tags: list[str] = Field(default_factory=list)
    topics: list[str] = Field(default_factory=list)
    language: str = "id"
    claim_group: str = Field(min_length=1)
    source_hash: str = Field(min_length=1)
    record_hash: str = ""

    @field_validator("label")
    @classmethod
    def label_must_be_canonical(cls, value: str) -> str:
        if value not in CANONICAL_LABELS:
            raise ValueError(f"unsupported label: {value!r}")
        return value

    def to_jsonl(self) -> dict[str, Any]:
        """Flatten for the training artifact (HuggingFace ``load_dataset("json")`` compatible)."""
        return self.model_dump(mode="json")


class RejectedRecord(BaseModel):
    model_config = ConfigDict(extra="forbid")

    record_id: str
    source: str
    reason: str
    title: str
    detail: str | None = None


class SourceMeta(BaseModel):
    name: str
    kind: str
    license: str
    url: str | None = None
    retrieved_at: str | None = None
    rows_ingested: int = 0
    rows_kept: int = 0


class SplitConfig(BaseModel):
    mode: Literal["group", "time"] = "group"
    train_ratio: float = Field(gt=0.0, lt=1.0)
    val_ratio: float = Field(gt=0.0, lt=1.0)
    test_ratio: float = Field(gt=0.0, lt=1.0)
    seed: int = 42
    time_column: str = "published_at"

    def validate_ratios(self) -> None:
        total = self.train_ratio + self.val_ratio + self.test_ratio
        if abs(total - 1.0) > 1e-9:
            raise ValueError(f"split ratios must sum to 1.0, got {total}")