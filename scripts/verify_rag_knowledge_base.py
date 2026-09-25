"""Fetch and conservatively screen a candidate RAG corpus against source pages.

This is an offline curation tool, not a runtime crawler. It uses deterministic
metadata, entity, number, topic, and text-support checks. It does not claim that
automated checks replace a human or entailment-model review.
"""

from __future__ import annotations

import argparse
from concurrent.futures import ThreadPoolExecutor, as_completed
from dataclasses import dataclass
from datetime import date
from decimal import Decimal, InvalidOperation
from html.parser import HTMLParser
import hashlib
import json
from pathlib import Path
import re
import tempfile
import unicodedata
from urllib.error import HTTPError, URLError
from urllib.parse import urlsplit
from urllib.request import Request, urlopen

try:
    from scripts.validate_rag_knowledge_base import validate_knowledge_base
except ModuleNotFoundError:
    from validate_rag_knowledge_base import validate_knowledge_base


ROOT = Path(__file__).resolve().parents[1]
DEFAULT_CANDIDATE = ROOT / "datasets/rag/knowledge-base-v1.1.0"
DEFAULT_FINAL = ROOT / "datasets/rag/knowledge-base-v1.1.1"
MAX_RESPONSE_BYTES = 5_000_000
REQUEST_TIMEOUT_SECONDS = 20
USER_AGENT = "HoaxlinKnowledgeBaseAudit/1.0"

SOURCE_ROOTS: dict[str, set[str]] = {
    "BMKG": {"bmkg.go.id"},
    "Stasiun Pemantau Atmosfer Global Lore Lindu Bariri, BMKG": {"bmkg.go.id"},
    "BNPB": {"bnpb.go.id"},
    "BPOM": {"pom.go.id"},
    "Bank Indonesia": {"bi.go.id"},
    "CekFakta": {"cekfakta.com"},
    "Kementerian Energi dan Sumber Daya Mineral (ESDM)": {"esdm.go.id"},
    "Kementerian Kesehatan RI": {"kemkes.go.id"},
    "Kementerian Keuangan (DJPb)": {"kemenkeu.go.id"},
    "Kementerian Komunikasi dan Digital (Komdigi)": {"komdigi.go.id"},
    "Kementerian Pendidikan Dasar dan Menengah": {"kemendikdasmen.go.id"},
    "Kementerian Perhubungan": {"dephub.go.id", "kemenhub.go.id"},
    "MAFINDO / TurnBackHoax.ID": {"turnbackhoax.id"},
    "Otoritas Jasa Keuangan (OJK)": {"ojk.go.id"},
}

PUBLISHER_ALIASES: dict[str, tuple[str, ...]] = {
    "BMKG": ("bmkg", "badan meteorologi"),
    "Stasiun Pemantau Atmosfer Global Lore Lindu Bariri, BMKG": ("bmkg", "stasiun pemantau"),
    "BNPB": ("bnpb", "badan nasional penanggulangan bencana"),
    "BPOM": ("bpom", "badan pengawas obat"),
    "Bank Indonesia": ("bank indonesia",),
    "CekFakta": ("cekfakta", "cek fakta"),
    "Kementerian Energi dan Sumber Daya Mineral (ESDM)": ("esdm", "energi dan sumber daya mineral"),
    "Kementerian Kesehatan RI": ("kementerian kesehatan", "kemenkes", "ayosehat"),
    "Kementerian Keuangan (DJPb)": ("djpb", "direktorat jenderal perbendaharaan"),
    "Kementerian Komunikasi dan Digital (Komdigi)": ("komdigi", "komunikasi dan digital"),
    "Kementerian Pendidikan Dasar dan Menengah": ("kemendikdasmen", "pendidikan dasar dan menengah"),
    "Kementerian Perhubungan": ("kementerian perhubungan", "dephub", "kemenhub"),
    "MAFINDO / TurnBackHoax.ID": ("turnbackhoax", "mafindo"),
    "Otoritas Jasa Keuangan (OJK)": ("ojk", "otoritas jasa keuangan"),
}

TOPIC_TERMS: dict[str, tuple[str, ...]] = {
    "bantuan-publik": ("bansos", "bantuan", "subsidi", "beras", "blt", "mbg", "pkh", "penerima"),
    "bencana-cuaca": ("cuaca", "gempa", "banjir", "hujan", "siklon", "tsunami", "bencana", "kemarau", "angin"),
    "ekonomi-keuangan": ("ekonomi", "anggaran", "pajak", "rupiah", "apbn", "keuangan", "harga"),
    "energi": ("energi", "bbm", "listrik", "minyak", "gas", "batu bara", "solar"),
    "keamanan": ("keamanan", "polisi", "tni", "penipuan", "kejahatan"),
    "keamanan-digital": ("phishing", "penipuan", "scam", "whatsapp", "tautan", "akun", "digital", "telegram"),
    "kesehatan": ("kesehatan", "vaksin", "vaksinasi", "obat", "penyakit", "tbc", "imunisasi", "medis"),
    "keuangan": ("keuangan", "bank", "rupiah", "pajak", "investasi", "ojk", "bi"),
    "keuangan-digital": ("keuangan", "digital", "konsumen", "transaksi", "fintech", "pembayaran"),
    "layanan-publik": ("layanan", "pajak", "kendaraan", "pemerintah", "administrasi", "kartu"),
    "lingkungan": ("lingkungan", "iklim", "polusi", "sampah", "hutan", "udara"),
    "olahraga": ("fifa", "afc", "olahraga", "timnas", "sepak bola", "atlet"),
    "pangan": ("pangan", "beras", "makanan", "pangan", "pestisida", "pertanian"),
    "pemerintahan": ("pemerintah", "presiden", "kementerian", "desa", "anggaran", "kebijakan"),
    "pendidikan": ("pendidikan", "sekolah", "murid", "guru", "beasiswa", "spmb", "adem"),
    "teknologi": ("teknologi", "digital", "internet", "aplikasi", "akun", "telegram"),
    "transportasi": ("transportasi", "mudik", "kereta", "jalan", "pelabuhan", "perhubungan", "kendaraan"),
}

STOPWORDS = frozenset(
    "dan yang di ke dari untuk pada dengan tentang atau dalam ini itu sebagai oleh "
    "artikel menyatakan menjelaskan menyebut menilai klaim informasi berita "
    "masyarakat hasil bahwa tidak bukan dapat akan masih telah adalah menjadi "
    "sebagai terhadap melalui serta karena namun agar dalam sebuah para pihak "
    "menurut berdasarkan terkait lebih berbagai salah hoaks keliru benar "
    "artikel mengulas memeriksa pemeriksaan fakta sumber resmi publikasi "
    "selama ketika jika kepada sebagai antara dari itu tersebut mereka "
    "program dilakukan termasuk mengenai secara salah satu sampai tanpa "
    "bagi maka setelah sebelum dengan oleh karena pada ketika melalui "
    "the and for from with this that claim article states says reports"
    .split()
)
GENERIC_PUBLISHER_WORDS = frozenset(
    {"artikel", "berita", "publikasi", "kementerian", "pemerintah", "nasional", "republik", "indonesia"}
)
SKIP_TAGS = frozenset(
    {"script", "style", "noscript", "svg", "nav", "footer", "header", "aside", "form", "button", "iframe", "template"}
)
VOID_TAGS = frozenset({"area", "base", "br", "col", "embed", "hr", "img", "input", "link", "meta", "param", "source", "track", "wbr"})
SKIP_MARKERS = ("cookie", "navbar", "navigation", "breadcrumb", "related", "share", "social", "footer", "header", "menu", "banner", "widget", "popup", "advert", "iklan")
NUMBER_PATTERN = re.compile(r"(?<!\d)\d+(?:[.,]\d+)*")
TOKEN_PATTERN = re.compile(r"[a-z0-9]+", re.IGNORECASE)
ACRONYM_PATTERN = re.compile(r"\b[A-Z][A-Z0-9]{1,}\b")
CAPITALIZED_NAME_PATTERN = re.compile(r"\b[A-Z][a-z]+(?:\s+(?:[A-Z][a-z]+|RI|TBC|HPV|COVID-19)){1,3}\b")


def normalize_text(value: str) -> str:
    return " ".join(unicodedata.normalize("NFKC", value).casefold().split())


def tokens(value: str) -> list[str]:
    return TOKEN_PATTERN.findall(normalize_text(value))


def source_root(host: str, allowed_roots: set[str]) -> str | None:
    host = host.casefold().rstrip(".")
    for root in sorted(allowed_roots, key=len, reverse=True):
        if host == root or host.endswith("." + root):
            return root
    return None


def _recursive_jsonld_values(value: object, key: str) -> list[str]:
    found: list[str] = []
    if isinstance(value, dict):
        for item_key, item_value in value.items():
            if item_key.casefold() == key.casefold():
                if isinstance(item_value, str):
                    found.append(item_value)
                elif isinstance(item_value, dict):
                    name = item_value.get("name")
                    if isinstance(name, str):
                        found.append(name)
            found.extend(_recursive_jsonld_values(item_value, key))
    elif isinstance(value, list):
        for item in value:
            found.extend(_recursive_jsonld_values(item, key))
    return found


class ArticleParser(HTMLParser):
    """Extract visible article/main text and standard publication metadata."""

    def __init__(self) -> None:
        super().__init__(convert_charrefs=True)
        self.meta: dict[str, str] = {}
        self._stack: list[tuple[str, bool]] = []
        self._body: list[str] = []
        self._main: list[str] = []
        self._article: list[str] = []
        self._title_text: list[str] = []
        self._h1_text: list[str] = []
        self._jsonld_chunks: list[str] = []
        self._active_jsonld: list[str] | None = None
        self._time_stack: list[dict[str, object]] = []
        self._time_candidates: list[dict[str, object]] = []

    def handle_starttag(self, tag: str, attrs: list[tuple[str, str | None]]) -> None:
        attributes = {key.casefold(): value or "" for key, value in attrs}
        classes = f"{attributes.get('class', '')} {attributes.get('id', '')}".casefold()
        parent_hidden = self._stack[-1][1] if self._stack else False
        hidden = parent_hidden or tag.casefold() in SKIP_TAGS or any(
            marker in classes for marker in SKIP_MARKERS
        )
        if tag.casefold() not in VOID_TAGS:
            self._stack.append((tag.casefold(), hidden))

        if tag.casefold() == "meta":
            key = attributes.get("property") or attributes.get("name") or attributes.get("itemprop")
            content = attributes.get("content", "").strip()
            if key and content:
                self.meta[key.casefold()] = content
        elif tag.casefold() == "script" and "ld+json" in attributes.get("type", "").casefold():
            self._active_jsonld = []
        elif tag.casefold() == "time":
            self._time_stack.append({"datetime": attributes.get("datetime", ""), "text": []})

    def handle_startendtag(self, tag: str, attrs: list[tuple[str, str | None]]) -> None:
        self.handle_starttag(tag, attrs)
        self.handle_endtag(tag)

    def handle_endtag(self, tag: str) -> None:
        tag = tag.casefold()
        if tag == "script" and self._active_jsonld is not None:
            self._jsonld_chunks.append("".join(self._active_jsonld))
            self._active_jsonld = None
        if tag == "time" and self._time_stack:
            self._time_candidates.append(self._time_stack.pop())
        for index in range(len(self._stack) - 1, -1, -1):
            if self._stack[index][0] == tag:
                del self._stack[index:]
                break

    def handle_data(self, data: str) -> None:
        if self._active_jsonld is not None:
            self._active_jsonld.append(data)
        hidden = any(item[1] for item in self._stack)
        if hidden:
            return
        if self._time_stack:
            self._time_stack[-1]["text"].append(data)
        text = " ".join(data.split())
        if not text:
            return
        self._body.append(text)
        active_tags = {item[0] for item in self._stack}
        if "main" in active_tags:
            self._main.append(text)
        if "article" in active_tags:
            self._article.append(text)
        if "title" in active_tags:
            self._title_text.append(text)
        if "h1" in active_tags:
            self._h1_text.append(text)

    def extract(self) -> dict[str, str | None]:
        jsonld: list[object] = []
        for raw in self._jsonld_chunks:
            try:
                jsonld.append(json.loads(raw))
            except json.JSONDecodeError:
                continue
        jsonld_title = next(iter(_recursive_jsonld_values(jsonld, "headline")), "")
        jsonld_date = next(iter(_recursive_jsonld_values(jsonld, "datePublished")), "")
        jsonld_body = next(iter(_recursive_jsonld_values(jsonld, "articleBody")), "")
        title = (
            self.meta.get("og:title")
            or jsonld_title
            or " ".join(self._h1_text)
            or " ".join(self._title_text)
        ).strip()
        publisher = (
            self.meta.get("og:site_name")
            or self.meta.get("application-name")
            or None
        )
        meta_date = (
            self.meta.get("article:published_time")
            or self.meta.get("datepublished")
            or self.meta.get("date")
            or self.meta.get("parsely-pub-date")
        )
        page_date = next(
            (
                parsed
                for item in self._time_candidates
                for parsed in (
                    _parse_publication_date(" ".join(item["text"])),
                    _parse_publication_date(str(item["datetime"])),
                )
                if parsed
            ),
            _parse_publication_date(meta_date or jsonld_date),
        )
        body = " ".join(self._body)
        main = " ".join(self._main)
        article = " ".join(self._article)
        page_text = max((article, main, jsonld_body, body), key=len).strip()
        return {
            "title": title or None,
            "publisher": publisher,
            "published_at": page_date,
            "text": page_text,
        }


MONTHS = {
    "januari": 1, "january": 1, "februari": 2, "february": 2, "maret": 3, "march": 3,
    "april": 4, "mei": 5, "may": 5, "juni": 6, "june": 6, "juli": 7, "july": 7,
    "agustus": 8, "august": 8, "september": 9, "oktober": 10, "october": 10,
    "november": 11, "desember": 12, "december": 12,
}


def _parse_publication_date(value: str) -> str | None:
    value = value.strip()
    iso = re.search(r"\b(\d{4}-\d{2}-\d{2})\b", value)
    if iso:
        try:
            return date.fromisoformat(iso.group(1)).isoformat()
        except ValueError:
            return None
    slash = re.search(r"\b(\d{1,2})/(\d{1,2})/(\d{4})\b", value)
    if slash:
        try:
            return date(int(slash.group(3)), int(slash.group(2)), int(slash.group(1))).isoformat()
        except ValueError:
            return None
    named = re.search(r"\b(\d{1,2})\s+([A-Za-z]+)\s+(\d{4})\b", value)
    if named:
        month = MONTHS.get(named.group(2).casefold())
        if month:
            try:
                return date(int(named.group(3)), month, int(named.group(1))).isoformat()
            except ValueError:
                return None
    named = re.search(r"\b([A-Za-z]+)\s+(\d{1,2}),?\s+(\d{4})\b", value)
    if named:
        month = MONTHS.get(named.group(1).casefold())
        if month:
            try:
                return date(int(named.group(3)), month, int(named.group(2))).isoformat()
            except ValueError:
                return None
    return None


@dataclass
class FetchResult:
    source_status: str
    http_status: int | None
    final_url: str | None
    page_title: str | None
    page_publisher: str | None
    page_published_at: str | None
    page_text: str
    error: str | None = None


def fetch_source(document: dict) -> FetchResult:
    request = Request(
        document["source_url"],
        headers={
            "User-Agent": USER_AGENT,
            "Accept": "text/html,application/xhtml+xml;q=0.9,*/*;q=0.5",
            "Accept-Language": "id,en;q=0.8",
        },
    )
    try:
        with urlopen(request, timeout=REQUEST_TIMEOUT_SECONDS) as response:
            status = response.status
            final_url = response.geturl()
            content_type = response.headers.get_content_type()
            raw = response.read(MAX_RESPONSE_BYTES + 1)
    except HTTPError as error:
        return FetchResult("SOURCE_UNREACHABLE", error.code, error.geturl(), None, None, None, "", str(error))
    except (OSError, URLError, TimeoutError) as error:
        return FetchResult("SOURCE_UNREACHABLE", None, None, None, None, None, "", type(error).__name__)

    if status != 200:
        return FetchResult("SOURCE_UNREACHABLE", status, final_url, None, None, None, "", "non-200 status")
    if content_type not in {"text/html", "application/xhtml+xml"}:
        return FetchResult("SOURCE_CONTENT_UNAVAILABLE", status, final_url, None, None, None, "", f"not HTML: {content_type}")
    if len(raw) > MAX_RESPONSE_BYTES:
        raw = raw[:MAX_RESPONSE_BYTES]
    charset = "utf-8"
    match = re.search(r"charset=([\w-]+)", response.headers.get("Content-Type", ""), re.IGNORECASE)
    if match:
        charset = match.group(1)
    try:
        html = raw.decode(charset, errors="replace")
    except LookupError:
        html = raw.decode("utf-8", errors="replace")
    parser = ArticleParser()
    try:
        parser.feed(html)
    except Exception:
        return FetchResult("SOURCE_CONTENT_UNAVAILABLE", status, final_url, None, None, None, "", "HTML parse failed")
    extracted = parser.extract()
    title = str(extracted["title"] or "")
    final_path = urlsplit(final_url).path
    if final_path in {"", "/"} or (title and title_coverage(document["title"], title) < 0.35):
        return FetchResult(
            "SOURCE_MISMATCH", status, final_url, extracted["title"],
            extracted["publisher"], extracted["published_at"], str(extracted["text"]),
            "detail URL resolved to a generic or unrelated page",
        )
    page_text = str(extracted["text"])
    if not _has_substantive_body(page_text, title) or not extracted["title"]:
        return FetchResult(
            "SOURCE_CONTENT_UNAVAILABLE", status, final_url,
            extracted["title"], extracted["publisher"], extracted["published_at"],
            page_text,
            "article body unavailable or contains too little content beyond the page title",
        )
    return FetchResult(
        "SOURCE_VERIFIED", status, final_url,
        extracted["title"], extracted["publisher"], extracted["published_at"],
        page_text,
    )


def title_coverage(expected: str, actual: str) -> float:
    expected_tokens = {item for item in tokens(expected) if item not in STOPWORDS and len(item) > 2}
    actual_tokens = {item for item in tokens(actual) if item not in STOPWORDS and len(item) > 2}
    if not expected_tokens:
        return 0.0
    return len(expected_tokens & actual_tokens) / len(expected_tokens)


def _has_substantive_body(page_text: str, title: str) -> bool:
    title_terms = {
        item for item in tokens(title)
        if len(item) >= 4 and item not in STOPWORDS
    }
    substantive_terms = {
        item for item in tokens(page_text)
        if len(item) >= 4 and item not in STOPWORDS and item not in title_terms
    }
    return len(page_text) >= 160 and len(substantive_terms) >= 12


def _word_present(word: str, page_words: set[str]) -> bool:
    if word in page_words:
        return True
    return any(
        len(word) >= 5 and len(page_word) >= 5
        and (word.startswith(page_word) or page_word.startswith(word))
        for page_word in page_words
    )


def _number_values(value: str) -> set[str]:
    """Return canonical numeric facts, including Indonesian grouped amounts."""
    values: set[str] = set()
    scale_values = {
        "ribu": Decimal(1_000), "rb": Decimal(1_000),
        "juta": Decimal(1_000_000), "jt": Decimal(1_000_000),
        "miliar": Decimal(1_000_000_000), "milyar": Decimal(1_000_000_000),
        "triliun": Decimal(1_000_000_000_000),
    }
    for match in NUMBER_PATTERN.finditer(value):
        raw = match.group(0)
        before = value[max(0, match.start() - 8):match.start()]
        after = value[match.end():match.end() + 20]
        separators = [position for position, char in enumerate(raw) if char in ".,"]
        decimal_text = raw
        if separators:
            last_separator = separators[-1]
            trailing = len(raw) - last_separator - 1
            if len(separators) > 1:
                if trailing == 3:
                    decimal_text = raw.replace(".", "").replace(",", "")
                else:
                    decimal_text = raw.replace(".", "").replace(",", ".")
            elif trailing == 3:
                decimal_text = raw.replace(".", "").replace(",", "")
            else:
                decimal_text = raw.replace(",", ".")
        try:
            number = Decimal(decimal_text)
        except InvalidOperation:
            continue

        scale_match = re.match(r"\s*(ribu|rb|juta|jt|miliar|milyar|triliun)\b", after, re.IGNORECASE)
        if scale_match:
            number *= scale_values[scale_match.group(1).casefold()]
        else:
            # Indonesian financial summaries commonly abbreviate trillion as
            # "T" (for example, "Rp58 T"). Require currency context to avoid
            # treating an unrelated initial as a unit.
            if re.search(r"rp\.?\s*$", before, re.IGNORECASE) and re.match(r"\s*T\b", after):
                number *= Decimal(1_000_000_000_000)

        normalized = format(number.normalize(), "f")
        values.add(normalized)
    return values


def _entities(value: str, source: str) -> set[str]:
    acronyms = set(ACRONYM_PATTERN.findall(value))
    names = set(CAPITALIZED_NAME_PATTERN.findall(value))
    source_terms = {item.upper() for item in re.findall(r"[A-Za-z0-9]{2,}", source)}
    return {
        normalize_text(item)
        for item in acronyms | names
        if item.upper() not in source_terms and item.casefold() not in GENERIC_PUBLISHER_WORDS
    }


def assess_content(document: dict, page: FetchResult, page_root: str) -> dict:
    page_normalized = normalize_text(page.page_text + " " + (page.page_title or ""))
    page_words = set(tokens(page_normalized))
    content_words = [
        item for item in tokens(document["content"])
        if len(item) >= 4 and item not in STOPWORDS
    ]
    unique_terms = sorted(set(content_words))
    supported_terms = [item for item in unique_terms if _word_present(item, page_words)]
    term_coverage = len(supported_terms) / max(1, len(unique_terms))
    content_numbers = _number_values(document["content"])
    page_numbers = _number_values(page_normalized)
    missing_numbers = sorted(content_numbers - page_numbers)
    content_entities = _entities(document["content"], document["source"])
    unsupported_entities = sorted(item for item in content_entities if item not in page_normalized)
    topic_terms = TOPIC_TERMS.get(document.get("topic") or "", ())
    topic_supported = any(_word_present(item, page_words) for item in topic_terms)
    polarity_terms = {"hoaks", "salah", "keliru", "menyesatkan", "penipuan", "palsu", "tidak benar"}
    summary_has_verdict = any(item in normalize_text(document["content"]) for item in polarity_terms)
    page_has_verdict = any(item in page_normalized for item in polarity_terms)
    verdict_supported = not summary_has_verdict or page_has_verdict
    short_words = len(document["content"].split())
    sufficient = (
        short_words >= 20
        and len(unique_terms) >= 8
        and term_coverage >= 0.45
        and topic_supported
        and not missing_numbers
        and not unsupported_entities
    )
    title_score = title_coverage(document["title"], page.page_title or "")

    if missing_numbers or term_coverage < 0.20 or title_score < 0.35:
        content_status = "CONTENT_UNSUPPORTED"
    elif term_coverage >= 0.55 and topic_supported and not unsupported_entities and verdict_supported:
        content_status = "CONTENT_SUPPORTED"
    else:
        content_status = "CONTENT_PARTIALLY_SUPPORTED"

    return {
        "content_status": content_status,
        "title_coverage": round(title_score, 3),
        "summary_term_coverage": round(term_coverage, 3),
        "unsupported_numbers": missing_numbers,
        "unsupported_entities": unsupported_entities,
        "topic_supported": topic_supported,
        "verdict_supported": verdict_supported,
        "word_count": short_words,
        "content_length_status": "SUFFICIENT" if sufficient else "TOO_SHORT",
    }


def assess_document(document: dict, page: FetchResult) -> dict:
    expected_roots = SOURCE_ROOTS.get(document["source"], set())
    original_host = (urlsplit(document["source_url"]).hostname or "").casefold()
    final_host = (urlsplit(page.final_url).hostname or "").casefold() if page.final_url else ""
    expected_root = source_root(original_host, expected_roots)
    final_root = source_root(final_host, expected_roots)
    generic_redirect = bool(page.final_url and urlsplit(page.final_url).path in {"", "/"})
    actual_title_coverage = title_coverage(document["title"], page.page_title or "")
    publisher_aliases = PUBLISHER_ALIASES.get(document["source"], ())
    publisher_text = normalize_text(page.page_publisher or "")
    publisher_consistent = (
        final_root is not None
        and (not publisher_text or any(normalize_text(alias) in publisher_text for alias in publisher_aliases))
    )

    mismatch_reasons = []
    if not expected_root:
        mismatch_reasons.append("source has no configured publisher domain")
    if not final_root:
        mismatch_reasons.append("final URL is outside the declared publisher domain")
    if generic_redirect:
        mismatch_reasons.append("URL redirected to a generic root page")
    if not publisher_consistent:
        mismatch_reasons.append("publisher metadata conflicts with source")
    if page.page_title and actual_title_coverage < 0.35:
        mismatch_reasons.append("page title does not match the KB title")

    source_status = page.source_status
    if source_status in {"SOURCE_VERIFIED", "SOURCE_CONTENT_UNAVAILABLE"} and (
        not expected_root or not final_root or generic_redirect or not publisher_consistent
        or actual_title_coverage < 0.35
    ):
        source_status = "SOURCE_MISMATCH"

    content_assessment = assess_content(document, page, final_root or "") if source_status == "SOURCE_VERIFIED" else {
        "content_status": "CONTENT_PARTIALLY_SUPPORTED",
        "title_coverage": round(actual_title_coverage, 3),
        "summary_term_coverage": 0.0,
        "unsupported_numbers": [],
        "unsupported_entities": [],
        "topic_supported": False,
        "verdict_supported": False,
        "word_count": len(document["content"].split()),
        "content_length_status": "TOO_SHORT" if len(document["content"].split()) < 30 else "SUFFICIENT",
    }

    date_check = "NOT_EXPOSED"
    auto_metadata_update = False
    if page.page_published_at:
        if document["published_at"] != page.page_published_at:
            date_check = "SOURCE_DATE_AVAILABLE"
            auto_metadata_update = True
        else:
            date_check = "MATCH"
    elif document["published_at"] is None:
        date_check = "NO_DATE_AVAILABLE"

    checks = {
        "valid_http_url": True,
        "source_reachable": source_status == "SOURCE_VERIFIED",
        "publisher_consistent": publisher_consistent,
        "title_consistent": actual_title_coverage >= 0.55,
        "content_supported": content_assessment["content_status"] == "CONTENT_SUPPORTED",
        "entity_consistent": not content_assessment["unsupported_entities"],
        "sufficient_content": content_assessment["content_length_status"] == "SUFFICIENT",
        "duplicate_clear": True,
        "metadata_date_consistent": date_check in {"MATCH", "SOURCE_DATE_AVAILABLE", "NO_DATE_AVAILABLE"},
    }
    if source_status in {"SOURCE_UNREACHABLE", "SOURCE_CONTENT_UNAVAILABLE", "SOURCE_MISMATCH"}:
        decision = "EXCLUDED_BY_AUTOMATED_CHECKS"
        reasons = [source_status, *(mismatch_reasons if source_status == "SOURCE_MISMATCH" else [])]
    elif content_assessment["content_status"] == "CONTENT_UNSUPPORTED":
        decision = "EXCLUDED_BY_AUTOMATED_CHECKS"
        reasons = ["CONTENT_UNSUPPORTED"]
        if content_assessment["unsupported_numbers"]:
            reasons.append("numbers absent from source: " + ", ".join(content_assessment["unsupported_numbers"]))
    elif content_assessment["content_length_status"] == "TOO_SHORT":
        decision = "EXCLUDED_BY_AUTOMATED_CHECKS"
        reasons = ["TOO_SHORT; no safe automatic paraphrase was available"]
    elif content_assessment["content_status"] == "CONTENT_PARTIALLY_SUPPORTED":
        decision = "REVIEW_REQUIRED"
        reasons = ["CONTENT_PARTIALLY_SUPPORTED"]
        if content_assessment["unsupported_entities"]:
            reasons.append("entities not located in source: " + ", ".join(content_assessment["unsupported_entities"]))
        if not content_assessment["topic_supported"]:
            reasons.append("topic cue not located in source")
        if content_assessment["summary_term_coverage"] < 0.55:
            reasons.append("summary term coverage below conservative gate")
    elif not all(checks.values()):
        decision = "REVIEW_REQUIRED"
        reasons = [key for key, value in checks.items() if not value]
    else:
        decision = "APPROVED_BY_AUTOMATED_CHECKS"
        reasons = []

    return {
        "document_id": document["id"],
        "source": document["source"],
        "source_url": document["source_url"],
        "http_status": page.http_status,
        "final_url": page.final_url,
        "source_status": source_status,
        "source_mismatch_reasons": mismatch_reasons,
        "content_status": content_assessment["content_status"],
        "decision": decision,
        "reasons": reasons,
        "page_title": page.page_title,
        "page_publisher": page.page_publisher,
        "page_published_at": page.page_published_at,
        "record_published_at": document["published_at"],
        "date_check": date_check,
        "auto_metadata_update": auto_metadata_update,
        "page_text_chars": len(page.page_text),
        "checks": checks,
        **content_assessment,
    }


def _normalized_content(value: str) -> str:
    return " ".join(unicodedata.normalize("NFKC", value).split()).casefold()


def duplicate_assessment(documents: list[dict]) -> tuple[list[tuple[str, str]], list[tuple[str, str, float]]]:
    exact_seen: dict[tuple[str, str], str] = {}
    exact: list[tuple[str, str]] = []
    near: list[tuple[str, str, float]] = []
    prepared = []
    for document in documents:
        normalized = _normalized_content(document["content"])
        provenance_key = (normalized, document.get("source_url", ""))
        if provenance_key in exact_seen:
            exact.append((document["id"], exact_seen[provenance_key]))
        else:
            exact_seen[provenance_key] = document["id"]
        prepared.append((document, set(tokens(document["content"])), set(tokens(document["title"]))))
    for index, (left, left_content, left_title) in enumerate(prepared):
        for right, right_content, right_title in prepared[index + 1:]:
            if left["source"] != right["source"]:
                continue
            content_jaccard = len(left_content & right_content) / max(1, len(left_content | right_content))
            title_jaccard = len(left_title & right_title) / max(1, len(left_title | right_title))
            if content_jaccard >= 0.78 and title_jaccard >= 0.55:
                near.append((left["id"], right["id"], round(content_jaccard, 3)))
    return exact, near


def inherited_overlap_flags(candidate_directory: Path) -> list[tuple[str, str]]:
    """Keep earlier curator risk notes visible without treating them as findings."""
    report_path = Path(candidate_directory) / "review-report.md"
    if not report_path.is_file():
        return []
    flags: list[tuple[str, str]] = []
    for line in report_path.read_text(encoding="utf-8").splitlines():
        id_match = re.match(r"\|\s*`([^`]+)`\s*\|", line)
        risk_match = re.search(
            r"\|\s*((?:potensi\s+(?:near-duplicate|overlap)|overlap)[^|]*)\|",
            line,
            re.IGNORECASE,
        )
        if id_match and risk_match:
            flags.append((id_match.group(1), risk_match.group(1).strip()))
    return sorted(set(flags))


def _markdown_cell(value: object) -> str:
    if value is None:
        return "null"
    return str(value).replace("|", "\\|").replace("\r", " ").replace("\n", " ")


def build_review_report(
    candidates: list[dict], results: list[dict], final_documents: list[dict],
    excluded_duplicates: list[tuple[str, str]], near_duplicates: list[tuple[str, str, float]],
    automatic_fixes: list[dict], inherited_flags: list[tuple[str, str]],
) -> str:
    result_by_id = {item["document_id"]: item for item in results}
    counts = {key: sum(item["decision"] == key for item in results) for key in (
        "APPROVED_BY_AUTOMATED_CHECKS", "EXCLUDED_BY_AUTOMATED_CHECKS", "REVIEW_REQUIRED"
    )}
    source_counts: dict[str, int] = {}
    topic_counts: dict[str, int] = {}
    for document in final_documents:
        source_counts[document["source"]] = source_counts.get(document["source"], 0) + 1
        topic = document.get("topic") or "(null)"
        topic_counts[topic] = topic_counts.get(topic, 0) + 1
    source_statuses = {status: sum(item["source_status"] == status for item in results) for status in (
        "SOURCE_VERIFIED", "SOURCE_UNREACHABLE", "SOURCE_CONTENT_UNAVAILABLE", "SOURCE_MISMATCH"
    )}
    content_statuses = {status: sum(item["content_status"] == status for item in results) for status in (
        "CONTENT_SUPPORTED", "CONTENT_PARTIALLY_SUPPORTED", "CONTENT_UNSUPPORTED"
    )}
    final_short = [item for item in final_documents if len(item["content"].split()) < 30]
    lines = [
        "# Automated source verification - knowledge-base-v1.1.1", "",
        "**Status: AUTOMATED_CHECKS_ONLY.** Tidak ada klaim human review atau owner approval. Pemeriksaan ini deterministik dan berbasis metadata halaman, domain sumber, title, angka, entitas, topik, dan cakupan istilah. Pemeriksaan otomatis bukan bukti kebenaran dan bukan pengganti penilaian entailment manusia.", "",
        f"- Candidate snapshot: v1.1.0 ({len(candidates)} dokumen; 74 tambahan baru dan 24 legacy v1.0.0).",
        f"- Kandidat baru yang diperiksa: {len(results)}.",
        f"- {counts['APPROVED_BY_AUTOMATED_CHECKS']} `APPROVED_BY_AUTOMATED_CHECKS`; {counts['EXCLUDED_BY_AUTOMATED_CHECKS']} `EXCLUDED_BY_AUTOMATED_CHECKS`; {counts['REVIEW_REQUIRED']} `REVIEW_REQUIRED`.",
        f"- Final snapshot menyertakan 24 dokumen legacy v1.0.0 yang dipertahankan dan {counts['APPROVED_BY_AUTOMATED_CHECKS']} tambahan yang lolos semua gate otomatis.",
        f"- Ringkasan kandidat baru <30 kata sebelum filtering: {sum(item['word_count'] < 30 for item in results)}; di final snapshot: {len(final_short)}. Perbaikan ringkasan otomatis: {len(automatic_fixes)}.",
        "- v1.0.0 dan v1.1.0 tetap tersedia; report evaluasi R11 dan relevance set tidak berubah.",
        "- Isi halaman sumber hanya diproses sementara saat audit; HTML mentah tidak disimpan di repository.", "",
        "## Distribusi sumber (final snapshot)", "", "| Source | Dokumen |", "| --- | ---: |",
    ]
    lines.extend(f"| {source} | {count} |" for source, count in sorted(source_counts.items()))
    lines.extend(["", "## Distribusi topic (final snapshot)", "", "| Topic | Dokumen |", "| --- | ---: |"])
    lines.extend(f"| {topic} | {count} |" for topic, count in sorted(topic_counts.items()))
    lines.extend(["", "## Source verification summary", "", "| Status | Count |", "| --- | ---: |"])
    lines.extend(f"| {status} | {count} |" for status, count in source_statuses.items())
    lines.extend(["", "## Content verification summary", "", "| Status | Count |", "| --- | ---: |"])
    lines.extend(f"| {status} | {count} |" for status, count in content_statuses.items())
    lines.extend([
        "", "## Per-document automated assessment", "",
        "| ID | Source status | Content status | Decision | Checks/reason | HTTP | Final URL | Page title | Publisher metadata | Record date | Source date | Date check | Words | Title coverage | Content term coverage |",
        "| --- | --- | --- | --- | --- | ---: | --- | --- | --- | --- | --- | --- | ---: | ---: | ---: |",
    ])
    for candidate in sorted(candidates, key=lambda item: item["id"]):
        result = result_by_id.get(candidate["id"])
        if result is None:
            legacy_note = "PRESERVED_FROM_V1.0.0; not part of the 74-document automated campaign"
            cells = [candidate["id"], "LEGACY_PRESERVED", "LEGACY_PRESERVED", legacy_note, "-", "-", "-", "-", candidate["source"], candidate["published_at"], "-", "-", str(len(candidate["content"].split())), "-", "-"]
        else:
            checks = ", ".join(key for key, passed in result["checks"].items() if not passed)
            reason = "; ".join(result["reasons"]) or ("all deterministic gates passed" if not checks else checks)
            cells = [
                result["document_id"], result["source_status"], result["content_status"], result["decision"],
                reason, result["http_status"], result["final_url"], result["page_title"],
                result["page_publisher"], result["record_published_at"], result["page_published_at"],
                result["date_check"], str(result["word_count"]), result["title_coverage"],
                result["summary_term_coverage"],
            ]
        lines.append("| " + " | ".join(_markdown_cell(cell) for cell in cells) + " |")
    lines.extend(["", "## Excluded or unresolved candidates", "", "Dokumen dengan `CONTENT_PARTIALLY_SUPPORTED` tetap `REVIEW_REQUIRED` dan tidak masuk final snapshot. Dokumen `TOO_SHORT` dikeluarkan bila tidak ada cara aman membentuk parafrasa baru dari materi sumber tanpa menyalin atau menambah fakta.", ""])
    for result in sorted(results, key=lambda item: item["document_id"]):
        if result["decision"] != "APPROVED_BY_AUTOMATED_CHECKS":
            reason = "; ".join(result["reasons"]) or result["decision"]
            lines.append(f"- `{result['document_id']}`: `{result['decision']}`; `{reason}`.")
    lines.extend(["", "## Duplicates", "", "- Exact duplicate pairs: " + (", ".join(f"`{left}` / `{right}`" for left, right in excluded_duplicates) if excluded_duplicates else "none detected by normalized-content check."),
        "- Same-source near-duplicate pairs (deterministic token Jaccard screen; candidates, not confirmed duplicates): " + (", ".join(f"`{left}` / `{right}` ({score})" for left, right, score in near_duplicates) if near_duplicates else "none detected."),
        "- Different-source articles are not removed solely for discussing the same claim.", "",
        "## Inherited preliminary overlap flags", "",
        f"The v1.1.0 candidate report summary claimed 16 possible overlap flags. This audit found {len(inherited_flags)} explicit document-level overlap notes in its review table; the earlier summary count could not be confirmed from those rows. These notes are not confirmed duplicates. The stricter same-source token screen above did not detect a near-duplicate pair; different-source items remain independently sourced unless their normalized content is an exact duplicate.", "",
    ])
    if inherited_flags:
        lines.extend(f"- `{document_id}`: {risk}" for document_id, risk in inherited_flags)
    else:
        lines.append("- No prior overlap flags were present in the candidate report.")
    lines.extend(["",
        "## Automatically corrected metadata", "",
    ])
    if automatic_fixes:
        lines.extend(f"- `{item['id']}`: published_at `{item['before']}` -> `{item['after']}` from explicit source publication metadata." for item in automatic_fixes)
    else:
        lines.append("- None. No summary was rewritten automatically; a safe factual paraphrase could not be generated deterministically.")
    if final_short:
        lines.extend(["", "## Final documents still under 30 words", ""])
        lines.extend(f"- `{document['id']}`: {len(document['content'].split())} words; retained only because source, content, entity, topic, and length gates passed." for document in final_short)
    lines.extend(["", "## Method limitations", "",
        "The checker parses visible article/main text and structured metadata; it does not use an LLM or claim semantic entailment. Content is marked supported only when title coverage, topic cues, key-term coverage, verdict cues, entity checks, and explicit numeric mentions pass together. A false-positive semantic match is still possible; use this artifact as an automated screen, not as human approval or a truth label.",
        ""])
    return "\n".join(lines)


def run_verification(candidate_directory: Path, final_directory: Path, workers: int = 4) -> dict:
    validate_knowledge_base(candidate_directory)
    validate_knowledge_base(ROOT / "datasets/rag/knowledge-base-v1")
    candidate_documents = [
        json.loads(line)
        for line in (candidate_directory / "documents.jsonl").read_text(encoding="utf-8").splitlines()
    ]
    legacy_ids = {
        json.loads(line)["id"]
        for line in (ROOT / "datasets/rag/knowledge-base-v1/documents.jsonl").read_text(encoding="utf-8").splitlines()
    }
    new_documents = [document for document in candidate_documents if document["id"] not in legacy_ids]
    fetched: dict[str, FetchResult] = {}
    with ThreadPoolExecutor(max_workers=max(1, min(workers, 8))) as executor:
        futures = {executor.submit(fetch_source, document): document["id"] for document in new_documents}
        completed = 0
        for future in as_completed(futures):
            fetched[futures[future]] = future.result()
            completed += 1
            if completed % 10 == 0 or completed == len(futures):
                print(f"Fetched source pages: {completed}/{len(futures)}", flush=True)

    results = [assess_document(document, fetched[document["id"]]) for document in new_documents]
    result_by_id = {item["document_id"]: item for item in results}
    exact_duplicates, near_duplicates = duplicate_assessment(candidate_documents)
    prior_overlap_flags = inherited_overlap_flags(candidate_directory)
    for duplicate_id, original_id in exact_duplicates:
        duplicate_result = result_by_id.get(duplicate_id)
        if duplicate_result:
            duplicate_result["decision"] = "EXCLUDED_BY_AUTOMATED_CHECKS"
            duplicate_result["reasons"].append(f"exact duplicate of {original_id}")
            duplicate_result["checks"]["duplicate_clear"] = False
    for left_id, right_id, overlap in near_duplicates:
        for document_id in (left_id, right_id):
            duplicate_result = result_by_id.get(document_id)
            if duplicate_result:
                near_reason = (
                    f"possible same-source near-duplicate with "
                    f"{right_id if document_id == left_id else left_id} (token overlap {overlap})"
                )
                duplicate_result["checks"]["duplicate_clear"] = False
                if near_reason not in duplicate_result["reasons"]:
                    duplicate_result["reasons"].append(near_reason)
                if duplicate_result["decision"] == "APPROVED_BY_AUTOMATED_CHECKS":
                    duplicate_result["decision"] = "REVIEW_REQUIRED"
    automatic_fixes: list[dict] = []
    final_documents = [document.copy() for document in candidate_documents if document["id"] in legacy_ids]
    for document in new_documents:
        result = result_by_id[document["id"]]
        if result["decision"] != "APPROVED_BY_AUTOMATED_CHECKS":
            continue
        approved_document = document.copy()
        if result["page_published_at"] and result["page_published_at"] != document["published_at"]:
            automatic_fixes.append({
                "id": document["id"], "before": document["published_at"], "after": result["page_published_at"]
            })
            approved_document["published_at"] = result["page_published_at"]
        final_documents.append(approved_document)

    final_exact_duplicates, _ = duplicate_assessment(final_documents)
    if final_exact_duplicates:
        raise ValueError(f"final snapshot would violate normalized-content uniqueness: {final_exact_duplicates}")

    final_directory = Path(final_directory)
    if final_directory.exists():
        raise FileExistsError(f"refusing to overwrite existing snapshot: {final_directory}")
    final_directory.parent.mkdir(parents=True, exist_ok=True)
    final_documents.sort(key=lambda item: item["id"])
    raw = "".join(json.dumps(document, ensure_ascii=False, separators=(",", ":")) + "\n" for document in final_documents).encode("utf-8")
    digest = hashlib.sha256(raw).hexdigest()
    manifest = {
        "dataset_name": "hoaxlin-rag-knowledge-base",
        "version": "1.1.1",
        "schema_version": "1.0.0",
        "document_count": len(final_documents),
        "documents": {"path": "documents.jsonl", "sha256": digest},
        "provenance": {"source_type": "original_publication_url", "local_input_files": []},
    }
    report = build_review_report(
        candidate_documents, results, final_documents, exact_duplicates, near_duplicates,
        automatic_fixes, prior_overlap_flags,
    )
    with tempfile.TemporaryDirectory(prefix=".rag-kb-verify-", dir=final_directory.parent) as temporary:
        staging = Path(temporary) / final_directory.name
        staging.mkdir()
        (staging / "documents.jsonl").write_bytes(raw)
        (staging / "manifest.json").write_text(
            json.dumps(manifest, ensure_ascii=False, indent=2) + "\n", encoding="utf-8"
        )
        (staging / "review-report.md").write_text(report, encoding="utf-8", newline="\n")
        validate_knowledge_base(staging)
        staging.rename(final_directory)
    return {
        "candidate_count": len(candidate_documents),
        "new_candidate_count": len(new_documents),
        "results": results,
        "final_count": len(final_documents),
        "manifest": manifest,
        "source_status_counts": {
            status: sum(result["source_status"] == status for result in results)
            for status in ("SOURCE_VERIFIED", "SOURCE_UNREACHABLE", "SOURCE_CONTENT_UNAVAILABLE", "SOURCE_MISMATCH")
        },
        "content_status_counts": {
            status: sum(result["content_status"] == status for result in results)
            for status in ("CONTENT_SUPPORTED", "CONTENT_PARTIALLY_SUPPORTED", "CONTENT_UNSUPPORTED")
        },
        "decision_counts": {
            status: sum(result["decision"] == status for result in results)
            for status in ("APPROVED_BY_AUTOMATED_CHECKS", "EXCLUDED_BY_AUTOMATED_CHECKS", "REVIEW_REQUIRED")
        },
        "short_before": sum(result["word_count"] < 30 for result in results),
        "short_after": sum(len(document["content"].split()) < 30 for document in final_documents),
        "automatic_fixes": automatic_fixes,
        "exact_duplicates": exact_duplicates,
        "near_duplicates": near_duplicates,
    }


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--candidate", type=Path, default=DEFAULT_CANDIDATE)
    parser.add_argument("--output", type=Path, default=DEFAULT_FINAL)
    parser.add_argument("--workers", type=int, default=4)
    args = parser.parse_args()
    result = run_verification(args.candidate, args.output, args.workers)
    print(json.dumps({key: value for key, value in result.items() if key != "results"}, ensure_ascii=False, indent=2))
    print(f"Review report: {args.output / 'review-report.md'}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
