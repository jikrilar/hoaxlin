"""Synthetic test records stay in temporary directories, never in the KB."""

from __future__ import annotations

import hashlib
import json
from pathlib import Path
import sys
from types import SimpleNamespace

from fastapi.testclient import TestClient
import numpy as np
import pytest

from app.config import MODEL_ID, MODEL_REVISION
from app.embedding import SentenceTransformerEmbedding
from app.main import RetrieveResponse, create_app


def document(identifier: str, keyword: str) -> dict:
    return {
        "id": identifier,
        "title": f"Test-only {keyword} title",
        "content": f"Test-only {keyword} source content for index behavior.",
        "source": f"Test-only {identifier} publisher",
        "source_url": f"https://example.test/{identifier}",
        "published_at": "2024-02-29" if identifier == "doc-a" else None,
        "topic": None,
    }


def write_kb(directory: Path, documents: list[dict]) -> None:
    raw = "".join(json.dumps(item) + "\n" for item in documents).encode("utf-8")
    (directory / "documents.jsonl").write_bytes(raw)
    (directory / "manifest.json").write_text(
        json.dumps(
            {
                "dataset_name": "hoaxlin-rag-knowledge-base",
                "version": "1.0.0",
                "schema_version": "1.0.0",
                "document_count": len(documents),
                "documents": {
                    "path": "documents.jsonl",
                    "sha256": hashlib.sha256(raw).hexdigest(),
                },
                "provenance": {
                    "source_type": "original_publication_url",
                    "local_input_files": [],
                },
            }
        ),
        encoding="utf-8",
    )


class TestEmbedding:
    dimension = 3

    def encode(self, texts: list[str]) -> np.ndarray:
        vectors = []
        for text in texts:
            lower = text.lower()
            if "alpha" in lower:
                vectors.append([1.0, 0.0, 0.0])
            elif "beta" in lower:
                vectors.append([0.0, 1.0, 0.0])
            else:
                vectors.append([0.0, 0.0, 1.0])
        return np.asarray(vectors, dtype=np.float32)


def fixture_app(directory: Path):
    return create_app(
        knowledge_base_directory=directory, embedding_factory=TestEmbedding
    )


def test_live_health_and_empty_knowledge_base_fixture(tmp_path: Path) -> None:
    write_kb(tmp_path, [])

    def must_not_load_model():
        raise AssertionError("empty fixture must not load an embedding model")

    app = create_app(
        knowledge_base_directory=tmp_path,
        embedding_factory=must_not_load_model,
    )
    with TestClient(app) as client:
        assert client.get("/health/live").json() == {"status": "live"}
        ready = client.get("/health/ready")
        assert ready.status_code == 503
        assert ready.json() == {"status": "not_ready", "reason": "knowledge_base_empty"}
        version = client.get("/version").json()
        assert version == {
            "service_version": "0.1.0",
            "embedding_model_id": MODEL_ID,
            "embedding_model_revision": MODEL_REVISION,
            "knowledge_base_version": "1.0.0",
            "document_count": 0,
            "embedding_dimension": None,
            "vector_count": 0,
        }
        response = client.post("/retrieve", json={"text": "test query", "top_k": 3})
        assert response.status_code == 200
        assert response.json() == {"results": []}


def test_top_k_deterministic_ranking_response_schema_and_provenance(tmp_path: Path) -> None:
    documents = [document("doc-b", "beta"), document("doc-a", "alpha")]
    write_kb(tmp_path, documents)
    with TestClient(fixture_app(tmp_path)) as client:
        assert client.get("/health/ready").json() == {
            "status": "ready",
            "reason": None,
        }
        version = client.get("/version").json()
        assert version["document_count"] == 2
        assert version["embedding_dimension"] == 3
        assert version["vector_count"] == 2

        request = {"text": "alpha", "top_k": 2}
        first = client.post("/retrieve", json=request)
        assert first.status_code == 200
        assert first.json() == client.post("/retrieve", json=request).json()
        parsed = RetrieveResponse.model_validate(first.json())
        assert [result.document_id for result in parsed.results] == ["doc-a", "doc-b"]
        assert [result.rank for result in parsed.results] == [1, 2]
        assert parsed.results[0].score == 1.0
        assert parsed.results[0].title == documents[1]["title"]
        assert parsed.results[0].source == documents[1]["source"]
        assert parsed.results[0].source_url == documents[1]["source_url"]
        assert parsed.results[0].published_at.isoformat() == documents[1]["published_at"]
        assert parsed.results[0].snippet == documents[1]["content"]
        assert parsed.results[1].published_at is None

        one = client.post("/retrieve", json={"text": "alpha", "top_k": 1})
        assert [item["document_id"] for item in one.json()["results"]] == ["doc-a"]


def test_score_is_cosine_similarity_not_confidence_or_raw_dot_product(tmp_path: Path) -> None:
    write_kb(
        tmp_path,
        [
            document("doc-diagonal", "diagonal"),
            document("doc-orthogonal", "orthogonal"),
        ],
    )

    class NonUnitEmbedding:
        dimension = 2

        def encode(self, texts: list[str]) -> np.ndarray:
            vectors = []
            for text in texts:
                lowered = text.lower()
                if lowered == "query":
                    vectors.append([4.0, 0.0])
                elif "diagonal" in lowered:
                    vectors.append([3.0, 3.0])
                else:
                    vectors.append([0.0, 7.0])
            return np.asarray(vectors, dtype=np.float32)

    app = create_app(
        knowledge_base_directory=tmp_path, embedding_factory=NonUnitEmbedding
    )
    with TestClient(app) as client:
        response = client.post("/retrieve", json={"text": "query", "top_k": 2})

    assert response.status_code == 200
    results = response.json()["results"]
    assert results[0]["document_id"] == "doc-diagonal"
    assert results[0]["score"] == pytest.approx(1 / np.sqrt(2))
    assert -1.0 <= results[0]["score"] <= 1.0
    assert "confidence" not in results[0]


def test_ties_are_sorted_by_document_id(tmp_path: Path) -> None:
    write_kb(tmp_path, [document("doc-z", "beta"), document("doc-a", "alpha")])
    with TestClient(fixture_app(tmp_path)) as client:
        results = client.post("/retrieve", json={"text": "unknown"}).json()["results"]
        assert [result["document_id"] for result in results] == ["doc-a", "doc-z"]


def test_min_score_can_return_no_match_without_a_default_threshold(tmp_path: Path) -> None:
    write_kb(tmp_path, [document("doc-a", "alpha"), document("doc-b", "beta")])
    with TestClient(fixture_app(tmp_path)) as client:
        response = client.post(
            "/retrieve", json={"text": "unknown", "top_k": 2, "min_score": 0.1}
        )
        assert response.status_code == 200
        assert response.json() == {"results": []}
        unfiltered = client.post("/retrieve", json={"text": "unknown"})
        assert len(unfiltered.json()["results"]) == 2


def test_chunks_do_not_duplicate_document_results(tmp_path: Path) -> None:
    long_document = document("doc-a", "alpha")
    long_document["content"] = "alpha " * 250
    write_kb(tmp_path, [long_document, document("doc-b", "beta")])
    with TestClient(fixture_app(tmp_path)) as client:
        assert client.get("/version").json()["vector_count"] > 2
        response = client.post("/retrieve", json={"text": "alpha", "top_k": 10})
        identifiers = [item["document_id"] for item in response.json()["results"]]
        assert identifiers.count("doc-a") == 1
        assert len(identifiers) == len(set(identifiers))


@pytest.mark.parametrize(
    "payload",
    [
        {"text": ""},
        {"text": "  "},
        {"text": "x" * 4001},
        {"text": "alpha", "top_k": 0},
        {"text": "alpha", "top_k": 11},
        {"text": "alpha", "top_k": 1.5},
        {"text": "alpha", "min_score": 1.1},
        {"text": "alpha", "min_score": "nan"},
        {"text": "alpha", "filesystem_path": "datasets/challenge/"},
    ],
)
def test_request_validation(tmp_path: Path, payload: dict) -> None:
    write_kb(tmp_path, [document("doc-a", "alpha")])
    with TestClient(fixture_app(tmp_path)) as client:
        assert client.post("/retrieve", json=payload).status_code == 422


def test_query_is_trimmed_before_length_validation(tmp_path: Path) -> None:
    write_kb(tmp_path, [document("doc-a", "alpha")])
    with TestClient(fixture_app(tmp_path)) as client:
        response = client.post("/retrieve", json={"text": "  alpha  "})
        assert response.status_code == 200
        assert response.json()["results"][0]["document_id"] == "doc-a"


@pytest.mark.parametrize("failure", ["malformed_json", "bad_checksum", "duplicate_id", "bad_url"])
def test_malformed_knowledge_base_is_unready_and_public_errors_are_safe(
    tmp_path: Path, failure: str
) -> None:
    first = document("doc-a", "alpha")
    if failure == "duplicate_id":
        write_kb(tmp_path, [first, document("doc-a", "beta")])
    elif failure == "bad_url":
        first["source_url"] = "file:///private/article"
        write_kb(tmp_path, [first])
    else:
        write_kb(tmp_path, [first])
        if failure == "malformed_json":
            (tmp_path / "documents.jsonl").write_text("{bad json}\n", encoding="utf-8")
        else:
            manifest_path = tmp_path / "manifest.json"
            manifest = json.loads(manifest_path.read_text(encoding="utf-8"))
            manifest["documents"]["sha256"] = "0" * 64
            manifest_path.write_text(json.dumps(manifest), encoding="utf-8")
    with TestClient(fixture_app(tmp_path)) as client:
        assert client.get("/health/live").status_code == 200
        ready = client.get("/health/ready")
        assert ready.status_code == 503
        assert ready.json()["reason"] == "knowledge_base_invalid"
        retrieval = client.post("/retrieve", json={"text": "alpha"})
        assert retrieval.status_code == 503
        assert retrieval.json() == {"detail": "retrieval_unavailable"}
        assert str(tmp_path) not in retrieval.text + ready.text


def test_malformed_vector_index_is_unready(tmp_path: Path) -> None:
    write_kb(tmp_path, [document("doc-a", "alpha")])

    class BrokenEmbedding(TestEmbedding):
        def encode(self, texts: list[str]) -> np.ndarray:
            return np.asarray([[float("nan"), 0.0, 0.0]], dtype=np.float32)

    app = create_app(
        knowledge_base_directory=tmp_path, embedding_factory=BrokenEmbedding
    )
    with TestClient(app) as client:
        assert client.get("/health/ready").json()["reason"] == "index_unavailable"
        response = client.post("/retrieve", json={"text": "alpha"})
        assert response.status_code == 503
        assert response.json() == {"detail": "retrieval_unavailable"}


def test_missing_cached_model_is_unready_without_public_exception(tmp_path: Path) -> None:
    write_kb(tmp_path, [document("doc-a", "alpha")])

    def missing_model():
        raise FileNotFoundError(str(tmp_path / "private-model-path"))

    app = create_app(
        knowledge_base_directory=tmp_path, embedding_factory=missing_model
    )
    with TestClient(app) as client:
        assert client.get("/health/ready").json()["reason"] == "index_unavailable"
        response = client.post("/retrieve", json={"text": "alpha"})
        assert response.status_code == 503
        assert str(tmp_path) not in response.text


def test_runtime_embedding_error_returns_sanitized_503(tmp_path: Path) -> None:
    write_kb(tmp_path, [document("doc-a", "alpha")])

    class FailingQueryEmbedding(TestEmbedding):
        def encode(self, texts: list[str]) -> np.ndarray:
            if len(texts) == 1 and texts[0] == "alpha":
                raise OSError(str(tmp_path / "private-runtime-path"))
            return super().encode(texts)

    app = create_app(
        knowledge_base_directory=tmp_path, embedding_factory=FailingQueryEmbedding
    )
    with TestClient(app) as client:
        response = client.post("/retrieve", json={"text": "alpha"})
        assert response.status_code == 503
        assert response.json() == {"detail": "retrieval_unavailable"}
        assert client.get("/health/ready").json()["reason"] == "index_unavailable"


def test_pretrained_adapter_is_cpu_only_and_offline_at_runtime(monkeypatch) -> None:
    calls: dict[str, object] = {}

    class Model:
        def __init__(self, identifier, **kwargs):
            calls["identifier"] = identifier
            calls["kwargs"] = kwargs

        def get_embedding_dimension(self):
            return 3

        def encode(self, texts, **kwargs):
            calls["texts"] = texts
            calls["encode_kwargs"] = kwargs
            return [[1.0, 0.0, 0.0]]

    monkeypatch.setitem(
        sys.modules, "sentence_transformers", SimpleNamespace(SentenceTransformer=Model)
    )
    backend = SentenceTransformerEmbedding()
    assert backend.dimension == 3
    assert backend.encode(["alpha"]).shape == (1, 3)
    assert calls["identifier"] == MODEL_ID
    assert calls["kwargs"] == {
        "revision": MODEL_REVISION,
        "device": "cpu",
        "local_files_only": True,
    }
    assert calls["texts"] == ["alpha"]
