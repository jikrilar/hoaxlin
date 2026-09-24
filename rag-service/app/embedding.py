"""Replaceable embedding interface and a CPU-only pretrained implementation."""

from __future__ import annotations

from typing import Protocol

import numpy as np

from app.config import MODEL_ID, MODEL_REVISION


class EmbeddingBackend(Protocol):
    @property
    def dimension(self) -> int: ...

    def encode(self, texts: list[str]) -> np.ndarray: ...


class SentenceTransformerEmbedding:
    def __init__(self) -> None:
        # Startup is offline. Model setup is an explicit, separate command.
        from sentence_transformers import SentenceTransformer

        self._model = SentenceTransformer(
            MODEL_ID,
            revision=MODEL_REVISION,
            device="cpu",
            local_files_only=True,
        )
        dimension = self._model.get_embedding_dimension()
        if not isinstance(dimension, int) or dimension < 1:
            raise ValueError("embedding_dimension_invalid")
        self._dimension = dimension

    @property
    def dimension(self) -> int:
        return self._dimension

    def encode(self, texts: list[str]) -> np.ndarray:
        return np.asarray(
            self._model.encode(
                texts,
                batch_size=16,
                convert_to_numpy=True,
                show_progress_bar=False,
            ),
            dtype=np.float32,
        )
