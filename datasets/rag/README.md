# Hoaxlin RAG knowledge base

`knowledge-base-v1/documents.jsonl` is the versioned retrieval corpus. It is
currently empty: the repository has no separately curated, verified source
documents suitable for this corpus. Training data in `datasets/processed/` and
evaluation data in `datasets/challenge/` must never be used as input.

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
text in `content`. Human review must verify that the URL resolves to the named
source and supports the title and content; syntactic validation alone cannot
prove that.

## Manifest and validation

`manifest.json` records the knowledge-base version, document schema version,
exact document count, and SHA-256 of the **raw bytes** of `documents.jsonl`.
Its `provenance.local_input_files` must stay empty: this corpus accepts only
manually reviewed original publication URLs, not local dataset files. The
validator rejects additional manifest fields and document fields; local files
cannot be declared as sanctioned inputs in this contract.

To add reviewed documents, write one object per line, update `document_count`
and `documents.sha256`, then run:

```powershell
python scripts/validate_rag_knowledge_base.py
python -m unittest discover -s tests/rag -p 'test_*.py'
```

The validator checks UTF-8 JSONL, the document contract, unique IDs, normalized
exact duplicates (Unicode NFKC, whitespace collapse, casefold), manifest count
and checksum, and the no-local-input boundary. It does not fetch URLs or
certify claims. Document collection and editorial approval remain separate
work before retrieval can use this corpus.
