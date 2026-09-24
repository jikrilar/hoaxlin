"""Deterministic in-memory cosine index over document text chunks."""

from __future__ import annotations

from dataclasses import dataclass

import numpy as np

from app.embedding import EmbeddingBackend
from app.knowledge_base import Document


CHUNK_WORDS = 90
CHUNK_OVERLAP_WORDS = 15
SNIPPET_CHARACTERS = 400


class VectorIndexError(RuntimeError):
    """An embedding or index vector violates the retrieval contract."""


@dataclass(frozen=True)
class IndexedChunk:
    document: Document
    ordinal: int
    text: str


@dataclass(frozen=True)
class ScoredDocument:
    document: Document
    snippet: str
    score: float


def _document_chunks(document: Document) -> list[IndexedChunk]:
    words = document.content.split()
    chunks: list[IndexedChunk] = []
    step = CHUNK_WORDS - CHUNK_OVERLAP_WORDS
    for start in range(0, len(words), step):
        text = " ".join(words[start : start + CHUNK_WORDS])
        if text:
            chunks.append(IndexedChunk(document, len(chunks), text))
        if start + CHUNK_WORDS >= len(words):
            break
    return chunks


def _normalized_vectors(value: object, rows: int, dimension: int) -> np.ndarray:
    try:
        vectors = np.asarray(value, dtype=np.float32)
    except (TypeError, ValueError) as exc:
        raise VectorIndexError("malformed_vectors") from exc
    if vectors.shape != (rows, dimension) or not np.isfinite(vectors).all():
        raise VectorIndexError("malformed_vectors")
    lengths = np.linalg.norm(vectors, axis=1)
    if not np.isfinite(lengths).all() or np.any(lengths <= 0):
        raise VectorIndexError("malformed_vectors")
    return vectors / lengths[:, np.newaxis]


def _snippet(text: str) -> str:
    if len(text) <= SNIPPET_CHARACTERS:
        return text
    prefix = text[:SNIPPET_CHARACTERS].rsplit(" ", 1)[0]
    return (prefix or text[:SNIPPET_CHARACTERS]).rstrip() + "…"


class CosineIndex:
    def __init__(self, documents: tuple[Document, ...], embedder: EmbeddingBackend) -> None:
        if not isinstance(embedder.dimension, int) or embedder.dimension < 1:
            raise VectorIndexError("invalid_dimension")
        ids = [document.id for document in documents]
        if len(ids) != len(set(ids)):
            raise VectorIndexError("duplicate_document_id")
        self.dimension = embedder.dimension
        self._embedder = embedder
        self._chunks = tuple(
            chunk for document in documents for chunk in _document_chunks(document)
        )
        if not self._chunks:
            raise VectorIndexError("empty_index")
        texts = [f"{chunk.document.title}\n{chunk.text}" for chunk in self._chunks]
        self._matrix = _normalized_vectors(
            embedder.encode(texts), len(self._chunks), self.dimension
        )

    @property
    def vector_count(self) -> int:
        return len(self._chunks)

    def search(
        self, text: str, top_k: int, min_score: float | None = None
    ) -> list[ScoredDocument]:
        query = _normalized_vectors(self._embedder.encode([text]), 1, self.dimension)[0]
        similarities = self._matrix @ query
        best: dict[str, tuple[float, IndexedChunk]] = {}
        for position, chunk in enumerate(self._chunks):
            score = float(np.clip(similarities[position], -1.0, 1.0))
            current = best.get(chunk.document.id)
            if current is None or score > current[0]:
                best[chunk.document.id] = (score, chunk)
        ranked = sorted(
            best.values(),
            key=lambda entry: (-entry[0], entry[1].document.id, entry[1].ordinal),
        )
        return [
            ScoredDocument(chunk.document, _snippet(chunk.text), score)
            for score, chunk in ranked
            if min_score is None or score >= min_score
        ][:top_k]
