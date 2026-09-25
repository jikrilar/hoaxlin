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

The approved evaluation snapshot contains 20 query records and is bound by
manifest checksum to the 24-document v1.0.0 corpus. Owner approval and its exact
version/checksum scope are recorded in
[`evaluation-v1/relevance-review.md`](evaluation-v1/relevance-review.md).
The original R11 report, `reports/rag-retrieval-evaluation-v1.json`, remains
unchanged and reports Hit Rate@3/@5 1.0, Precision@3 0.4, Precision@5 0.25,
Recall@3 0.975, Recall@5 1.0, and MRR 0.95 for those v1.0.0 snapshots.

R14 adds `reports/rag-retrieval-evaluation-v1.1.1.json`: the same 20 query
bytes and relevance judgments were evaluated with the v1.1.1 corpus (61
records), using the same local cosine index and pinned embedding revision as
`rag-service`. It reports Hit Rate@3/@5 1.0, Precision@3 0.383333,
Precision@5 0.25, Recall@3 0.958333, Recall@5 1.0, and MRR 0.95. The owner
approval remains scoped to v1.0.0; relevance of newly added documents was not
independently judged. Unlisted documents are treated as non-relevant by the
metric calculator, so precision on the expanded corpus is subject to incomplete
judgments. This is a limited evaluation set, not a universal estimate. Metric
differences are descriptive across different corpus snapshots, not proof of
absolute quality improvement or decline.

The v1.1.1 report includes an exploratory score sweep: at min_score 0.4,
coverage is 100% and Precision@5 is 0.279167; at 0.5, coverage remains 100%,
Precision@5 is 0.490833 and Hit Rate@5/Recall@5 are 0.95; at 0.6, coverage is
95%, Precision@5 is 0.731667 and Recall@5 is 0.925. These candidates do not
establish a production threshold. `RAG_MIN_SCORE` remains unset. The original
evaluation manifest and relevance judgments were not changed; the v1.1.1 run
used a temporary corpus binding while preserving query bytes. Synthetic
fixtures remain limited to automated tests.
