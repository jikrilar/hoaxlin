from __future__ import annotations

import pytest

from dataset.normalize import (
    clean_text,
    cut_claim,
    id_ratio,
    is_indonesian,
    normalize_key,
    sha1_hex,
    strip_html,
    tokens,
    word_shingles,
)


def test_strip_html_removes_tags():
    assert strip_html("<p>Hello <b>world</b></p>") == " Hello  world  "


def test_clean_text_collapses_and_strips():
    assert clean_text("<p>Hello <b>world</b></p>") == "Hello world"
    assert clean_text("<p>  Beredar\n\tunggahan  &nbsp; </p>") == "Beredar unggahan &nbsp;"
    assert clean_text("Halo   dunia\t") == "Halo dunia"


def test_clean_text_strip_urls():
    text = "Lihat https://example.com/a?b=1 untuk info"
    assert clean_text(text, strip_urls=True) == "Lihat untuk info"


def test_normalize_key_and_hash_are_consistent():
    a = normalize_key("<p>HOAX   Beredar di <b>Facebook</b></p>")
    b = normalize_key("hoax beredar di facebook")
    assert a == b
    assert sha1_hex("<p>HOAX   Beredar di <b>Facebook</b></p>") == sha1_hex(
        "hoax beredar di facebook"
    )


def test_tokens_lowercase_alphanumeric():
    assert tokens("Beredar unggahan FB 2026!") == ["beredar", "unggahan", "fb", "2026"]


def test_word_shingles():
    seq = ["a", "b", "c", "d"]
    assert word_shingles(seq, size=3) == [("a", "b", "c"), ("b", "c", "d")]
    assert word_shingles(["a"], size=3) == [("a",)]


def test_id_ratio_indonesian_vs_english():
    id_text = "yang beredar di media sosial dengan informasi tersebut"
    en_text = "the quick brown fox jumps over the lazy dog"
    assert id_ratio(id_text) > 0.5
    assert id_ratio(en_text) < 0.2


def test_is_indonesian():
    assert is_indonesian("yang beredar di media sosial dengan klaim tersebut")
    assert not is_indonesian("the quick brown fox jumps over the lazy dog again now")


def test_cut_claim_stops_before_verdict():
    text = (
        "Beredar unggahan di media sosial yang mengeklaim sesuatu yang aneh dan "
        "tidak masuk akal sama sekali menurut berbagai sumber. Faktanya, klaim "
        "tersebut adalah hoaks dan tidak benar."
    )
    claim, cut = cut_claim(text)
    assert cut is True
    assert "faktanya" not in claim.lower()
    assert "Beredar" in claim


def test_cut_claim_falls_back_when_marker_too_early():
    text = "Faktanya klaim tersebut hoaks dan ini hanya teks pendek."
    claim, cut = cut_claim(text)
    assert cut is False
    assert claim == text


def test_cut_claim_removes_penjelasan_prefix():
    text = (
        "Penjelasan: Beredar unggahan di media sosial yang mengeklaim bahwa "
        "pemerintah akan membagikan uang tunai kepada seluruh warga negara "
        "Indonesia tanpa syarat apapun pada bulan depan dan meminta masyarakat "
        "segera mendaftar melalui tautan yang beredar. Faktanya, klaim tersebut "
        "hoaks dan tidak memiliki dasar hukum."
    )
    claim, cut = cut_claim(text)
    assert cut is True
    assert "penjelasan" not in claim.lower()
    assert "Beredar" in claim


def test_cut_claim_empty():
    assert cut_claim("") == ("", False)
    assert cut_claim("   ") == ("", False)