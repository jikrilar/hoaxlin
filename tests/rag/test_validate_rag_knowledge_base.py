"""Contract tests use synthetic fixtures only; none enter the real corpus."""

from __future__ import annotations

import hashlib
import json
from pathlib import Path
import sys
import tempfile
import unittest


sys.path.insert(0, str(Path(__file__).resolve().parents[2]))
from scripts.validate_rag_knowledge_base import (  # noqa: E402
    DEFAULT_DIRECTORY,
    LEGACY_DIRECTORY,
    ValidationError,
    validate_knowledge_base,
)

CANDIDATE_DIRECTORY = LEGACY_DIRECTORY.parent / "knowledge-base-v1.1.0"


def fixture_document(**changes: object) -> dict:
    document = {
        "id": "test-1",
        "title": "Test-only title",
        "content": "Test-only content for validator coverage.",
        "source": "Test-only publisher",
        "source_url": "https://example.test/article/1",
        "published_at": None,
        "topic": None,
    }
    document.update(changes)
    return document


class KnowledgeBaseValidatorTest(unittest.TestCase):
    def setUp(self) -> None:
        temporary = tempfile.TemporaryDirectory()
        self.addCleanup(temporary.cleanup)
        self.directory = Path(temporary.name)

    def write_fixture(self, documents: list[dict]) -> dict:
        raw = "".join(json.dumps(doc, ensure_ascii=False) + "\n" for doc in documents)
        return self.write_raw(raw)

    def write_raw(self, raw: str) -> dict:
        dataset_bytes = raw.encode("utf-8")
        (self.directory / "documents.jsonl").write_bytes(dataset_bytes)
        manifest = {
            "dataset_name": "hoaxlin-rag-knowledge-base",
            "version": "1.0.0",
            "schema_version": "1.0.0",
            "document_count": len(raw.splitlines()),
            "documents": {
                "path": "documents.jsonl",
                "sha256": hashlib.sha256(dataset_bytes).hexdigest(),
            },
            "provenance": {
                "source_type": "original_publication_url",
                "local_input_files": [],
            },
        }
        self.write_manifest(manifest)
        return manifest

    def write_manifest(self, manifest: dict) -> None:
        (self.directory / "manifest.json").write_text(
            json.dumps(manifest), encoding="utf-8"
        )

    def assert_invalid(self, pattern: str) -> None:
        with self.assertRaisesRegex(ValidationError, pattern):
            validate_knowledge_base(self.directory)

    def test_real_corpus_is_valid_and_matches_manifest_count(self) -> None:
        manifest = json.loads(
            (DEFAULT_DIRECTORY / "manifest.json").read_text(encoding="utf-8")
        )
        document_count = validate_knowledge_base(DEFAULT_DIRECTORY)
        self.assertGreater(document_count, 0)
        self.assertEqual(document_count, manifest["document_count"])
        self.assertEqual(manifest["version"], "1.1.1")
        self.assertEqual(document_count, 61)

    def test_unreviewed_candidate_snapshot_remains_valid_and_unchanged(self) -> None:
        manifest = json.loads(
            (CANDIDATE_DIRECTORY / "manifest.json").read_text(encoding="utf-8")
        )
        self.assertEqual(validate_knowledge_base(CANDIDATE_DIRECTORY), 98)
        self.assertEqual(manifest["version"], "1.1.0")
        self.assertEqual(
            manifest["documents"]["sha256"],
            "2d6945ea68b2be604e8d2737cfbed5f33e6c2d61fa468b5cc2a5aafc3daf4bdc",
        )

    def test_legacy_snapshot_remains_valid_and_unchanged(self) -> None:
        manifest = json.loads(
            (LEGACY_DIRECTORY / "manifest.json").read_text(encoding="utf-8")
        )
        self.assertEqual(validate_knowledge_base(LEGACY_DIRECTORY), 24)
        self.assertEqual(manifest["version"], "1.0.0")
        self.assertEqual(
            manifest["documents"]["sha256"],
            "1f275303f523be70c5f507b199eeb418f06b7d85a8f1dba449537b386a3ef1e2",
        )

    def test_complete_schema_accepts_nullable_fields_and_real_date(self) -> None:
        self.write_fixture(
            [fixture_document(published_at="2024-02-29", topic="Test topic")]
        )
        self.assertEqual(validate_knowledge_base(self.directory), 1)

    def test_jsonl_rejects_malformed_json_blank_line_and_duplicate_keys(self) -> None:
        for raw, pattern in (
            ('{"id":\n', "documents.jsonl line 1"),
            ("\n", "blank line"),
            ('{"id":"a","id":"b"}\n', "duplicate JSON key"),
            (json.dumps(fixture_document(content=float("nan"))) + "\n", "non-JSON"),
        ):
            with self.subTest(raw=raw):
                self.write_raw(raw)
                self.assert_invalid(pattern)

    def test_jsonl_rejects_invalid_utf8(self) -> None:
        self.write_fixture([])
        (self.directory / "documents.jsonl").write_bytes(b"\xff")
        self.assert_invalid("UTF-8")

    def test_schema_rejects_missing_and_extra_fields(self) -> None:
        for field in ("id", "title", "content", "source", "source_url", "published_at", "topic"):
            with self.subTest(field=field):
                document = fixture_document()
                del document[field]
                self.write_fixture([document])
                self.assert_invalid("fields mismatch")
        self.write_fixture([fixture_document(local_input_file="datasets/processed/train.jsonl")])
        self.assert_invalid("fields mismatch")

    def test_required_strings_cannot_be_empty_or_wrong_type(self) -> None:
        for field in ("id", "title", "content", "source", "source_url"):
            for bad_value in ("  ", None, 42):
                with self.subTest(field=field, value=bad_value):
                    self.write_fixture([fixture_document(**{field: bad_value})])
                    self.assert_invalid("nonempty string")

    def test_id_must_be_unique(self) -> None:
        self.write_fixture(
            [fixture_document(), fixture_document(content="Different test-only content")]
        )
        self.assert_invalid("duplicate id")

    def test_normalized_content_must_be_unique(self) -> None:
        self.write_fixture(
            [
                fixture_document(content="Ａ test\nCONTENT"),
                fixture_document(id="test-2", content="a  test content"),
            ]
        )
        self.assert_invalid("duplicate normalized content")

    def test_source_url_must_be_valid_http_or_https(self) -> None:
        for url in (
            "file:///datasets/challenge/item.json",
            "https:///missing-host",
            "https://user:pass@example.test/article",
            "https://example.test:99999/article",
            "https://bad_host.test/article",
            "https://example.test/a b",
            "https://example.test/%zz",
        ):
            with self.subTest(url=url):
                self.write_fixture([fixture_document(source_url=url)])
                self.assert_invalid("URL|hostname|port")
        self.write_fixture([fixture_document(source_url="http://example.test/article")])
        self.assertEqual(validate_knowledge_base(self.directory), 1)

    def test_published_at_must_be_null_or_real_iso_date(self) -> None:
        for value in ("2023-02-29", "2024-2-9", "2024-01-01T10:00:00Z", 20240101):
            with self.subTest(value=value):
                self.write_fixture([fixture_document(published_at=value)])
                self.assert_invalid("published_at")

    def test_topic_must_be_null_or_nonempty_string(self) -> None:
        for value in (" ", 3):
            with self.subTest(value=value):
                self.write_fixture([fixture_document(topic=value)])
                self.assert_invalid("topic")

    def test_manifest_checks_count_and_dataset_checksum(self) -> None:
        manifest = self.write_fixture([fixture_document()])
        manifest["document_count"] = 2
        self.write_manifest(manifest)
        self.assert_invalid("document_count")

        manifest["document_count"] = 1
        manifest["documents"]["sha256"] = "0" * 64
        self.write_manifest(manifest)
        self.assert_invalid("SHA-256")

    def test_manifest_rejects_wrong_dataset_path_and_version(self) -> None:
        manifest = self.write_fixture([])
        manifest["documents"]["path"] = "../../processed/komdigi-antara-v1/train.jsonl"
        self.write_manifest(manifest)
        self.assert_invalid("documents.path")

        manifest["documents"]["path"] = "documents.jsonl"
        manifest["version"] = "9.0.0"
        self.write_manifest(manifest)
        self.assert_invalid("unsupported version")

        manifest["version"] = "1.0.0"
        manifest["schema_version"] = "2.0.0"
        self.write_manifest(manifest)
        self.assert_invalid("version")

    def test_manifest_rejects_any_local_input_including_training_and_challenge(self) -> None:
        for local_path in (
            "datasets/processed/komdigi-antara-v1/train.jsonl",
            "datasets/challenge/external-challenge-v1/challenge.csv",
            "other/local/file.jsonl",
        ):
            with self.subTest(local_path=local_path):
                manifest = self.write_fixture([])
                manifest["provenance"]["local_input_files"] = [local_path]
                self.write_manifest(manifest)
                self.assert_invalid("local_input_files")

    def test_manifest_rejects_undeclared_metadata_and_source_type(self) -> None:
        manifest = self.write_fixture([])
        manifest["training_dataset"] = "datasets/processed/komdigi-antara-v1"
        self.write_manifest(manifest)
        self.assert_invalid("fields mismatch")

        del manifest["training_dataset"]
        manifest["provenance"]["source_type"] = "classifier_training_data"
        self.write_manifest(manifest)
        self.assert_invalid("source_type")


if __name__ == "__main__":
    unittest.main()
