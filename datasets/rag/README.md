# Hoaxlin RAG knowledge base

`knowledge-base-v1.1.1/documents.jsonl` is the current runtime corpus selected
by `rag-service/app/config.py`. It contains 61 documents: 24 records preserved
from the previously reviewed v1.0.0 snapshot and 37 of the 74 new v1.1.0
candidates that passed deterministic automated source/content checks. This is
**not human review or owner approval**. The [v1.1.1 review report](knowledge-base-v1.1.1/review-report.md)
records 21 excluded and 16 unresolved candidates, source checks, limitations,
and duplicate-risk notes. Candidate snapshot v1.1.0 remains available
unchanged. Content is paraphrased from linked publications, not copied as
full-page HTML. Training data in `datasets/processed/`, classifier splits,
and evaluation data in `datasets/challenge/` must never be used as
knowledge-base input.

## Document contract (schema 1.0.0)

Each line is exactly one UTF-8 JSON object with these seven fields. Blank lines
are invalid:

| Field | Type | Rule |
| --- | --- | --- |
| `id` | string | Stable, unique, nonempty identifier |
| `title` | string | Nonempty original document title |
| `content` | string | Nonempty text checked against the original publication |
| `source` | string | Nonempty publisher name |
| `source_url` | string | Original HTTP/HTTPS publication URL |
| `published_at` | string or null | Real calendar date in `YYYY-MM-DD` form, or `null` |
| `topic` | string or null | Nonempty topic when known, or `null` |

The URL and publisher identify the provenance of each document. Do not put a
claim extracted for classifier training, an evaluation item, or LLM-generated
text in `content`. The automated v1.1.1 checks fetch source pages and compare
publisher domain, title, visible content, dates, entities, numbers, and topic
cues. These checks are conservative screening, not semantic entailment or
human approval; passing them cannot prove a claim true.

## Manifest and validation

Each snapshot's `manifest.json` records the knowledge-base version, document schema version,
exact document count, and SHA-256 of the **raw bytes** of `documents.jsonl`.
Its `provenance.local_input_files` must stay empty: this corpus accepts only
original publication URLs, not local dataset files. The
validator rejects additional manifest fields and document fields; local files
cannot be declared as sanctioned inputs in this contract.

To add reviewed documents, write one object per line, update `document_count`
and `documents.sha256`, then run:

```powershell
python scripts/validate_rag_knowledge_base.py
python -m unittest discover -s tests/rag -p 'test_*.py'
```

The validator defaults to the v1.1.1 runtime snapshot and accepts explicit
snapshot paths, including the retained v1.0.0 corpus. It checks UTF-8 JSONL,
the document contract, unique IDs, normalized exact duplicates (Unicode NFKC,
whitespace collapse, casefold), manifest count and checksum, and the no-local-
input boundary. It does not fetch URLs or certify claims. V1.0.0 contains 24
previously reviewed documents. Candidate v1.1.0 contains 98 documents (24
legacy plus 74 additions). Snapshot v1.1.1 contains those 24 legacy records
and 37 additions that passed automated checks; 21 candidates were excluded
and 16 remain review-required. The 37 automated passes have not received
human approval. The v1.1.1 report describes the checks and their limits.

## Retrieval evaluation (schema 1.0.0)

`evaluation-v1/` is a separate, versioned retrieval relevance set. Each line
in `queries.jsonl` has a unique `id`, a nonempty `query`, and nonempty
`relevant_document_ids` that must exist in the exact corpus snapshot recorded
in `manifest.json`. It has no classifier labels. The manifest pins the corpus
version and `documents.jsonl` checksum as well as the query file checksum.

The approved evaluation snapshot contains 20 query records against the
24-document v1.0.0 corpus snapshot. Owner approval and its exact version/checksum
scope are recorded in
[`evaluation-v1/relevance-review.md`](evaluation-v1/relevance-review.md).
The R11 report at `reports/rag-retrieval-evaluation-v1.json` remains bound to
these v1.0.0 snapshots; it does not evaluate v1.1.0 or v1.1.1. It reports Hit Rate@3/@5
1.0, Precision@3 0.4, Precision@5
0.25, Recall@3 0.975, Recall@5 1.0, and MRR 0.95. Threshold candidates are
exploratory only; `RAG_MIN_SCORE` remains unset because 20 queries are not a
robust basis for selecting a production threshold. Synthetic documents and
queries exist only inside temporary automated test fixtures and are never
used as production corpus or evaluation records.

The evaluator supports document-level Hit Rate@3, Hit Rate@5, Precision@3,
Precision@5, Recall@3, Recall@5, and MRR. It uses the same local cosine index
and pinned embedding revision as the service. Results are deterministic for
the same query set, corpus snapshot, model revision, and code. The final
production artifact records the metrics for the approved snapshot. Its
exploratory threshold candidates show increasing precision with reduced hit
rate or coverage at higher scores; no production threshold is selected from
this 20-query set. Metric values from synthetic test fixtures are only
calculator/runner tests, not production results.

To reproduce the approved v1.0.0 evaluation from the repository root, run:

```powershell
.\rag-service\.venv\Scripts\python.exe scripts\evaluate_rag_retrieval.py
```

The deterministic report is written to
`reports/rag-retrieval-evaluation-v1.json`. The evaluator uses the same local
cosine index and pinned embedding model as `rag-service`; it never calls a
production HTTP endpoint or uses an LLM judge. Threshold sweep values are
exploratory candidates only. `production_min_score` remains unset and must
not be inferred automatically from a small or unrepresentative evaluation set.

The default R11 evaluator deliberately continues to use
`knowledge-base-v1/` (v1.0.0), because `evaluation-v1/` relevance judgments
and the report are checksum-bound to that 24-document snapshot. Do not interpret
those metrics as results for the expanded runtime snapshot. A new evaluation
snapshot and reviewed relevance judgments are required before evaluating
v1.1.1. No retrieval threshold is set by the expansion task.
