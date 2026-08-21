from __future__ import annotations

import pytest

from dataset.dedup import NearDuplicateClusterer, exact_dedup, jaccard


def _sample_texts() -> list[str]:
    return [
        "Beredar unggahan di media sosial tentang undian berhadiah bank yang mencurigakan",
        "Beredar unggahan di media sosial tentang undian berhadiah bank yang mencurigakan",
        "Beredar unggahan di media sosial tentang undian berhadiah bank yang mencurigakan sekali",
        "Presiden menghadiri peluncuran koperasi desa merah putih di Kabupaten Klaten",
        "Presiden menghadiri peluncuran koperasi desa merah putih di Kabupaten Klaten",
    ]


def test_exact_dedup_keeps_first():
    result = exact_dedup(["a", "b", "c", "d", "e"], _sample_texts())
    assert result.kept == ["a", "c", "d"]
    assert result.removed == ["b", "e"]


def test_exact_dedup_case_and_whitespace_insensitive():
    result = exact_dedup(["x", "y"], ["Hoax   Beredar <b>di</b> FB", "hoax beredar di fb"])
    assert result.removed == ["y"]


def test_jaccard():
    a = {"satu", "dua", "tiga"}
    b = {"satu", "dua", "empat"}
    assert jaccard(a, a) == 1.0
    assert jaccard(a, b) == pytest.approx(0.5)
    assert jaccard(set(), {"x"}) == 0.0


def test_clusterer_finds_near_duplicates():
    clusterer = NearDuplicateClusterer(n_permutations=64, n_bands=8, seed=7, min_similarity=0.6)
    docs = [
        ("d1", "Beredar unggahan di media sosial tentang undian berhadiah bank yang mencurigakan"),
        ("d2", "Beredar unggahan di media sosial tentang undian berhadiah bank yang mencurigakan"),
        ("d3", "Beredar unggahan di media sosial tentang undian berhadiah bank yang mencurigakan sekali"),
        ("d4", "Presiden menghadiri peluncuran koperasi desa merah putih di Kabupaten Klaten"),
        ("d5", "Presiden menghadiri peluncuran koperasi desa merah putih di Kabupaten Klaten"),
    ]
    for doc_id, text in docs:
        clusterer.add(doc_id, text)
    pairs = clusterer.confirmed_pairs()
    assert ("d1", "d2") in pairs
    assert ("d4", "d5") in pairs
    clusters = clusterer.clusters()
    assert clusters["d1"] == clusters["d2"] == clusters["d3"]
    assert clusters["d4"] == clusters["d5"]
    assert clusters["d1"] != clusters["d4"]


def test_clusterer_distinct_docs_stay_alone():
    clusterer = NearDuplicateClusterer(n_permutations=64, n_bands=8, seed=7, min_similarity=0.9)
    docs = [
        ("d1", "Beredar undian berhadiah bank nasional di media sosial facebook"),
        ("d2", "Kementerian pertanian meluncurkan program ketahanan pangan nasional"),
    ]
    for doc_id, text in docs:
        clusterer.add(doc_id, text)
    clusters = clusterer.clusters()
    assert clusters["d1"] != clusters["d2"]


def test_clusterer_permutation_validation():
    with pytest.raises(ValueError):
        NearDuplicateClusterer(n_permutations=100, n_bands=16)