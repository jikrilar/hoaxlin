"""HTTP boundary for local, versioned evidence retrieval."""

from __future__ import annotations

from contextlib import asynccontextmanager
from dataclasses import dataclass
from datetime import date
import logging
from pathlib import Path
from typing import Callable

from fastapi import FastAPI, HTTPException, Response
from fastapi.responses import JSONResponse
from pydantic import BaseModel, ConfigDict, Field, field_validator

from app.config import (
    KNOWLEDGE_BASE_DIRECTORY,
    MAX_QUERY_LENGTH,
    MAX_TOP_K,
    MODEL_ID,
    MODEL_REVISION,
    SERVICE_VERSION,
)
from app.embedding import EmbeddingBackend, SentenceTransformerEmbedding
from app.index import CosineIndex
from app.knowledge_base import KnowledgeBaseError, load_knowledge_base


logger = logging.getLogger(__name__)


class StrictModel(BaseModel):
    model_config = ConfigDict(extra="forbid")


class RetrieveRequest(StrictModel):
    text: str = Field(min_length=1, max_length=MAX_QUERY_LENGTH)
    top_k: int = Field(default=3, ge=1, le=MAX_TOP_K, strict=True)
    min_score: float | None = Field(default=None, ge=-1.0, le=1.0, allow_inf_nan=False)

    @field_validator("text", mode="before")
    @classmethod
    def trim_text(cls, value: object) -> object:
        return value.strip() if isinstance(value, str) else value


class EvidenceResult(StrictModel):
    document_id: str
    title: str
    source: str
    source_url: str
    published_at: date | None
    snippet: str
    score: float = Field(ge=-1.0, le=1.0, allow_inf_nan=False)
    rank: int = Field(ge=1)


class RetrieveResponse(StrictModel):
    results: list[EvidenceResult]


class LiveResponse(StrictModel):
    status: str


class ReadyResponse(StrictModel):
    status: str
    reason: str | None = None


class VersionResponse(StrictModel):
    service_version: str
    embedding_model_id: str
    embedding_model_revision: str
    knowledge_base_version: str | None
    document_count: int
    embedding_dimension: int | None
    vector_count: int


@dataclass
class RuntimeState:
    knowledge_base_version: str | None = None
    document_count: int = 0
    index: CosineIndex | None = None
    reason: str = "initializing"


def create_app(
    *,
    knowledge_base_directory: Path = KNOWLEDGE_BASE_DIRECTORY,
    embedding_factory: Callable[[], EmbeddingBackend] = SentenceTransformerEmbedding,
) -> FastAPI:
    """Inject paths/backends in tests; HTTP requests never accept either."""
    runtime = RuntimeState()

    @asynccontextmanager
    async def lifespan(_app: FastAPI):
        try:
            knowledge_base = load_knowledge_base(knowledge_base_directory)
            runtime.knowledge_base_version = knowledge_base.version
            runtime.document_count = len(knowledge_base.documents)
            if not knowledge_base.documents:
                runtime.reason = "knowledge_base_empty"
            else:
                try:
                    runtime.index = CosineIndex(knowledge_base.documents, embedding_factory())
                    runtime.reason = "ready"
                except Exception:
                    logger.exception("RAG embedding or index initialization failed")
                    runtime.reason = "index_unavailable"
        except KnowledgeBaseError:
            logger.exception("RAG knowledge base validation failed")
            runtime.reason = "knowledge_base_invalid"
        yield

    app = FastAPI(title="Hoaxlin RAG Retrieval", version=SERVICE_VERSION, lifespan=lifespan)

    @app.exception_handler(Exception)
    async def unexpected_error(_request, _exc: Exception):
        logger.exception("RAG request failed")
        return JSONResponse(status_code=500, content={"detail": "internal_error"})

    @app.get("/health/live", response_model=LiveResponse)
    def live() -> LiveResponse:
        return LiveResponse(status="live")

    @app.get("/health/ready", response_model=ReadyResponse)
    def ready(response: Response) -> ReadyResponse:
        if runtime.index is None:
            response.status_code = 503
            return ReadyResponse(status="not_ready", reason=runtime.reason)
        return ReadyResponse(status="ready")

    @app.get("/version", response_model=VersionResponse)
    def version() -> VersionResponse:
        return VersionResponse(
            service_version=SERVICE_VERSION,
            embedding_model_id=MODEL_ID,
            embedding_model_revision=MODEL_REVISION,
            knowledge_base_version=runtime.knowledge_base_version,
            document_count=runtime.document_count,
            embedding_dimension=runtime.index.dimension if runtime.index else None,
            vector_count=runtime.index.vector_count if runtime.index else 0,
        )

    @app.post("/retrieve", response_model=RetrieveResponse)
    def retrieve(request: RetrieveRequest) -> RetrieveResponse:
        if runtime.reason == "knowledge_base_empty":
            return RetrieveResponse(results=[])
        if runtime.index is None:
            raise HTTPException(status_code=503, detail="retrieval_unavailable")
        try:
            matches = runtime.index.search(request.text, request.top_k, request.min_score)
        except Exception:
            logger.exception("RAG retrieval failed")
            runtime.index = None
            runtime.reason = "index_unavailable"
            raise HTTPException(status_code=503, detail="retrieval_unavailable") from None
        return RetrieveResponse(
            results=[
                EvidenceResult(
                    document_id=match.document.id,
                    title=match.document.title,
                    source=match.document.source,
                    source_url=match.document.source_url,
                    published_at=match.document.published_at,
                    snippet=match.snippet,
                    score=match.score,
                    rank=rank,
                )
                for rank, match in enumerate(matches, start=1)
            ]
        )

    return app


app = create_app()
