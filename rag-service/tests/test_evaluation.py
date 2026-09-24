"""Retrieval evaluator tests use temporary corpora and deterministic embeddings."""

from __future__ import annotations

import hashlib
import json
import os
from pathlib import Path
import sys

import numpy as np
import pytest


REPOSITORY_ROOT = Path(__file__).resolve().parents[2]
if str(REPOSITORY_ROOT) not in sys.path:
    sys.path.insert(0, str(REPOSITORY_ROOT))

from scripts.evaluate_rag_retrieval import (  # noqa: E402
    EvaluationError,
    load_evaluation_dataset,
    run_evaluation,
    write_artifact,
)
from scripts.rag_retrieval_metrics import (  # noqa: E402
    MetricInputError,
    RetrievalCase,
    calculate_metrics,
)
from app.knowledge_base import load_knowledge_base  # noqa: E402


def document(identifier: str, keyword: str) -> dict[str, object]:
    return {
        "id": identifier,
        "title": f"Test-only {keyword} title",
        "content": f"Test-only {keyword} evidence for metric evaluation.",
        "source": f"Test-only {identifier} source",
        "source_url": f"https://example.test/{identifier}",
        "published_at": None,
        "topic": "test-only",
    }


def write_corpus(directory: Path, documents: list[dict[str, object]]) -> str:
    directory.mkdir(parents=True, exist_ok=True)
    raw = "".join(
        json.dumps(item, ensure_ascii=False, separators=(",", ":")) + "\n"
        for item in documents
    ).encode("utf-8")
    (directory / "documents.jsonl").write_bytes(raw)
    manifest = {
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
    (directory / "manifest.json").write_text(
        json.dumps(manifest, ensure_ascii=False, indent=2) + "\n", encoding="utf-8"
    )
    return manifest["documents"]["sha256"]


def write_evaluation(
    directory: Path,
    corpus_directory: Path,
    records: list[dict[str, object]],
) -> None:
    directory.mkdir(parents=True, exist_ok=True)
    raw = "".join(
        json.dumps(record, ensure_ascii=False, separators=(",", ":")) + "\n"
        for record in records
    ).encode("utf-8")
    (directory / "queries.jsonl").write_bytes(raw)
    corpus = load_knowledge_base(corpus_directory)
    manifest = {
        "dataset_name": "hoaxlin-rag-retrieval-evaluation",
        "version": "1.0.0",
        "schema_version": "1.0.0",
        "query_count": len(records),
        "queries": {
            "path": "queries.jsonl",
            "sha256": hashlib.sha256(raw).hexdigest(),
        },
        "corpus": {
            "path": os.path.relpath(corpus_directory, directory).replace("\\", "/"),
            "version": corpus.version,
            "documents_sha256": corpus.sha256,
        },
        "evaluation_script_version": "1.0.0",
    }
    (directory / "manifest.json").write_text(
        json.dumps(manifest, ensure_ascii=False, indent=2) + "\n", encoding="utf-8"
    )


class FixtureEmbedding:
    dimension = 3
    model_id = "test/deterministic-embedding"
    model_revision = "fixture-v1"

    def encode(self, texts: list[str]) -> np.ndarray:
        vectors = []
        for text in texts:
            lowered = text.lower()
            if "alpha" in lowered:
                vectors.append([1.0, 0.0, 0.0])
            elif "beta" in lowered:
                vectors.append([0.0, 1.0, 0.0])
            else:
                vectors.append([0.0, 0.0, 1.0])
        return np.asarray(vectors, dtype=np.float32)


def test_hit_precision_recall_and_mrr_cover_ranks_one_three_five_and_no_match() -> None:
    cases = (
        RetrievalCase("q1", "alpha", ("a",)),
        RetrievalCase("q2", "beta", ("b",)),
        RetrievalCase("q3", "gamma", ("c",)),
        RetrievalCase("q4", "delta", ("d",)),
        RetrievalCase("q5", "epsilon", ("e", "f")),
        RetrievalCase("q6", "empty", ("missing",)),
    )
    rankings = {
        "q6": [],
        "q5": ["e", "e", "other"],
        "q4": ["x", "y"],
        "q3": ["x", "y", "z", "w", "c"],
        "q2": ["x", "y", "b", "z", "w"],
        "q1": ["a", "x", "x"],
    }

    result = calculate_metrics(cases, rankings)

    assert result["query_count"] == 6
    assert result["query_hits"] == {"@3": 3, "@5": 4}
    assert result["metrics"] == {
        "hit_rate@3": 0.5,
        "precision@3": 0.222222,
        "recall@3": 0.416667,
        "hit_rate@5": 0.666667,
        "precision@5": 0.233333,
        "recall@5": 0.583333,
        "mrr": 0.422222,
    }


def test_metrics_are_deterministic_and_independent_of_query_mapping_order() -> None:
    cases = (
        RetrievalCase("q-b", "beta", ("b",)),
        RetrievalCase("q-a", "alpha", ("a",)),
    )
    first = calculate_metrics(cases, {"q-b": ["b"], "q-a": ["a"]})
    second = calculate_metrics(tuple(reversed(cases)), {"q-a": ["a"], "q-b": ["b"]})

    assert first == second


@pytest.mark.parametrize(
    ("cases", "rankings", "message"),
    [
        ((), {}, "evaluation_queries_empty"),
        ((RetrievalCase("q", "claim", ()),), {"q": []}, "empty_relevant_document_ids"),
        ((RetrievalCase("q", "claim", ("a",)),), {}, "retrieval_query_ids_mismatch"),
    ],
)
def test_metric_calculator_rejects_invalid_inputs(
    cases: tuple[RetrievalCase, ...],
    rankings: dict[str, list[str]],
    message: str,
) -> None:
    with pytest.raises(MetricInputError, match=message):
        calculate_metrics(cases, rankings)


def test_loader_rejects_unknown_relevant_document_id(tmp_path: Path) -> None:
    corpus_directory = tmp_path / "corpus"
    evaluation_directory = tmp_path / "evaluation"
    write_corpus(corpus_directory, [document("doc-alpha", "alpha")])
    write_evaluation(
        evaluation_directory,
        corpus_directory,
        [{"id": "q-1", "query": "alpha", "relevant_document_ids": ["missing"]}],
    )

    knowledge_base = load_knowledge_base(corpus_directory)
    with pytest.raises(EvaluationError, match="unknown_relevant_document_ids"):
        load_evaluation_dataset(evaluation_directory, corpus_directory, knowledge_base)


def test_loader_rejects_empty_relevance_and_manifest_checksum_mismatch(tmp_path: Path) -> None:
    corpus_directory = tmp_path / "corpus"
    evaluation_directory = tmp_path / "evaluation"
    write_corpus(corpus_directory, [document("doc-alpha", "alpha")])
    write_evaluation(
        evaluation_directory,
        corpus_directory,
        [{"id": "q-1", "query": "alpha", "relevant_document_ids": []}],
    )
    knowledge_base = load_knowledge_base(corpus_directory)
    with pytest.raises(EvaluationError, match="relevant_document_ids_must_be_nonempty"):
        load_evaluation_dataset(evaluation_directory, corpus_directory, knowledge_base)

    write_evaluation(
        evaluation_directory,
        corpus_directory,
        [{"id": "q-1", "query": "alpha", "relevant_document_ids": ["doc-alpha"]}],
    )
    query_path = evaluation_directory / "queries.jsonl"
    query_path.write_bytes(query_path.read_bytes() + b" ")
    with pytest.raises(EvaluationError, match="queries_checksum_mismatch"):
        load_evaluation_dataset(evaluation_directory, corpus_directory, knowledge_base)


def test_runner_and_artifact_are_deterministic_with_a_fixture_corpus(tmp_path: Path) -> None:
    corpus_directory = tmp_path / "corpus"
    evaluation_directory = tmp_path / "evaluation"
    documents = [
        document("doc-alpha", "alpha"),
        document("doc-beta", "beta"),
        document("doc-gamma", "gamma"),
    ]
    write_corpus(corpus_directory, documents)
    write_evaluation(
        evaluation_directory,
        corpus_directory,
        [
            {"id": "eval-alpha", "query": "alpha claim", "relevant_document_ids": ["doc-alpha"]},
            {"id": "eval-beta", "query": "beta claim", "relevant_document_ids": ["doc-beta"]},
            {
                "id": "eval-multiple",
                "query": "alpha beta claim",
                "relevant_document_ids": ["doc-alpha", "doc-beta"],
            },
            {"id": "eval-gamma", "query": "gamma claim", "relevant_document_ids": ["doc-gamma"]},
        ],
    )

    first = run_evaluation(
        evaluation_directory,
        corpus_directory,
        embedding_factory=FixtureEmbedding,
        threshold_candidates=(0.0, 0.8),
    )
    second = run_evaluation(
        evaluation_directory,
        corpus_directory,
        embedding_factory=FixtureEmbedding,
        threshold_candidates=(0.0, 0.8),
    )
    first_path = tmp_path / "first.json"
    second_path = tmp_path / "second.json"
    write_artifact(first_path, first)
    write_artifact(second_path, second)

    assert first == second
    assert first_path.read_bytes() == second_path.read_bytes()
    assert first["status"] == "complete"
    assert first["evaluation_dataset"]["query_count"] == 4
    assert first["corpus"]["document_count"] == 3
    assert first["embedding"] == {
        "model_id": FixtureEmbedding.model_id,
        "revision": FixtureEmbedding.model_revision,
        "dimension": 3,
    }
    assert first["metrics"]["hit_rate@3"] == 1.0
    assert first["metrics"]["hit_rate@5"] == 1.0
    assert first["threshold_evaluation"]["production_min_score"] is None
    assert [
        item["min_score"] for item in first["threshold_evaluation"]["candidate_results"]
    ] == [0.0, 0.8]


def test_empty_production_knowledge_base_is_blocked_without_metric_or_model_load(
    tmp_path: Path,
) -> None:
    production_root = REPOSITORY_ROOT / "datasets/rag"
    corpus_directory = production_root / "knowledge-base-v1"
    evaluation_directory = production_root / "evaluation-v1"

    def must_not_load_embedding() -> FixtureEmbedding:
        raise AssertionError("empty production corpus must not instantiate an embedder")

    result = run_evaluation(
        evaluation_directory,
        corpus_directory,
        embedding_factory=must_not_load_embedding,
    )
    output = tmp_path / "blocked-report.json"
    write_artifact(output, result)
    serialized = json.loads(output.read_text(encoding="utf-8"))

    assert result["status"] == "blocked"
    assert result["reason"] == "production_knowledge_base_empty"
    assert result["corpus"]["document_count"] == 0
    assert result["metrics"] is None
    assert result["threshold_evaluation"]["production_min_score"] is None
    assert result["threshold_evaluation"]["candidate_results"] == []
    assert serialized == result
