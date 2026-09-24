"""Pure, deterministic metrics for document-level retrieval evaluation."""

from __future__ import annotations

from collections.abc import Mapping, Sequence
from dataclasses import dataclass


class MetricInputError(ValueError):
    """Evaluation cases or rankings violate the metric contract."""


@dataclass(frozen=True)
class RetrievalCase:
    id: str
    query: str
    relevant_document_ids: tuple[str, ...]


def calculate_metrics(
    cases: Sequence[RetrievalCase],
    retrieved_document_ids: Mapping[str, Sequence[str]],
    ks: Sequence[int] = (3, 5),
) -> dict[str, object]:
    """Calculate macro document-level metrics; empty rankings contribute zero.

    Duplicate retrieved IDs are discarded at their later ranks. Precision uses
    the number of unique results actually returned at each K as its denominator.
    """
    if not cases:
        raise MetricInputError("evaluation_queries_empty")

    normalized_ks = tuple(ks)
    if not normalized_ks or any(
        isinstance(k, bool) or not isinstance(k, int) or k < 1 for k in normalized_ks
    ):
        raise MetricInputError("invalid_metric_k")
    if len(set(normalized_ks)) != len(normalized_ks):
        raise MetricInputError("duplicate_metric_k")

    case_ids: set[str] = set()
    cases_by_id: list[tuple[str, set[str]]] = []
    for case in cases:
        if not isinstance(case.id, str) or not case.id.strip() or case.id in case_ids:
            raise MetricInputError("invalid_or_duplicate_query_id")
        if not isinstance(case.query, str) or not case.query.strip():
            raise MetricInputError(f"empty_query:{case.id}")
        if not case.relevant_document_ids:
            raise MetricInputError(f"empty_relevant_document_ids:{case.id}")
        if any(
            not isinstance(document_id, str) or not document_id.strip()
            for document_id in case.relevant_document_ids
        ):
            raise MetricInputError(f"invalid_relevant_document_id:{case.id}")
        relevant = set(case.relevant_document_ids)
        if len(relevant) != len(case.relevant_document_ids):
            raise MetricInputError(f"duplicate_relevant_document_id:{case.id}")
        case_ids.add(case.id)
        cases_by_id.append((case.id, relevant))

    if set(retrieved_document_ids) != case_ids:
        raise MetricInputError("retrieval_query_ids_mismatch")

    # Sorting query IDs makes aggregate floating point operations independent
    # of the order in which a caller assembled its input mapping or sequence.
    cases_by_id.sort(key=lambda item: item[0])
    unique_rankings: dict[str, list[str]] = {}
    for query_id, _relevant in cases_by_id:
        ranking = retrieved_document_ids[query_id]
        if isinstance(ranking, (str, bytes)):
            raise MetricInputError(f"invalid_retrieved_document_ids:{query_id}")
        try:
            values = list(ranking)
        except TypeError as exc:
            raise MetricInputError(
                f"invalid_retrieved_document_ids:{query_id}"
            ) from exc
        if any(not isinstance(value, str) or not value.strip() for value in values):
            raise MetricInputError(f"invalid_retrieved_document_id:{query_id}")
        unique_rankings[query_id] = list(dict.fromkeys(values))

    query_count = len(cases_by_id)
    query_hits: dict[str, int] = {}
    metrics: dict[str, float] = {}
    for k in normalized_ks:
        hit_count = 0
        precision_values: list[float] = []
        recall_values: list[float] = []

        for query_id, relevant in cases_by_id:
            top_results = unique_rankings[query_id][:k]
            found = relevant.intersection(top_results)
            hit_count += int(bool(found))
            precision_values.append(len(found) / len(top_results) if top_results else 0.0)
            recall_values.append(len(found) / len(relevant))

        query_hits[f"@{k}"] = hit_count
        metrics[f"hit_rate@{k}"] = round(hit_count / query_count, 6)
        metrics[f"precision@{k}"] = round(sum(precision_values) / query_count, 6)
        metrics[f"recall@{k}"] = round(sum(recall_values) / query_count, 6)

    reciprocal_ranks: list[float] = []
    for query_id, relevant in cases_by_id:
        first_rank = next(
            (
                rank
                for rank, document_id in enumerate(unique_rankings[query_id], start=1)
                if document_id in relevant
            ),
            None,
        )
        reciprocal_ranks.append(1.0 / first_rank if first_rank is not None else 0.0)

    metrics["mrr"] = round(sum(reciprocal_ranks) / query_count, 6)
    return {
        "query_count": query_count,
        "query_hits": query_hits,
        "metrics": metrics,
    }
