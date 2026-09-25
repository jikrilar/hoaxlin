# Hoaxlin local RAG retrieval service

This service reads only `../datasets/rag/knowledge-base-v1.1.1/` by default. It validates the
R2 manifest and document schema before building an in-memory index. It does
not classify claims, change IndoBERT results, fetch evidence from the web, or
generate text. Evidence metadata and snippets come directly from the validated
documents.

## Model and index

The CPU embedding backend uses the pretrained
[`sentence-transformers/paraphrase-multilingual-MiniLM-L12-v2`](https://huggingface.co/sentence-transformers/paraphrase-multilingual-MiniLM-L12-v2)
model at the revision pinned in `app/config.py`. The model covers Indonesian
and English and produces 384-dimensional embeddings. There is no training or
fine-tuning. Model files are downloaded only by the explicit preparation
command below; service startup uses the local cache and retrieval requests
never fetch them.

The replaceable `EmbeddingBackend` and `CosineIndex` are independent of FastAPI.
The index splits each document into overlapping 90-word chunks, embeds its
title with each chunk, L2-normalizes vectors, then ranks by cosine similarity.
It keeps only the best chunk per document, so a document appears at most once.
Ties are resolved by document ID and chunk position. The index is rebuilt from
the pinned model and validated knowledge base at startup; no FAISS or persisted
vector artifact is needed at this scale.

## Run locally (Python 3.10+)

From `rag-service/`:

```powershell
python -m venv .venv
.\.venv\Scripts\python.exe -m pip install -r requirements.txt
.\.venv\Scripts\python.exe -m app.prepare_model
.\.venv\Scripts\python.exe -m uvicorn app.main:app --host 127.0.0.1 --port 8002
```

The model preparation step requires internet access once. Runtime startup and
`/retrieve` use cached files only. `requirements-dev.txt` adds only the test
client and test runner; all dependencies remain local to this service.

## Docker image

From the repository root, `docker compose build rag` builds the internal-only
service image. The build installs `requirements.txt` and explicitly runs
`python -m app.prepare_model`, downloading the model at the pinned revision
into `/opt/huggingface`. This build step requires network access; the image
contains the prepared cache, and its runtime sets `HF_HUB_OFFLINE=1` and
`TRANSFORMERS_OFFLINE=1`. The embedding backend also uses
`local_files_only=True`, so service startup and requests do not download model
files.

The image runs as a non-root user and listens on port 8002 inside the
`hoaxlin` network without publishing a host port. Its build context allowlist
contains only the RAG service source and requirements, the knowledge-base
`documents.jsonl` and `manifest.json`, and the validator imported by the
service. The image places the corpus at `/app/datasets/rag/knowledge-base-v1.1.1/`,
which is the path resolved by `app.config.KNOWLEDGE_BASE_DIRECTORY`. The
container healthcheck uses `/health/live`; an empty corpus can therefore keep
the process healthy while `/health/ready` reports `knowledge_base_empty`.

The active v1.1.1 corpus snapshot has **61 documents**: 24 records preserved
from the previously reviewed v1.0.0 snapshot and 37 additions that passed
automated source/content checks. These checks are not human review or owner
approval; the final snapshot report lists 21 excluded and 16 unresolved
candidates. Candidate v1.1.0 (98 documents) remains available for audit. The
retained v1.0.0 snapshot contains 24 documents and remains the corpus used by
the approved R11 evaluation. The local
service needs the pinned model cache prepared before it can build vectors; it
does not download model files on request. A genuinely empty corpus remains a
supported state: liveness stays healthy, readiness reports
`knowledge_base_empty`, version metadata reports zero documents and no vector
dimension, and valid retrieval requests return `{"results": []}`. Empty-corpus
behavior is tested using a temporary fixture, not by replacing production
documents. If a populated knowledge base or index is invalid, readiness is 503
and retrieval is 503 with a generic public error. Internal exception details
stay in server logs.

## API

| Endpoint | Response |
| --- | --- |
| `GET /health/live` | `{"status":"live"}` |
| `GET /health/ready` | `{"status":"ready","reason":null}` (200), or `{"status":"not_ready","reason":"..."}` (503) |
| `GET /version` | `service_version`, `embedding_model_id`, `embedding_model_revision`, `knowledge_base_version`, `document_count`, `embedding_dimension`, `vector_count` |
| `POST /retrieve` | `{"results":[...]}` or 503 when retrieval is unavailable |

`/retrieve` accepts JSON with `text` (1–4000 characters after trimming),
`top_k` (integer 1–10, default 3), and optional `min_score` (cosine value from
-1 to 1). No minimum score is applied by default. A production threshold
requires separate retrieval evaluation. Unknown request fields are rejected;
there is no request parameter for a model, URL, or filesystem path.

Each result contains `document_id`, `title`, `source`, `source_url`,
`published_at` (date or null), `snippet`, `score` (cosine similarity), and
`rank` (starting at 1). Similarity describes relevance, not factual truth.
An empty `results` array means no document met the requested filter or the
knowledge base is empty.

## Test

```powershell
.\.venv\Scripts\python.exe -m pip install -r requirements-dev.txt
.\.venv\Scripts\python.exe -m pytest tests
python ..\scripts\validate_rag_knowledge_base.py
python -m unittest discover -s ..\tests\rag -p 'test_*.py'
```

Tests use synthetic fixtures in temporary directories and a deterministic
test embedding backend. No test record is added to the production corpus.

## Retrieval evaluation

The versioned retrieval evaluation dataset, metric definitions, corpus binding,
and current production status are documented in
[`datasets/rag/README.md`](../datasets/rag/README.md). Run the evaluator from
the repository root with:

```powershell
.\rag-service\.venv\Scripts\python.exe scripts\evaluate_rag_retrieval.py
```

The artifact is `reports/rag-retrieval-evaluation-v1.json`. Its approved run is
bound to the approved v1.0.0 evaluation snapshot (20 queries) and the
knowledge-base v1.0.0 corpus (24 documents) by manifest checksums. It reports
Hit Rate@3/@5 1.0, Precision@3 0.4, Precision@5 0.25, Recall@3 0.975,
Recall@5 1.0, and MRR 0.95. Threshold candidates are exploratory: 0.4 retains
baseline hit rate and recall with Precision@5 0.334167; 0.5 raises
Precision@5 to 0.635833 while Hit Rate@3/@5 falls to 0.95; 0.6 raises
Precision@5 to 0.8 with coverage and Recall@5 at 0.95. This 20-query set is
not sufficient to set a robust production threshold, so `RAG_MIN_SCORE`
remains unset. Synthetic corpora are used only in automated tests; they are
not presented as production retrieval evaluation. These metrics remain tied to
v1.0.0 and do not measure the expanded v1.1.1 runtime corpus. The expansion task
does not set `RAG_MIN_SCORE` or claim a retrieval-quality improvement.
