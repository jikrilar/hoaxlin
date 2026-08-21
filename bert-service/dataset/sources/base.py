from __future__ import annotations

from abc import ABC, abstractmethod
from collections.abc import Iterator

from dataset.schema import SourceMeta, SourceRecord


class SourceAdapter(ABC):
    """Adapter contract: turns one raw export into ``SourceRecord``s."""

    name: str
    kind: str
    license: str
    url: str | None = None

    @abstractmethod
    def read(self) -> Iterator[SourceRecord]:
        raise NotImplementedError

    @abstractmethod
    def meta(self) -> SourceMeta:
        raise NotImplementedError


class AdapterError(Exception):
    pass


def register(adapters: list[SourceAdapter]) -> dict[str, SourceAdapter]:
    by_name: dict[str, SourceAdapter] = {}
    for adapter in adapters:
        if adapter.name in by_name:
            raise AdapterError(f"duplicate adapter name: {adapter.name!r}")
        by_name[adapter.name] = adapter
    return by_name