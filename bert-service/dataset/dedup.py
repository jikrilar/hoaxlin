from __future__ import annotations

import hashlib
from collections import defaultdict
from dataclasses import dataclass, field

import numpy as np

from dataset.normalize import sha1_hex, tokenize_norm, word_shingles

_MERSENNE = (1 << 61) - 1


def _shingle_hash(shingle: tuple[str, ...]) -> int:
    digest = hashlib.md5("|".join(shingle).encode("utf-8")).digest()
    return int.from_bytes(digest[:8], "big")


@dataclass(frozen=True)
class ExactDedupResult:
    kept: list[str]
    removed: list[str]


def exact_dedup(doc_ids: list[str], texts: list[str]) -> ExactDedupResult:
    """Remove exact duplicates on normalized text (first occurrence wins)."""
    seen: dict[str, str] = {}
    kept: list[str] = []
    removed: list[str] = []
    for doc_id, text in zip(doc_ids, texts):
        key = sha1_hex(text)
        if key in seen:
            removed.append(doc_id)
        else:
            seen[key] = doc_id
            kept.append(doc_id)
    return ExactDedupResult(kept=kept, removed=removed)


def jaccard(a: set[tuple[str, ...]], b: set[tuple[str, ...]]) -> float:
    if not a or not b:
        return 0.0
    return len(a & b) / len(a | b)


@dataclass(slots=True)
class MinHash:
    """MinHash signature over word trigrams.

    The permutation family is fixed per instance so all documents are hashed
    with the same functions (required for MinHash correctness).
    """

    n_permutations: int = 128
    seed: int = 42
    _rng: np.random.Generator = field(init=False, repr=False)
    _a: np.ndarray = field(init=False, repr=False)
    _b: np.ndarray = field(init=False, repr=False)

    def __post_init__(self) -> None:
        self._rng = np.random.default_rng(self.seed)
        self._a = self._rng.integers(1, _MERSENNE, size=self.n_permutations, dtype=np.uint64)
        self._b = self._rng.integers(0, _MERSENNE, size=self.n_permutations, dtype=np.uint64)

    def signature(self, text: str) -> np.ndarray:
        shingles = set(word_shingles(tokenize_norm(text), size=3))
        if not shingles:
            return np.zeros(self.n_permutations, dtype=np.uint64)
        hashes = np.fromiter(
            (_shingle_hash(s) for s in shingles),
            dtype=np.uint64,
            count=len(shingles),
        )
        # h' = (a*h + b) mod M  (vectorized over shingles x permutations)
        permuted = (
            hashes[:, None] * self._a[None, :] + self._b[None, :]
        ) % np.uint64(_MERSENNE)
        return permuted.min(axis=0)


class NearDuplicateClusterer:
    """MinHash + banded LSH candidate generation with Jaccard confirmation.

    Groups near-duplicate documents into claim clusters. The cluster ids
    power leakage-safe splitting: no two documents of the same claim may land
    in different splits.
    """

    def __init__(
        self,
        *,
        n_permutations: int = 128,
        n_bands: int = 16,
        seed: int = 42,
        min_similarity: float = 0.65,
    ) -> None:
        if n_permutations % n_bands != 0:
            raise ValueError("n_permutations must be divisible by n_bands")
        self.minhash = MinHash(n_permutations=n_permutations, seed=seed)
        self.n_bands = n_bands
        self.rows_per_band = n_permutations // n_bands
        self.min_similarity = min_similarity
        self._signatures: dict[str, np.ndarray] = {}
        self._shingles: dict[str, set[tuple[str, ...]]] = {}

    def add(self, doc_id: str, text: str) -> None:
        tokens_seq = tokenize_norm(text)
        self._shingles[doc_id] = set(word_shingles(tokens_seq, size=3))
        self._signatures[doc_id] = self.minhash.signature(text)

    def candidate_pairs(self) -> list[tuple[str, str]]:
        buckets: dict[tuple[int, bytes], list[str]] = defaultdict(list)
        for doc_id, sig in self._signatures.items():
            for band in range(self.n_bands):
                start = band * self.rows_per_band
                rows = sig[start : start + self.rows_per_band]
                key = (band, rows.tobytes())
                buckets[key].append(doc_id)
        pairs: set[tuple[str, str]] = set()
        for members in buckets.values():
            members = sorted(set(members))
            for i in range(len(members)):
                for j in range(i + 1, len(members)):
                    left, right = members[i], members[j]
                    pairs.add((left, right) if left < right else (right, left))
        return sorted(pairs)

    def confirmed_pairs(self) -> list[tuple[str, str]]:
        confirmed: list[tuple[str, str]] = []
        for left, right in self.candidate_pairs():
            similarity = jaccard(self._shingles[left], self._shingles[right])
            if similarity >= self.min_similarity:
                confirmed.append((left, right))
        return confirmed

    def clusters(self) -> dict[str, str]:
        """Map every added doc_id to a claim-group id (the smallest id in its cluster)."""
        parent = {doc_id: doc_id for doc_id in self._signatures}

        def find(node: str) -> str:
            while parent[node] != node:
                parent[node] = parent[parent[node]]
                node = parent[node]
            return node

        def union(a: str, b: str) -> None:
            ra, rb = find(a), find(b)
            if ra != rb:
                if rb < ra:
                    ra, rb = rb, ra
                parent[rb] = ra

        for left, right in self.confirmed_pairs():
            union(left, right)

        return {doc_id: find(doc_id) for doc_id in parent}