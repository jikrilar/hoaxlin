"""Load only an R2-validated snapshot of the versioned knowledge base."""

from __future__ import annotations

from dataclasses import dataclass
import hashlib
import json
from pathlib import Path
import sys


# The R2 validator is shared with the dataset, not duplicated in the service.
REPOSITORY_ROOT = Path(__file__).resolve().parents[2]
if str(REPOSITORY_ROOT) not in sys.path:
    sys.path.insert(0, str(REPOSITORY_ROOT))

from scripts.validate_rag_knowledge_base import (  # noqa: E402
    ValidationError,
    validate_knowledge_base,
)


class KnowledgeBaseError(RuntimeError):
    """The knowledge base cannot be trusted for retrieval."""


@dataclass(frozen=True)
class Document:
    id: str
    title: str
    content: str
    source: str
    source_url: str
    published_at: str | None
    topic: str | None


@dataclass(frozen=True)
class KnowledgeBase:
    version: str
    documents: tuple[Document, ...]
    sha256: str


def load_knowledge_base(directory: Path) -> KnowledgeBase:
    """Validate before parsing; reject changes between validation and loading."""
    try:
        count = validate_knowledge_base(directory)
        manifest = json.loads((directory / "manifest.json").read_text(encoding="utf-8"))
        raw = (directory / "documents.jsonl").read_bytes()
        checksum = hashlib.sha256(raw).hexdigest()
        if checksum != manifest["documents"]["sha256"]:
            raise KnowledgeBaseError("knowledge_base_changed")
        documents = tuple(
            Document(**json.loads(line)) for line in raw.decode("utf-8").splitlines()
        )
        if len(documents) != count:
            raise KnowledgeBaseError("knowledge_base_changed")
        return KnowledgeBase(manifest["version"], documents, checksum)
    except (ValidationError, OSError, UnicodeError, ValueError, KeyError, TypeError) as exc:
        raise KnowledgeBaseError("knowledge_base_invalid") from exc
