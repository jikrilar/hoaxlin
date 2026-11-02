"""Pure, deterministic metrics for document-level retrieval evaluation."""

from __future__ import annotations

from collections.abc import Mapping, Sequence
from dataclasses import dataclass
import math


class MetricInputError(ValueError):
    """Evaluation cases or rankings violate the metric contract."""


@dataclass(frozen=True)
class RetrievalCase:
    id: str
    query: str
    relevant_document_ids: tuple[str, ...]


@dataclass(frozen=True)
class GradedCase:
    id: str
    query: str
    answerable: bool
    judgments: Mapping[str, int]
    judgments_complete: bool


def calculate_graded_metrics(cases, rankings, ks=(3, 5)):
    """v2: grade>=2 relevant, grade3 direct. Unknown is never coerced to zero.

    Precision/nDCG macros are null if any contributing query lacks judgments.
    Recall uses answerable cases only and requires complete judgment scope.
    No-answer false-reference is a decision metric (any nonempty return).
    """
    if not cases: raise MetricInputError('evaluation_queries_empty')
    if not ks or any(type(k) is not int or k < 1 for k in ks) or len(set(ks)) != len(ks):
        raise MetricInputError('invalid_metric_k')
    ids = [c.id for c in cases]
    if len(set(ids)) != len(ids) or set(ids) != set(rankings): raise MetricInputError('retrieval_query_ids_mismatch')
    for c in cases:
        if not isinstance(c.id, str) or not c.id.strip() or not isinstance(c.query, str) or not c.query.strip(): raise MetricInputError('invalid_query')
        if type(c.answerable) is not bool or type(c.judgments_complete) is not bool: raise MetricInputError('explicit_answerability_required')
        if not isinstance(c.judgments, Mapping) or any(not isinstance(d, str) or not d.strip() or type(g) is not int or g not in range(4) for d,g in c.judgments.items()): raise MetricInputError('invalid_grade')
        relevant = [g for g in c.judgments.values() if g >= 2]
        if c.answerable != bool(relevant): raise MetricInputError('answerability_judgments_mismatch')
        if isinstance(rankings[c.id], (str, bytes)) or any(not isinstance(d,str) or not d.strip() for d in rankings[c.id]): raise MetricInputError('invalid_ranking')
    metrics, details, preliminary = {}, {c.id: {} for c in cases}, False
    def mean(values):
        return None if not values or any(v is None for v in values) else round(sum(values)/len(values), 6)
    for k in ks:
        values = {name: [] for name in ('returned_precision','fixed_precision','direct_evidence_precision','ndcg','answerable_recall','answerable_hit','answerable_empty_rate','no_answer_false_reference_rate','unjudged_rate')}
        for c in sorted(cases, key=lambda c:c.id):
            top = list(dict.fromkeys(rankings[c.id]))[:k]
            grades = [c.judgments.get(d) for d in top]
            unknown = sum(g is None for g in grades)
            complete = c.judgments_complete and not unknown
            preliminary |= not complete
            found = sum(g is not None and g >= 2 for g in grades)
            direct = sum(g == 3 for g in grades)
            local = {'document_ids': top, 'grades': grades}
            local['returned_precision'] = found/len(top) if top else 0.0
            local['fixed_precision'] = found/k
            local['direct_evidence_precision'] = direct/len(top) if top else 0.0
            if unknown:
                for name in ('returned_precision','fixed_precision','direct_evidence_precision'): local[name] = None
            ideal = sorted(c.judgments.values(), reverse=True)[:k]
            dcg = lambda gs: sum((2**g-1)/math.log2(i+2) for i,g in enumerate(gs))
            local['ndcg'] = (dcg(grades)/dcg(ideal) if dcg(ideal) else 0.0) if complete else None
            for name in ('returned_precision','fixed_precision','direct_evidence_precision'): values[name].append(local[name])
            if c.answerable: values['ndcg'].append(local['ndcg'])
            values['unjudged_rate'].append(unknown/len(top) if top else 0.0)
            if c.answerable:
                values['answerable_recall'].append(found/sum(g>=2 for g in c.judgments.values()) if complete else None)
                values['answerable_hit'].append(int(found>0) if complete else None)
                values['answerable_empty_rate'].append(int(not top))
            else: values['no_answer_false_reference_rate'].append(int(bool(top)))
            details[c.id][f'@{k}'] = local
        for name, vals in values.items(): metrics[f'{name}@{k}'] = mean(vals)
    return {'schema_version': '2.0.0', 'status': 'preliminary' if preliminary else 'complete',
            'query_count':len(cases), 'answerable_count':sum(c.answerable for c in cases),
            'no_answer_count':sum(not c.answerable for c in cases), 'metrics':metrics, 'per_query':details}


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
