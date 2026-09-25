"""Validate the versioned RAG corpus without importing classifier data."""

from __future__ import annotations

import argparse
from datetime import date
import hashlib
import ipaddress
import json
from pathlib import Path
import re
import unicodedata
from urllib.parse import urlsplit


DATASET_ROOT = Path(__file__).resolve().parents[1] / "datasets/rag"
LEGACY_DIRECTORY = DATASET_ROOT / "knowledge-base-v1"
DEFAULT_DIRECTORY = DATASET_ROOT / "knowledge-base-v1.1.1"
SUPPORTED_VERSIONS = frozenset({"1.0.0", "1.1.0", "1.1.1"})
DOCUMENT_FIELDS = frozenset(
    {"id", "title", "content", "source", "source_url", "published_at", "topic"}
)
MANIFEST_FIELDS = frozenset(
    {"dataset_name", "version", "schema_version", "document_count", "documents", "provenance"}
)
DATE_PATTERN = re.compile(r"\d{4}-\d{2}-\d{2}\Z")
SHA256_PATTERN = re.compile(r"[0-9a-f]{64}\Z")
DNS_LABEL_PATTERN = re.compile(r"[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\Z")
BAD_PERCENT_PATTERN = re.compile(r"%(?![0-9a-fA-F]{2})")


class ValidationError(ValueError):
    """The corpus or its manifest violates the RAG data contract."""


def _unique_keys(pairs: list[tuple[str, object]]) -> dict[str, object]:
    value: dict[str, object] = {}
    for key, item in pairs:
        if key in value:
            raise ValidationError(f"duplicate JSON key: {key}")
        value[key] = item
    return value


def _reject_constant(value: str) -> None:
    raise ValidationError(f"non-JSON numeric constant: {value}")


def _parse_json(raw: str, location: str) -> object:
    try:
        return json.loads(
            raw, object_pairs_hook=_unique_keys, parse_constant=_reject_constant
        )
    except (json.JSONDecodeError, ValidationError) as exc:
        raise ValidationError(f"{location}: {exc}") from exc


def _exact_keys(value: object, expected: frozenset[str], location: str) -> dict:
    if not isinstance(value, dict):
        raise ValidationError(f"{location}: expected a JSON object")
    missing = expected - value.keys()
    extra = value.keys() - expected
    if missing or extra:
        raise ValidationError(
            f"{location}: fields mismatch (missing={sorted(missing)}, extra={sorted(extra)})"
        )
    return value


def _nonempty_string(value: object, location: str) -> str:
    if not isinstance(value, str) or not value.strip():
        raise ValidationError(f"{location}: expected a nonempty string")
    return value


def _valid_source_url(value: str, location: str) -> None:
    if any(char.isspace() or ord(char) < 32 for char in value):
        raise ValidationError(f"{location}: URL contains whitespace or control characters")
    if BAD_PERCENT_PATTERN.search(value):
        raise ValidationError(f"{location}: URL has invalid percent encoding")
    try:
        url = urlsplit(value)
        hostname = url.hostname
        port = url.port
    except ValueError as exc:
        raise ValidationError(f"{location}: invalid URL: {exc}") from exc
    if url.scheme not in {"http", "https"} or not hostname or url.username or url.password:
        raise ValidationError(f"{location}: expected an HTTP/HTTPS URL with a host")
    if url.netloc.endswith(":") or (port is not None and port == 0):
        raise ValidationError(f"{location}: invalid port")
    try:
        ipaddress.ip_address(hostname)
    except ValueError:
        try:
            ascii_host = hostname.encode("idna").decode("ascii").lower()
        except UnicodeError as exc:
            raise ValidationError(f"{location}: invalid hostname") from exc
        if len(ascii_host) > 253 or any(
            not DNS_LABEL_PATTERN.fullmatch(label) for label in ascii_host.split(".")
        ):
            raise ValidationError(f"{location}: invalid hostname")


def _normalized_content(value: str) -> str:
    return " ".join(unicodedata.normalize("NFKC", value).split()).casefold()


def _validate_document(value: object, line_number: int) -> dict:
    location = f"documents.jsonl line {line_number}"
    document = _exact_keys(value, DOCUMENT_FIELDS, location)
    for field in ("id", "title", "content", "source", "source_url"):
        _nonempty_string(document[field], f"{location} {field}")
    _valid_source_url(document["source_url"], f"{location} source_url")

    published_at = document["published_at"]
    if published_at is not None:
        if not isinstance(published_at, str) or not DATE_PATTERN.fullmatch(published_at):
            raise ValidationError(f"{location} published_at: expected YYYY-MM-DD or null")
        try:
            date.fromisoformat(published_at)
        except ValueError as exc:
            raise ValidationError(f"{location} published_at: invalid calendar date") from exc

    topic = document["topic"]
    if topic is not None:
        _nonempty_string(topic, f"{location} topic")
    return document


def _validate_manifest(value: object) -> dict:
    manifest = _exact_keys(value, MANIFEST_FIELDS, "manifest.json")
    if manifest["dataset_name"] != "hoaxlin-rag-knowledge-base":
        raise ValidationError("manifest.json: unexpected dataset_name")
    if (
        not isinstance(manifest["version"], str)
        or manifest["version"] not in SUPPORTED_VERSIONS
        or manifest["schema_version"] != "1.0.0"
    ):
        raise ValidationError("manifest.json: unsupported version or schema_version")
    if type(manifest["document_count"]) is not int or manifest["document_count"] < 0:
        raise ValidationError("manifest.json: document_count must be a nonnegative integer")

    documents = _exact_keys(
        manifest["documents"], frozenset({"path", "sha256"}), "manifest.json documents"
    )
    if documents["path"] != "documents.jsonl":
        raise ValidationError("manifest.json: documents.path must be documents.jsonl")
    if not isinstance(documents["sha256"], str) or not SHA256_PATTERN.fullmatch(
        documents["sha256"]
    ):
        raise ValidationError("manifest.json: documents.sha256 must be lowercase SHA-256")

    provenance = _exact_keys(
        manifest["provenance"],
        frozenset({"source_type", "local_input_files"}),
        "manifest.json provenance",
    )
    if provenance["source_type"] != "original_publication_url":
        raise ValidationError("manifest.json: source_type must be original_publication_url")
    if provenance["local_input_files"] != []:
        raise ValidationError(
            "manifest.json: local_input_files must be empty; "
            "datasets/processed/ and datasets/challenge/ are forbidden inputs"
        )
    return manifest


def validate_knowledge_base(directory: Path = DEFAULT_DIRECTORY) -> int:
    """Return the verified document count or raise ValidationError."""
    directory = Path(directory)
    if directory.is_symlink():
        raise ValidationError("knowledge-base directory must not be a symlink")
    documents_path = directory / "documents.jsonl"
    manifest_path = directory / "manifest.json"
    for path in (documents_path, manifest_path):
        if path.is_symlink():
            raise ValidationError(f"{path.name}: symlink inputs are forbidden")
        if not path.is_file():
            raise ValidationError(f"{path.name}: file is missing")

    try:
        manifest = _validate_manifest(
            _parse_json(manifest_path.read_text(encoding="utf-8"), "manifest.json")
        )
        raw_documents = documents_path.read_bytes()
        document_text = raw_documents.decode("utf-8")
    except (OSError, UnicodeError) as exc:
        raise ValidationError(f"cannot read knowledge base as UTF-8: {exc}") from exc

    ids: set[str] = set()
    contents: set[str] = set()
    count = 0
    for line_number, line in enumerate(document_text.splitlines(), start=1):
        if not line.strip():
            raise ValidationError(f"documents.jsonl line {line_number}: blank line")
        document = _validate_document(
            _parse_json(line, f"documents.jsonl line {line_number}"), line_number
        )
        if document["id"] in ids:
            raise ValidationError(f"documents.jsonl line {line_number}: duplicate id")
        ids.add(document["id"])
        normalized = _normalized_content(document["content"])
        if normalized in contents:
            raise ValidationError(
                f"documents.jsonl line {line_number}: duplicate normalized content"
            )
        contents.add(normalized)
        count += 1

    if manifest["document_count"] != count:
        raise ValidationError(
            f"manifest.json: document_count={manifest['document_count']} but found {count}"
        )
    digest = hashlib.sha256(raw_documents).hexdigest()
    if manifest["documents"]["sha256"] != digest:
        raise ValidationError("manifest.json: documents.sha256 does not match dataset SHA-256")
    return count


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--directory", type=Path, default=DEFAULT_DIRECTORY)
    args = parser.parse_args()
    try:
        count = validate_knowledge_base(args.directory)
    except ValidationError as exc:
        parser.exit(1, f"Knowledge base invalid: {exc}\n")
    print(f"Knowledge base valid: {count} documents")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
