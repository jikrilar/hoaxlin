from __future__ import annotations

import re
import unicodedata

_HTML_TAG_RE = re.compile(r"<[^>]+>")
_WHITESPACE_RE = re.compile(r"\s+")
_TOKEN_RE = re.compile(r"[a-z0-9]+", re.IGNORECASE)
_URL_RE = re.compile(r"https?://\S+", re.IGNORECASE)

INDONESIAN_STOPWORDS: frozenset[str] = frozenset(
    {
        "yang", "dan", "di", "ke", "dari", "ini", "itu", "dengan", "untuk", "pada",
        "adalah", "tidak", "akan", "juga", "sudah", "belum", "dalam", "sebagai",
        "atau", "oleh", "karena", "namun", "tetapi", "serta", "agar", "saat",
        "setelah", "sebelum", "antara", "hingga", "sampai", "kepada", "para",
        "para", "sebuah", "seorang", "sekitar", "tersebut", "mereka", "kami",
        "kita", "saya", "anda", "kamu", "dia", "ia", "itu", "apa", "siapa",
        "kenapa", "mengapa", "bagaimana", "kapan", "dimana", "mana", "bisa",
        "dapat", "harus", "mungkin", "sangat", "lebih", "paling", "semua",
        "banyak", "beberapa", "setiap", "seluruh", "lainnya", "lain", "juga",
        "ada", "adanya", "berada", "mengenai", "tentang", "melalui", "selama",
        "ketika", "jika", "kalau", "maka", "sehingga", "sementara", "bahwa",
        "seperti", "berikut", "berbagai", "terkait", "berdasarkan", "menurut",
        "menyebutkan", "menjelaskan", "mengatakan", "mengklaim", "mengeklaim",
        "beredar", "unggahan", "media", "sosial", "narasi", "informasi", "klaim",
        "konten", "foto", "video", "tautan", "link", "akun", "pesan", "berita",
    }
)


def strip_html(text: str) -> str:
    return _HTML_TAG_RE.sub(" ", text)


def collapse_whitespace(text: str) -> str:
    return _WHITESPACE_RE.sub(" ", text).strip()


def normalize_unicode(text: str) -> str:
    return unicodedata.normalize("NFKC", text)


def clean_text(text: str, *, strip_urls: bool = False) -> str:
    """Canonical cleaning applied to every raw text before training."""
    if not text:
        return ""
    text = normalize_unicode(text)
    text = strip_html(text)
    if strip_urls:
        text = _URL_RE.sub(" ", text)
    text = _WHITESPACE_RE.sub(" ", text).strip()
    return text


def tokens(text: str) -> list[str]:
    return _TOKEN_RE.findall(text.lower())


def tokenize_norm(text: str) -> list[str]:
    """Lowercased alphanumeric tokens used for dedup keys and shingles."""
    return tokens(clean_text(text, strip_urls=True))


def normalize_key(text: str) -> str:
    """Canonical normalization for exact-duplicate detection."""
    return " ".join(tokenize_norm(text))


def sha1_hex(text: str) -> str:
    import hashlib

    return hashlib.sha1(normalize_key(text).encode("utf-8")).hexdigest()


def word_shingles(tokens_seq: list[str], size: int = 3) -> list[tuple[str, ...]]:
    if len(tokens_seq) < size:
        return [tuple(tokens_seq)]
    return [tuple(tokens_seq[i : i + size]) for i in range(len(tokens_seq) - size + 1)]


ENGLISH_STOPWORDS: frozenset[str] = frozenset(
    {
        "the", "and", "for", "with", "that", "this", "from", "are", "was", "were",
        "have", "has", "had", "will", "would", "can", "could", "should", "shall",
        "but", "not", "you", "your", "they", "them", "their", "there", "here",
        "what", "when", "where", "which", "who", "whom", "how", "why", "then",
        "than", "so", "if", "or", "as", "at", "by", "in", "on", "of", "to", "up",
        "an", "a", "be", "been", "being", "do", "does", "did", "doing", "its",
        "it's", "it", "he", "she", "his", "her", "him", "we", "us", "our", "ours",
    }
)


def id_ratio(text: str) -> float:
    """Fraction of tokens that are Indonesian stopwords (0.0-1.0)."""
    tok = tokenize_norm(text)
    if not tok:
        return 0.0
    return sum(1 for t in tok if t in INDONESIAN_STOPWORDS) / len(tok)


def en_ratio(text: str) -> float:
    """Fraction of tokens that are English stopwords (0.0-1.0)."""
    tok = tokenize_norm(text)
    if not tok:
        return 0.0
    return sum(1 for t in tok if t in ENGLISH_STOPWORDS) / len(tok)


def cut_claim(text: str) -> tuple[str, bool]:
    """Extract the circulating-claim portion of a fact-check article.

    Fact-check articles describe the claim first ("Beredar ... mengeklaim ...")
    and then announce the verdict ("Faktanya, klaim tersebut ..."). Training on
    the full text would leak the verdict through boilerplate, so we cut the
    text at the first verdict/evidence marker. Returns (claim_text, cut_flag).

    ``cut_flag`` is True when a marker was found and the claim text differs
    from the full text.
    """
    cleaned = clean_text(text)
    if not cleaned:
        return "", False
    prefix_re = re.compile(r"^\s*penjelasan\s*:?\s*", re.IGNORECASE)
    lowered = prefix_re.sub("", cleaned.lower())
    cleaned = prefix_re.sub("", cleaned)

    markers = (
        "faktanya",
        "setelah dilakukan penelusuran",
        "dilansir dari",
        "melansir dari",
        "dikutip dari",
        "berdasarkan laman",
        "berdasarkan penelusuran",
        "melalui akun resmi",
        "melalui laman resmi",
        "link counter",
    )
    positions = [lowered.find(m) for m in markers]
    positions = [p for p in positions if p >= 0]
    if not positions:
        return cleaned, False
    cut = min(positions)
    if cut < 120:
        return cleaned, False
    return cleaned[:cut].strip(), True


def is_indonesian(text: str, min_ratio: float = 0.35) -> bool:
    """Heuristic language gate: keep Indonesian texts, drop non-Indonesian ones.

    A text is kept when it has enough Indonesian stopwords OR when English
    stopwords are not dominant (Indonesian text is often acronym- and
    proper-noun-heavy and scores low on the Indonesian list).
    """
    if id_ratio(text) >= min_ratio:
        return True
    return en_ratio(text) <= id_ratio(text)