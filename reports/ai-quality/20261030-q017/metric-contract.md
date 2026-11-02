# Q-017 — Graded relevance/no-answer evaluator v2

Status DONE_TOOLING_ONLY. No Q018 benchmark/judgment or Q019 baseline run.
Legacy evaluator schema1.0.0 remains supported unchanged in meaning. No existing
queries, corpus, manifest, frozen model or historical reports modified.

## Schema2.0.0

Manifest fields/path/checksums/corpus identity checks remain the same as v1; set
version, schema_version and evaluation_script_version to2.0.0 on a NEW dataset.
Query fields exactly: id, query, answerable (bool), judgments (object document ID
to integer0..3), judgments_complete (bool).

Grades:0 unrelated;1 same topic;2 directly related claim/event;3 direct evidence
answering/refuting claim. Relevant binary cutoff>=2; direct evidence exactly3.
Missing document key is UNJUDGED, not0; null/bool grades rejected. Answerable
requires at least one judged grade>=2. No-answer must explicitly answerable=false
and no positive grades. judgments_complete=true requires judgments for EVERY
document in the referenced corpus. False no-answer with partial scope remains
preliminary, not proof that no corpus evidence exists. Human review status is not
inferred from a flag: Q018 must provide actual review provenance separately.

Example structure (synthetic only, not a dataset to ingest):
`{"id":"fixture","query":"claim","answerable":true,"judgments":{"doc-a":3},"judgments_complete":false}`.

## Metrics and denominator policy

Stable ranking order; IDs deduplicated at later ranks BEFORE K cutoff. Ties use
retriever's stable supplied order; no random tie breaking or score-based resort.
No confidence interpretation of cosine score or relevance grade.

- returned_precision@K: #grade>=2 / unique returned up toK, empty=0; macro all queries.
- fixed_precision@K: #grade>=2 / K, empty=0; distinct name, never legacy precision.
- direct_evidence_precision@K: #grade3 / returned count, empty=0; macro all queries.
- ndcg@K: gains2^grade−1, discountlog2(rank+1), ideal descending known corpus grades;
  macro ANSWERABLE only, empty=0. No-answer excluded (no positive ideal ranking).
- answerable_recall@K: hits / total corpus grade>=2, answerable only.
- answerable_hit@K: any grade>=2, answerable only.
- answerable_empty_rate@K: empty/answerable queries; always empty thus fails this
  and recall/hit even if no-answer rejection looks good.
- no_answer_false_reference_rate@K: any returned / no-answer queries. A decision
  metric, not claim every returned unjudged document has grade0.
- unjudged_rate@K: unjudged / returned, empty=0; macro all queries.

If returned items include unjudged IDs, precision variants for that query are
null, not pessimistic0; aggregate null if ANY contributing query undefined.
Recall/hit/nDCG require complete judgment scope, otherwise null. Missing subgroup
denominator produces null, not perfect score. Reports preliminary whenever any
query scope incomplete or returned unjudged. Per-query IDs/grades preserve None.
No single combined score selecting an always-empty system.

Legacy v1 preserves historical precision@K denominator=unique actually returned
and relevant_document_ids assumptions, plus hit/recall/MRR. Reports label this
legacy contract; no grade fabrication/migration from binary IDs. v2 threshold
candidate reports carry judgment_status; no production threshold selected.
Existing CLI exploratory threshold defaults retained for compatibility; use
threshold_candidates=() for tooling smoke, do not tune on legacy20 queries.

## Validation/limits

Full RAG suite:44 passed,1 dependency deprecation warning,1.62s. Covers legacy
regression, real v2 loader/runner with fixture embeddings, empty/no-answer,
wrong/fewer-thanK results, dedup, direct grades, nDCG ordering, missing judgments,
invalid IDs/grades/flags and full scope validation. No embedding download/model
inference or real semantic evaluation; no accuracy/relevance improvement claim.
Synthetic tests not human approvals. Q018/19 remain unstarted.
