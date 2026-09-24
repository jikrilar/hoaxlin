"""Run versioned RAG retrieval evaluation against a local validated corpus."""

from __future__ import annotations

import argparse
from collections.abc import Callable, Mapping
from dataclasses import dataclass
import hashlib
import json
from pathlib import Path
import re
import sys
from typing import Any


REPOSITORY_ROOT = Path(__file__).resolve().parents[1]
RAG_SERVICE_ROOT = REPOSITORY_ROOT / "rag-service"
if str(RAG_SERVICE_ROOT) not in sys.path:
    sys.path.insert(0, str(RAG_SERVICE_ROOT))

from app.config import MODEL_ID, MODEL_REVISION  # noqa: E402
from app.embedding import EmbeddingBackend, SentenceTransformerEmbedding  # noqa: E402
from app.index import CosineIndex  # noqa: E402
from app.knowledge_base import KnowledgeBase, KnowledgeBaseError, load_knowledge_base  # noqa: E402
from scripts.rag_retrieval_metrics import (  # noqa: E402
    MetricInputError,
    RetrievalCase,
    calculate_metrics,
)


EVALUATION_DATASET_NAME = "hoaxlin-rag-retrieval-evaluation"
EVALUATION_VERSION = "1.0.0"
EVALUATION_SCHEMA_VERSION = "1.0.0"
EVALUATION_SCRIPT_VERSION = "1.0.0"
DEFAULT_EVALUATION_DIRECTORY = REPOSITORY_ROOT / "datasets/rag/evaluation-v1"
DEFAULT_CORPUS_DIRECTORY = REPOSITORY_ROOT / "datasets/rag/knowledge-base-v1"
DEFAULT_OUTPUT_PATH = REPOSITORY_ROOT / "reports/rag-retrieval-evaluation-v1.json"
DEFAULT_THRESHOLD_CANDIDATES = tuple(round(step / 10, 1) for step in range(10))
SHA256_PATTERN = re.compile(r"[0-9a-f]{64}\Z")
MANIFEST_FIELDS = frozenset(
    {
        "dataset_name",
        "version",
        "schema_version",
        "query_count",
        "queries",
        "corpus",
        "evaluation_script_version",
    }
)


class EvaluationError(ValueError):
    """Evaluation dataset, corpus reference, or run contract is invalid."""


@dataclass(frozen=True)
class EvaluationDataset:
    version: str
    schema_version: str
    query_count: int
    queries_sha256: str
    corpus_reference: str
    corpus_version: str
    corpus_documents_sha256: str
    queries: tuple[RetrievalCase, ...]


def _unique_object(pairs: list[tuple[str, object]]) -> dict[str, object]:
    result: dict[str, object] = {}
    for key, value in pairs:
        if key in result:
            raise EvaluationError(f"duplicate_json_key:{key}")
        result[key] = value
    return result


def _reject_constant(value: str) -> None:
    raise EvaluationError(f"invalid_json_constant:{value}")


def _parse_json(raw: str, location: str) -> object:
    try:
        return json.loads(
            raw,
            object_pairs_hook=_unique_object,
            parse_constant=_reject_constant,
        )
    except (json.JSONDecodeError, EvaluationError) as exc:
        raise EvaluationError(f"{location}:invalid_json") from exc


def _exact_fields(value: object, required: frozenset[str], location: str) -> dict[str, Any]:
    if not isinstance(value, dict):
        raise EvaluationError(f"{location}:expected_object")
    missing = required - value.keys()
    extra = value.keys() - required
    if missing or extra:
        raise EvaluationError(
            f"{location}:fields_mismatch:missing={sorted(missing)}:extra={sorted(extra)}"
        )
    return value


def _check_sha256(value: object, location: str) -> str:
    if not isinstance(value, str) or not SHA256_PATTERN.fullmatch(value):
        raise EvaluationError(f"{location}:invalid_sha256")
    return value


def _load_queries(
    query_bytes: bytes,
    known_document_ids: set[str],
) -> tuple[RetrievalCase, ...]:
    try:
        text = query_bytes.decode("utf-8")
    except UnicodeError as exc:
        raise EvaluationError("queries.jsonl:invalid_utf8") from exc

    queries: list[RetrievalCase] = []
    seen_query_ids: set[str] = set()
    for line_number, line in enumerate(text.splitlines(), start=1):
        location = f"queries.jsonl:line_{line_number}"
        if not line.strip():
            raise EvaluationError(f"{location}:blank_line")
        raw_record = _parse_json(line, location)
        if not isinstance(raw_record, dict):
            raise EvaluationError(f"{location}:expected_object")
        allowed = frozenset({"id", "query", "relevant_document_ids", "topic"})
        required = frozenset({"id", "query", "relevant_document_ids"})
        missing = required - raw_record.keys()
        extra = raw_record.keys() - allowed
        if missing or extra:
            raise EvaluationError(
                f"{location}:fields_mismatch:missing={sorted(missing)}:extra={sorted(extra)}"
            )

        query_id = raw_record["id"]
        query_text = raw_record["query"]
        relevant_ids = raw_record["relevant_document_ids"]
        if not isinstance(query_id, str) or not query_id.strip():
            raise EvaluationError(f"{location}:id_must_be_nonempty_string")
        if query_id in seen_query_ids:
            raise EvaluationError(f"{location}:duplicate_id")
        if not isinstance(query_text, str) or not query_text.strip():
            raise EvaluationError(f"{location}:query_must_be_nonempty_string")
        if not isinstance(relevant_ids, list) or not relevant_ids:
            raise EvaluationError(f"{location}:relevant_document_ids_must_be_nonempty")
        if any(not isinstance(document_id, str) or not document_id.strip() for document_id in relevant_ids):
            raise EvaluationError(f"{location}:invalid_relevant_document_id")
        if len(set(relevant_ids)) != len(relevant_ids):
            raise EvaluationError(f"{location}:duplicate_relevant_document_id")

        unknown_ids = sorted(set(relevant_ids) - known_document_ids)
        if unknown_ids:
            raise EvaluationError(f"{location}:unknown_relevant_document_ids:{unknown_ids}")

        if "topic" in raw_record:
            topic = raw_record["topic"]
            if topic is not None and (not isinstance(topic, str) or not topic.strip()):
                raise EvaluationError(f"{location}:topic_must_be_nonempty_or_null")

        seen_query_ids.add(query_id)
        queries.append(
            RetrievalCase(
                id=query_id,
                query=query_text.strip(),
                relevant_document_ids=tuple(relevant_ids),
            )
        )
    return tuple(queries)


def load_evaluation_dataset(
    directory: Path,
    corpus_directory: Path,
    knowledge_base: KnowledgeBase,
) -> EvaluationDataset:
    """Validate evaluation records, checksum, corpus reference, and expected IDs."""
    directory = Path(directory)
    if directory.is_symlink():
        raise EvaluationError("evaluation_directory_must_not_be_symlink")
    manifest_path = directory / "manifest.json"
    query_path = directory / "queries.jsonl"
    for path in (manifest_path, query_path):
        if path.is_symlink():
            raise EvaluationError(f"{path.name}:symlink_not_allowed")
        if not path.is_file():
            raise EvaluationError(f"{path.name}:missing")

    try:
        manifest_text = manifest_path.read_text(encoding="utf-8")
        query_bytes = query_path.read_bytes()
    except (OSError, UnicodeError) as exc:
        raise EvaluationError("evaluation_dataset_unreadable") from exc

    manifest = _exact_fields(
        _parse_json(manifest_text, "manifest.json"), MANIFEST_FIELDS, "manifest.json"
    )
    if manifest["dataset_name"] != EVALUATION_DATASET_NAME:
        raise EvaluationError("manifest.json:unexpected_dataset_name")
    if manifest["version"] != EVALUATION_VERSION:
        raise EvaluationError("manifest.json:unsupported_version")
    if manifest["schema_version"] != EVALUATION_SCHEMA_VERSION:
        raise EvaluationError("manifest.json:unsupported_schema_version")
    if manifest["evaluation_script_version"] != EVALUATION_SCRIPT_VERSION:
        raise EvaluationError("manifest.json:unsupported_evaluation_script_version")
    if type(manifest["query_count"]) is not int or manifest["query_count"] < 0:
        raise EvaluationError("manifest.json:query_count_must_be_nonnegative_integer")

    query_metadata = _exact_fields(
        manifest["queries"], frozenset({"path", "sha256"}), "manifest.json:queries"
    )
    if query_metadata["path"] != "queries.jsonl":
        raise EvaluationError("manifest.json:queries_path_must_be_queries.jsonl")
    expected_queries_sha = _check_sha256(
        query_metadata["sha256"], "manifest.json:queries.sha256"
    )
    actual_queries_sha = hashlib.sha256(query_bytes).hexdigest()
    if expected_queries_sha != actual_queries_sha:
        raise EvaluationError("manifest.json:queries_checksum_mismatch")

    corpus_metadata = _exact_fields(
        manifest["corpus"],
        frozenset({"path", "version", "documents_sha256"}),
        "manifest.json:corpus",
    )
    corpus_reference = corpus_metadata["path"]
    if not isinstance(corpus_reference, str) or not corpus_reference.strip():
        raise EvaluationError("manifest.json:corpus.path_must_be_nonempty")
    relative_corpus_path = Path(corpus_reference)
    if relative_corpus_path.is_absolute():
        raise EvaluationError("manifest.json:corpus.path_must_be_relative")
    referenced_directory = (directory / relative_corpus_path).resolve()
    if referenced_directory != Path(corpus_directory).resolve():
        raise EvaluationError("manifest.json:corpus_reference_mismatch")
    if corpus_metadata["version"] != knowledge_base.version:
        raise EvaluationError("manifest.json:corpus_version_mismatch")
    expected_corpus_sha = _check_sha256(
        corpus_metadata["documents_sha256"], "manifest.json:corpus.documents_sha256"
    )
    if expected_corpus_sha != knowledge_base.sha256:
        raise EvaluationError("manifest.json:corpus_checksum_mismatch")

    queries = _load_queries(query_bytes, {document.id for document in knowledge_base.documents})
    if len(queries) != manifest["query_count"]:
        raise EvaluationError("manifest.json:query_count_mismatch")

    return EvaluationDataset(
        version=manifest["version"],
        schema_version=manifest["schema_version"],
        query_count=manifest["query_count"],
        queries_sha256=actual_queries_sha,
        corpus_reference=corpus_reference,
        corpus_version=knowledge_base.version,
        corpus_documents_sha256=knowledge_base.sha256,
        queries=queries,
    )


def _embedding_metadata(embedder: EmbeddingBackend | None) -> dict[str, object]:
    return {
        "model_id": getattr(embedder, "model_id", MODEL_ID),
        "revision": getattr(embedder, "model_revision", MODEL_REVISION),
        "dimension": getattr(embedder, "dimension", None),
    }


def _base_result(
    evaluation_dataset: EvaluationDataset,
    knowledge_base: KnowledgeBase,
    embedding: dict[str, object],
) -> dict[str, object]:
    return {
        "schema_version": EVALUATION_SCHEMA_VERSION,
        "evaluation_dataset": {
            "name": EVALUATION_DATASET_NAME,
            "version": evaluation_dataset.version,
            "schema_version": evaluation_dataset.schema_version,
            "script_version": EVALUATION_SCRIPT_VERSION,
            "query_count": evaluation_dataset.query_count,
            "queries_sha256": evaluation_dataset.queries_sha256,
        },
        "corpus": {
            "reference": evaluation_dataset.corpus_reference,
            "version": knowledge_base.version,
            "document_count": len(knowledge_base.documents),
            "documents_sha256": knowledge_base.sha256,
        },
        "embedding": embedding,
        "retrieval": {
            "algorithm": "cosine_similarity",
            "document_unit": "document_id",
            "top_k": [3, 5],
            "min_score": None,
        },
        "query_summary": {
            "query_count": evaluation_dataset.query_count,
            "valid_query_count": len(evaluation_dataset.queries),
        },
        "metrics": None,
        "threshold_evaluation": {
            "status": "not_evaluated",
            "production_min_score": None,
            "candidate_results": [],
            "reason": "threshold production belum ditetapkan karena evaluation corpus belum cukup representatif.",
        },
    }


def _validate_threshold_candidates(candidates: tuple[float, ...]) -> None:
    if len(set(candidates)) != len(candidates):
        raise EvaluationError("duplicate_threshold_candidate")
    if any(
        isinstance(value, bool)
        or not isinstance(value, (int, float))
        or not -1.0 <= value <= 1.0
        for value in candidates
    ):
        raise EvaluationError("invalid_threshold_candidate")


def run_evaluation(
    evaluation_directory: Path = DEFAULT_EVALUATION_DIRECTORY,
    corpus_directory: Path = DEFAULT_CORPUS_DIRECTORY,
    *,
    embedding_factory: Callable[[], EmbeddingBackend] = SentenceTransformerEmbedding,
    threshold_candidates: tuple[float, ...] = DEFAULT_THRESHOLD_CANDIDATES,
) -> dict[str, object]:
    """Run local retrieval and metrics; empty corpus is explicitly blocked."""
    try:
        knowledge_base = load_knowledge_base(Path(corpus_directory))
    except KnowledgeBaseError as exc:
        raise EvaluationError("knowledge_base_invalid") from exc

    dataset = load_evaluation_dataset(
        Path(evaluation_directory), Path(corpus_directory), knowledge_base
    )
    result = _base_result(dataset, knowledge_base, _embedding_metadata(None))

    if not knowledge_base.documents:
        result.update(
            {
                "status": "blocked",
                "reason": "production_knowledge_base_empty",
            }
        )
        return result
    if not dataset.queries:
        result.update(
            {
                "status": "blocked",
                "reason": "evaluation_dataset_empty",
            }
        )
        return result

    _validate_threshold_candidates(threshold_candidates)
    try:
        embedder = embedding_factory()
        index = CosineIndex(knowledge_base.documents, embedder)
    except Exception as exc:
        raise EvaluationError("retrieval_index_unavailable") from exc

    result["embedding"] = _embedding_metadata(embedder)
    baseline_rankings: dict[str, list[str]] = {}
    for case in dataset.queries:
        try:
            baseline_rankings[case.id] = [
                match.document.id for match in index.search(case.query, top_k=5)
            ]
        except Exception as exc:
            raise EvaluationError("retrieval_query_failed") from exc

    try:
        baseline_metrics = calculate_metrics(dataset.queries, baseline_rankings)
    except MetricInputError as exc:
        raise EvaluationError("metric_calculation_failed") from exc
    result["metrics"] = baseline_metrics["metrics"]
    result["query_summary"] = {
        "query_count": dataset.query_count,
        "valid_query_count": len(dataset.queries),
        "query_hits": baseline_metrics["query_hits"],
    }

    candidate_results: list[dict[str, object]] = []
    for threshold in threshold_candidates:
        candidate_rankings: dict[str, list[str]] = {}
        for case in dataset.queries:
            try:
                candidate_rankings[case.id] = [
                    match.document.id
                    for match in index.search(case.query, top_k=5, min_score=threshold)
                ]
            except Exception as exc:
                raise EvaluationError("threshold_retrieval_query_failed") from exc
        try:
            candidate_metrics = calculate_metrics(dataset.queries, candidate_rankings)
        except MetricInputError as exc:
            raise EvaluationError("threshold_metric_calculation_failed") from exc
        covered = sum(bool(ranking) for ranking in candidate_rankings.values())
        candidate_results.append(
            {
                "min_score": threshold,
                "coverage_count": covered,
                "coverage": round(covered / len(dataset.queries), 6),
                "metrics": candidate_metrics["metrics"],
            }
        )

    result["threshold_evaluation"] = {
        "status": "exploratory_candidates_not_selected",
        "production_min_score": None,
        "candidate_results": candidate_results,
        "reason": "threshold production belum ditetapkan karena evaluation corpus belum cukup representatif.",
    }
    result["status"] = "complete"
    return result


def write_artifact(path: Path, result: Mapping[str, object]) -> None:
    """Write stable JSON without volatile timestamps or environment paths."""
    destination = Path(path)
    destination.parent.mkdir(parents=True, exist_ok=True)
    serialized = json.dumps(result, ensure_ascii=False, indent=2, sort_keys=True) + "\n"
    destination.write_text(serialized, encoding="utf-8", newline="\n")


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--evaluation-directory", type=Path, default=DEFAULT_EVALUATION_DIRECTORY)
    parser.add_argument("--corpus-directory", type=Path, default=DEFAULT_CORPUS_DIRECTORY)
    parser.add_argument("--output", type=Path, default=DEFAULT_OUTPUT_PATH)
    args = parser.parse_args()

    try:
        result = run_evaluation(args.evaluation_directory, args.corpus_directory)
        write_artifact(args.output, result)
    except (EvaluationError, OSError) as exc:
        parser.exit(1, f"RAG retrieval evaluation failed: {exc}\n")

    print(json.dumps(result, ensure_ascii=False, indent=2, sort_keys=True))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
