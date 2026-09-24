# Hoaxlin local RAG retrieval service

This service reads only `../datasets/rag/knowledge-base-v1/`. It validates the
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

The production knowledge base currently has **0 documents**. The service
starts, `/health/live` returns 200, `/health/ready` returns 503 with
`knowledge_base_empty`, `/version` reports 0 documents and a null embedding
dimension, and valid `/retrieve` requests return `{"results": []}`. It does
not load the model until real documents exist. If a populated knowledge base
or index is invalid, readiness is 503 and retrieval is 503 with a generic
public error. Internal exception details stay in server logs.

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
