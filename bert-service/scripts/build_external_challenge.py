"""Collect an external, manually-reviewed challenge set for Hoaxlin.

This script only builds candidate CSV files. It does not train, evaluate, or
modify the active model. Every candidate has ``approved=0`` and must be
reviewed by a human before it becomes an evaluation record.

Install the collection-only dependencies and run from ``bert-service``::

    python -m pip install -e ".[collection]"
    python scripts/build_external_challenge.py

The collectors use only explicit verdicts for hoax records and official
first-party pages for valid records. A source that blocks collection or whose
markup cannot be parsed is reported as failed; the script never fabricates a
replacement record.
"""

from __future__ import annotations

import argparse
import csv
import json
import re
import sys
import time
import unicodedata
from collections import Counter, defaultdict
from dataclasses import asdict, dataclass, field
from datetime import date, datetime, timezone
from pathlib import Path
from typing import Any, Iterable
from urllib.parse import urljoin, urlparse

import httpx
from bs4 import BeautifulSoup

BERT_ROOT = Path(__file__).resolve().parents[1]
PROJECT_ROOT = BERT_ROOT.parent
if str(BERT_ROOT) not in sys.path:
    sys.path.insert(0, str(BERT_ROOT))

from dataset.normalize import clean_text, is_indonesian, normalize_key, tokenize_norm, word_shingles

USER_AGENT = (
    "HoaxlinExternalChallengeBuilder/1.0 "
    "(+academic research; candidates require manual review)"
)
DEFAULT_OUTPUT_DIR = PROJECT_ROOT / "datasets" / "challenge" / "external-challenge-v1"
DEFAULT_CORPUS = PROJECT_ROOT / "datasets" / "processed" / "komdigi-antara-v1" / "all.jsonl"
DEFAULT_CUTOFF = date(2026, 8, 20)
DEFAULT_TARGET_PER_SOURCE = 20
DEFAULT_SIMILARITY = 0.65

CSV_FIELDS = [
    "id",
    "text",
    "label",
    "text_source",
    "evidence_source",
    "evidence_url",
    "published_at",
    "topic",
    "verification_method",
    "notes",
    "approved",
]
REJECTED_FIELDS = CSV_FIELDS + ["rejection_reason", "similarity", "matched_id"]

SOURCE_ORDER = ["turnbackhoax", "cekfakta", "afp", "kemkes", "bank_indonesia", "bmkg"]
SOURCE_NAMES = {
    "turnbackhoax": "TurnBackHoax / MAFINDO",
    "cekfakta": "CekFakta",
    "afp": "AFP Fact Check Indonesia",
    "kemkes": "Kementerian Kesehatan RI",
    "bank_indonesia": "Bank Indonesia",
    "bmkg": "BMKG",
}
SOURCE_LABELS = {
    "turnbackhoax": "hoax",
    "cekfakta": "hoax",
    "afp": "hoax",
    "kemkes": "valid",
    "bank_indonesia": "valid",
    "bmkg": "valid",
}

VERDICT_PREFIX_RE = re.compile(
    r"^\s*(?:\[(?:salah|hoaks?|false|keliru|tidak benar|penipuan)\]\s*|"
    r"(?:cek fakta\s*:\s*)|(?:hoaks?|false|keliru|tidak benar|salah)\s*[:!,-]\s*)+",
    re.IGNORECASE,
)
VERDICT_LEAK_RE = re.compile(
    r"(?:\bfaktanya\b|\bhasil (?:cek fakta|penelusuran)\b|"
    r"\bberdasarkan penelusuran\b|\brating\s*:|\bkesimpulan\s*:|"
    r"\b(?:klaim|narasi|informasi)\s+(?:ini\s+)?(?:hoaks?|false|keliru|tidak benar|salah)\b|"
    r"\b(?:merupakan|adalah)\s+(?:hoaks?|false|keliru|tidak benar)\b|"
    r"^\s*(?:\[(?:salah|hoaks?|false|keliru|tidak benar)\]|"
    r"(?:salah|hoaks?|false|keliru|tidak benar)\s*[:!,-]))",
    re.IGNORECASE,
)
CLEAR_VERDICT_RE = re.compile(
    r"\b(?:salah|hoaks?|false|keliru|tidak benar|fabricated content|"
    r"baseless|unfounded|misleading|misrepresented)\b",
    re.IGNORECASE,
)
AUTOMATION_PROHIBITION_RE = re.compile(
    r"dilarang keras.*(?:crawling|pengindeksan otomatis).*\bAI\b",
    re.IGNORECASE | re.DOTALL,
)
FORECAST_RE = re.compile(
    r"\b(?:diprakirakan|diprediksi|diperkirakan|berpotensi|proyeksi|"
    r"diharapkan|akan diperkirakan|kemungkinan)\b",
    re.IGNORECASE,
)
BOILERPLATE_RE = re.compile(
    r"(?:adsbygoogle|baca juga|copyright|dilarang keras|pewarta\s*:|editor\s*:)",
    re.IGNORECASE,
)
AFP_EVIDENCE_RE = re.compile(
    r"\b(?:mengatakan kepada AFP|kepada AFP|para ahli|profesor|menurut pakar)\b",
    re.IGNORECASE,
)
AFP_NON_CLAIM_RE = re.compile(
    r"^(?:selamat pagi\b|semoga\b|mohon doanya\b)",
    re.IGNORECASE,
)
KEMKES_NONFACTUAL_RE = re.compile(
    r"\b(?:diperkirakan|diprediksi|berpotensi|diharapkan|akan terus|"
    r"mengajak|mengimbau|imbau|menyerukan|meminta masyarakat|"
    r"menargetkan|berharap|jangan)\b",
    re.IGNORECASE,
)


@dataclass(slots=True)
class Candidate:
    id: str
    text: str
    label: str
    text_source: str
    evidence_source: str
    evidence_url: str
    published_at: str
    topic: str
    verification_method: str
    notes: str
    approved: int = 0
    source_key: str = field(default="", repr=False)

    def csv_row(self) -> dict[str, Any]:
        return {name: getattr(self, name) for name in CSV_FIELDS}


@dataclass(slots=True)
class RejectedCandidate:
    candidate: Candidate
    rejection_reason: str
    similarity: float | None = None
    matched_id: str = ""

    def csv_row(self) -> dict[str, Any]:
        row = self.candidate.csv_row()
        row.update(
            {
                "rejection_reason": self.rejection_reason,
                "similarity": "" if self.similarity is None else f"{self.similarity:.6f}",
                "matched_id": self.matched_id,
            }
        )
        return row


@dataclass(slots=True)
class SourceStats:
    discovered: int = 0
    fetched: int = 0
    parsed: int = 0
    failures: list[dict[str, str]] = field(default_factory=list)

    def fail(self, url: str, reason: str) -> None:
        if len(self.failures) < 50:
            self.failures.append({"url": url, "reason": compact_error(reason)})


class CollectionError(RuntimeError):
    pass


class PoliteClient:
    def __init__(self, *, delay: float, timeout: float) -> None:
        self.delay = max(delay, 0.0)
        self.timeout = timeout
        self._last_request = 0.0
        self._client = httpx.Client(
            headers={
                "User-Agent": USER_AGENT,
                "Accept": "text/html,application/xhtml+xml,application/json;q=0.9,*/*;q=0.5",
                "Accept-Language": "id,en-US;q=0.8,en;q=0.7",
            },
            follow_redirects=True,
            timeout=timeout,
        )

    def __enter__(self) -> PoliteClient:
        return self

    def __exit__(self, *args: object) -> None:
        self._client.close()

    def request(self, method: str, url: str, **kwargs: Any) -> httpx.Response:
        elapsed = time.monotonic() - self._last_request
        if elapsed < self.delay:
            time.sleep(self.delay - elapsed)
        try:
            response = self._client.request(method, url, **kwargs)
        except httpx.HTTPError as exc:
            raise CollectionError(f"{type(exc).__name__}: {exc}") from exc
        finally:
            self._last_request = time.monotonic()

        response.encoding = "utf-8"
        if response.status_code >= 400:
            raise CollectionError(f"HTTP {response.status_code}")
        if "Just a moment" in response.text and "challenge-platform" in response.text:
            raise CollectionError("anti-bot challenge returned instead of source content")
        return response

    def get(self, url: str, **kwargs: Any) -> httpx.Response:
        return self.request("GET", url, **kwargs)

    def post(self, url: str, **kwargs: Any) -> httpx.Response:
        return self.request("POST", url, **kwargs)


def compact_error(value: str) -> str:
    return re.sub(r"\s+", " ", value).strip()[:500]


def normalized_text(value: str) -> str:
    value = unicodedata.normalize("NFKC", value or "")
    value = value.replace("\ufffd", " ")
    value = re.sub(r"\s+", " ", value).strip()
    return clean_text(value)


def strip_verdict_prefix(value: str) -> str:
    return normalized_text(VERDICT_PREFIX_RE.sub("", value or ""))


def contains_verdict_leak(value: str) -> bool:
    return bool(VERDICT_LEAK_RE.search(value))


def parse_date(value: str | None) -> date | None:
    if not value:
        return None
    cleaned = normalized_text(value)
    cleaned = re.sub(r"\s+(?:WIB|WITA|WIT)$", "", cleaned, flags=re.IGNORECASE)
    try:
        return datetime.fromisoformat(cleaned.replace("Z", "+00:00")).date()
    except ValueError:
        pass

    for fmt in (
        "%Y-%m-%d",
        "%d/%m/%Y",
        "%d.%m.%Y",
        "%m/%d/%Y %I:%M %p",
        "%m/%d/%Y",
        "%d %B %Y",
        "%B %d, %Y",
    ):
        try:
            return datetime.strptime(cleaned, fmt).date()
        except ValueError:
            continue

    month_names = {
        "januari": 1,
        "februari": 2,
        "maret": 3,
        "april": 4,
        "mei": 5,
        "juni": 6,
        "juli": 7,
        "agustus": 8,
        "september": 9,
        "oktober": 10,
        "november": 11,
        "desember": 12,
    }
    match = re.search(r"\b(\d{1,2})\s+([A-Za-z]+)\s+(\d{4})\b", cleaned)
    if match and match.group(2).lower() in month_names:
        return date(int(match.group(3)), month_names[match.group(2).lower()], int(match.group(1)))
    english_match = re.search(
        r"\b(\d{1,2}\s+[A-Za-z]+\s+\d{4}|[A-Za-z]+\s+\d{1,2},\s+\d{4})\b",
        cleaned,
    )
    if english_match:
        for fmt in ("%d %B %Y", "%B %d, %Y"):
            try:
                return datetime.strptime(english_match.group(1), fmt).date()
            except ValueError:
                continue
    return None


def iso_date(value: date | None) -> str:
    return value.isoformat() if value else ""


def split_sentences(value: str) -> list[str]:
    text = normalized_text(value)
    return [
        sentence.strip()
        for sentence in re.split(r"(?<=[.!?])\s+(?=[A-Z0-9À-ÖØ-Ý])", text)
        if sentence.strip()
    ]


def factual_text(title: str, body: str, *, max_sentences: int = 2) -> str:
    clean_title = normalized_text(title)
    selected: list[str] = []
    for sentence in split_sentences(body):
        sentence = re.sub(r"^[A-Za-z ]+,\s*\d{1,2}\s+[A-Za-z]+\s+\d{4}\s*[-–—]\s*", "", sentence)
        sentence = normalized_text(sentence)
        if len(sentence) < 45 or len(sentence) > 650:
            continue
        if BOILERPLATE_RE.search(sentence) or FORECAST_RE.search(sentence):
            continue
        if sentence.lower() == clean_title.lower():
            continue
        selected.append(sentence)
        if len(selected) >= max_sentences:
            break
    if not selected:
        return ""
    return normalized_text(". ".join([clean_title.rstrip(". "), *selected]))[:1600]


def infer_topic(*values: str) -> str:
    text = " ".join(values).lower()
    topics = [
        ("ekonomi", ("ekonomi", "inflasi", "ekspor", "impor", "rupiah", "bank", "devisa", "utang", "perdagangan")),
        ("politik", ("presiden", "menteri", "dpr", "pemilu", "pemerintah", "politik", "korupsi")),
        ("bencana_cuaca", ("gempa", "tsunami", "cuaca", "iklim", "hujan", "banjir", "gunung", "bmkg", "karhutla")),
        ("kesehatan", ("kesehatan", "vaksin", "virus", "penyakit", "obat", "rumah sakit", "hiv")),
        ("bantuan_sosial", ("bansos", "bantuan", "bpjs", "pkh", "sembako", "beras")),
        ("teknologi", ("teknologi", "digital", "internet", "ai", "kecerdasan buatan", "telepon", "aplikasi")),
        ("keamanan", ("polisi", "tni", "razia", "kejahatan", "penipuan", "perang")),
    ]
    for topic, keywords in topics:
        if any(keyword in text for keyword in keywords):
            return topic
    return "lainnya"


def json_ld_objects(soup: BeautifulSoup) -> Iterable[dict[str, Any]]:
    for script in soup.select('script[type="application/ld+json"]'):
        raw = script.string or script.get_text()
        try:
            value = json.loads(raw)
        except (TypeError, json.JSONDecodeError):
            continue
        values = value if isinstance(value, list) else [value]
        for item in values:
            if isinstance(item, dict) and isinstance(item.get("@graph"), list):
                for graph_item in item["@graph"]:
                    if isinstance(graph_item, dict):
                        yield graph_item
            elif isinstance(item, dict):
                yield item


def make_candidate(
    *,
    source_key: str,
    record_id: str,
    text: str,
    text_source: str,
    evidence_url: str,
    published_at: date | None,
    topic: str,
    notes: str,
) -> Candidate:
    return Candidate(
        id=f"{source_key}-{record_id}",
        text=normalized_text(text),
        label=SOURCE_LABELS[source_key],
        text_source=text_source,
        evidence_source=SOURCE_NAMES[source_key],
        evidence_url=evidence_url,
        published_at=iso_date(published_at),
        topic=topic,
        verification_method="fact_check" if SOURCE_LABELS[source_key] == "hoax" else "primary_source",
        notes=notes,
        approved=0,
        source_key=source_key,
    )


def collect_turnbackhoax(
    client: PoliteClient, stats: SourceStats, *, desired_pool: int, max_pages: int
) -> list[Candidate]:
    base = "https://turnbackhoax.id/articles"
    urls: list[str] = []
    for page in range(1, max_pages + 1):
        page_url = f"{base}?page={page}"
        try:
            response = client.get(page_url)
            soup = BeautifulSoup(response.text, "html.parser")
        except Exception as exc:  # noqa: BLE001
            stats.fail(page_url, str(exc))
            break
        found = []
        for anchor in soup.select('a[href*="/articles/"]'):
            url = urljoin(page_url, anchor.get("href", "")).split("#", 1)[0]
            slug = url.rstrip("/").rsplit("/", 1)[-1]
            if re.match(r"^\d+-", slug):
                found.append(url)
        for url in found:
            if url not in urls:
                urls.append(url)
        if not found or len(urls) >= desired_pool * 2:
            break

    stats.discovered = len(urls)
    candidates: list[Candidate] = []
    for url in urls:
        if len(candidates) >= desired_pool:
            break
        try:
            response = client.get(url)
            stats.fetched += 1
            soup = BeautifulSoup(response.text, "html.parser")
            review = next(
                (item for item in json_ld_objects(soup) if item.get("@type") == "ClaimReview"),
                None,
            )
            if not review:
                raise ValueError("missing ClaimReview structured data")
            rating = review.get("reviewRating") or {}
            verdict = normalized_text(str(rating.get("alternateName") or ""))
            rating_value = str(rating.get("ratingValue") or "")
            if not CLEAR_VERDICT_RE.search(verdict) and rating_value != "1":
                raise ValueError(f"verdict is not explicitly false: {verdict or 'missing'}")
            claim = strip_verdict_prefix(str(review.get("claimReviewed") or ""))
            if len(claim) < 25 or contains_verdict_leak(claim):
                raise ValueError("claim text is missing or contains verdict/evidence leakage")
            published = parse_date(
                str(((review.get("itemReviewed") or {}).get("datePublished") or ""))
            )
            record_id = re.match(r"(\d+)", url.rstrip("/").rsplit("/", 1)[-1])
            if not record_id:
                raise ValueError("article id is missing")
            candidates.append(
                make_candidate(
                    source_key="turnbackhoax",
                    record_id=record_id.group(1),
                    text=claim,
                    text_source="claim",
                    evidence_url=url,
                    published_at=published,
                    topic=infer_topic(claim),
                    notes=f"ClaimReview verdict={verdict or rating_value}; requires manual review",
                )
            )
            stats.parsed += 1
        except Exception as exc:  # noqa: BLE001
            stats.fail(url, str(exc))
    return candidates


def claim_from_cekfakta(row: dict[str, Any]) -> str:
    fact = normalized_text(str(row.get("fact") or ""))
    match = re.search(r"\bKlaim\s*:\s*(.+?)(?:\s+Rating\s*:|$)", fact, re.IGNORECASE)
    if match:
        claim = normalized_text(match.group(1))
        if claim:
            return claim
    title = strip_verdict_prefix(str(row.get("title") or ""))
    return re.sub(r"^(?:klaim\s+bahwa\s+)", "", title, flags=re.IGNORECASE).strip()


def collect_cekfakta(
    client: PoliteClient, stats: SourceStats, *, desired_pool: int
) -> list[Candidate]:
    api_url = "https://cekfakta.com/api-index"
    try:
        response = client.get(api_url, headers={"Accept": "application/json"})
        rows = response.json()
        if not isinstance(rows, list):
            raise ValueError("API response is not a list")
    except Exception as exc:  # noqa: BLE001
        stats.fail(api_url, str(exc))
        return []

    stats.discovered = len(rows)
    rows.sort(key=lambda row: str(row.get("tanggal") or ""), reverse=True)
    candidates: list[Candidate] = []
    for row in rows:
        if len(candidates) >= desired_pool:
            break
        row_id = str(row.get("id") or "")
        url = f"https://cekfakta.com/focus/{row_id}" if row_id else api_url
        try:
            stats.fetched += 1
            status = normalized_text(str(row.get("status") or ""))
            verdict_material = " ".join(
                str(row.get(field) or "") for field in ("status", "fact", "conclusion")
            )
            if status.lower() != "salah" or not CLEAR_VERDICT_RE.search(verdict_material):
                raise ValueError("record does not have an explicit false verdict")
            if AUTOMATION_PROHIBITION_RE.search(verdict_material):
                raise ValueError("publisher text explicitly prohibits automated AI collection")
            claim = claim_from_cekfakta(row)
            if len(claim) < 25 or contains_verdict_leak(claim):
                raise ValueError("claim text is missing or contains verdict/evidence leakage")
            if not row_id:
                raise ValueError("record id is missing")
            published = parse_date(str(row.get("tanggal") or ""))
            candidates.append(
                make_candidate(
                    source_key="cekfakta",
                    record_id=row_id,
                    text=claim,
                    text_source="claim",
                    evidence_url=url,
                    published_at=published,
                    topic=infer_topic(claim, str(row.get("tags") or "")),
                    notes="CekFakta API status=Salah; requires manual review",
                )
            )
            stats.parsed += 1
        except Exception as exc:  # noqa: BLE001
            stats.fail(url, str(exc))
    return candidates


def extract_afp_claim(soup: BeautifulSoup) -> str:
    # Use escapes so smart-quote matching is independent of source-file encoding.
    quote_re = re.compile(r'\u201c([^\u201d]{25,700})\u201d|"([^"\r\n]{25,700})"')
    options: list[str] = []
    for paragraph in soup.select("article p, main p"):
        text = normalized_text(paragraph.get_text(" ", strip=True))
        for match in quote_re.finditer(text):
            claim = normalized_text(match.group(1) or match.group(2))
            if (
                not contains_verdict_leak(claim)
                and not AFP_EVIDENCE_RE.search(claim)
                and not AFP_NON_CLAIM_RE.search(claim)
                and is_indonesian(claim)
            ):
                options.append(claim)
    return options[0] if options else ""


def collect_afp(
    client: PoliteClient, stats: SourceStats, *, desired_pool: int, max_pages: int
) -> list[Candidate]:
    listing_base = "https://periksafakta.afp.com/"
    urls: list[str] = []
    for page in range(max_pages):
        listing_url = listing_base if page == 0 else f"{listing_base}?page={page}"
        try:
            response = client.get(listing_url)
            soup = BeautifulSoup(response.text, "html.parser")
        except Exception as exc:  # noqa: BLE001
            stats.fail(listing_url, str(exc))
            break
        found = [
            urljoin(listing_url, anchor.get("href", "")).split("#", 1)[0]
            for anchor in soup.select('a[href*="/doc.afp.com."]')
        ]
        for url in found:
            if url not in urls:
                urls.append(url)
        if not found or len(urls) >= desired_pool * 2:
            break

    stats.discovered = len(urls)
    candidates: list[Candidate] = []
    for url in urls:
        if len(candidates) >= desired_pool:
            break
        try:
            response = client.get(url, headers={"Referer": listing_base})
            stats.fetched += 1
            soup = BeautifulSoup(response.text, "html.parser")
            title_el = soup.select_one("h1")
            title = normalized_text(title_el.get_text(" ", strip=True) if title_el else "")
            body = normalized_text(" ".join(p.get_text(" ", strip=True) for p in soup.select("article p, main p")))
            if not CLEAR_VERDICT_RE.search(f"{title} {body}"):
                raise ValueError("article does not state a clear false verdict")
            claim = extract_afp_claim(soup)
            if len(claim) < 25 or contains_verdict_leak(claim):
                raise ValueError("Indonesian claim cannot be separated safely from verdict/evidence")
            date_meta = soup.select_one(
                'meta[property="article:published_time"], meta[name="date"], time[datetime]'
            )
            raw_date = ""
            if date_meta:
                raw_date = date_meta.get("content") or date_meta.get("datetime") or date_meta.get_text(" ", strip=True)
            if not raw_date:
                raw_date = next(
                    (
                        str(meta.get("content"))
                        for meta in soup.select("meta[content]")
                        if re.fullmatch(r"\d{4}-\d{2}-\d{2}T[^\s]+", str(meta.get("content")))
                    ),
                    "",
                )
            if not raw_date:
                match = re.search(r"Published on ([A-Za-z]+ \d{1,2}, \d{4})", body)
                raw_date = match.group(1) if match else ""
            record_id = url.rstrip("/").rsplit("/", 1)[-1].replace("doc.afp.com.", "")
            candidates.append(
                make_candidate(
                    source_key="afp",
                    record_id=record_id,
                    text=claim,
                    text_source="claim",
                    evidence_url=url,
                    published_at=parse_date(raw_date),
                    topic=infer_topic(title, claim),
                    notes="AFP Periksa Fakta explicit false verdict; requires manual review",
                )
            )
            stats.parsed += 1
        except Exception as exc:  # noqa: BLE001
            stats.fail(url, str(exc))
    return candidates


def kemkes_fact_text(paragraphs: Iterable[str]) -> str:
    selected: list[str] = []
    for paragraph in paragraphs:
        paragraph = normalized_text(paragraph)
        paragraph = re.sub(
            r"^(?:Jakarta|Indonesia),?\s+\d{1,2}\s+[A-Za-z]+\s+\d{4}\s+",
            "",
            paragraph,
            flags=re.IGNORECASE,
        )
        if not paragraph or re.fullmatch(r"(?:Jakarta|Indonesia),?\s+\d{1,2}\s+\w+\s+\d{4}", paragraph):
            continue
        for sentence in re.split(r"(?<=[.!?])\s+", paragraph):
            sentence = normalized_text(sentence)
            if len(sentence) < 45 or len(sentence) > 650:
                continue
            if BOILERPLATE_RE.search(sentence) or FORECAST_RE.search(sentence):
                continue
            if KEMKES_NONFACTUAL_RE.search(sentence) and not re.search(
                r"\d|%|tercatat|ditemukan|mengalami|berjumlah|sebanyak|mencapai|menewaskan|terjadi|dilakukan",
                sentence,
                re.IGNORECASE,
            ):
                continue
            selected.append(sentence)
            if len(selected) >= 2:
                break
        if len(selected) >= 2:
            break
    return normalized_text(". ".join(selected))[:1600]


def collect_kemkes(
    client: PoliteClient, stats: SourceStats, *, desired_pool: int, max_pages: int
) -> list[Candidate]:
    category_base = "https://kemkes.go.id/id/category/rilis-berita"
    article_urls: list[tuple[str, date | None]] = []
    seen_urls: set[str] = set()
    for page in range(0, max_pages):
        listing_url = category_base if page == 0 else f"{category_base}/{page + 1}"
        try:
            response = client.get(listing_url, headers={"Referer": "https://kemkes.go.id/id/home"})
            soup = BeautifulSoup(response.text, "html.parser")
        except Exception as exc:  # noqa: BLE001
            stats.fail(listing_url, str(exc))
            continue
        found = []
        for anchor in soup.select("a.link"):
            href = str(anchor.get("href") or "")
            if not href.startswith("/id/") or "/category/" in href:
                continue
            url = urljoin(listing_url, href).split("#", 1)[0]
            if url in seen_urls:
                continue
            seen_urls.add(url)
            found.append((url, parse_date(anchor.get_text(" ", strip=True))))
        article_urls.extend(found)
    stats.discovered = len(article_urls)
    article_urls.sort(key=lambda item: (item[1] is not None, item[1] or date.min), reverse=True)

    candidates: list[Candidate] = []
    for url, listing_date in article_urls:
        if len(candidates) >= desired_pool:
            break
        try:
            response = client.get(url, headers={"Referer": category_base})
            stats.fetched += 1
            soup = BeautifulSoup(response.text, "html.parser")
            headings = soup.find_all("h1")
            title = normalized_text(headings[-1].get_text(" ", strip=True) if headings else "")
            content = soup.select_one(".content-wrapper")
            paragraphs = [p.get_text(" ", strip=True) for p in content.find_all("p")] if content else []
            times = [parse_date(str(time.get("datetime") or time.get_text(" ", strip=True))) for time in soup.select("time")]
            published = next((value for value in times if value), listing_date)
            text = kemkes_fact_text(paragraphs)
            if not title or not text or not published:
                raise ValueError("missing factual statement or publication date")
            record_id = url.rstrip("/").rsplit("/", 1)[-1]
            candidates.append(
                make_candidate(
                    source_key="kemkes",
                    record_id=record_id,
                    text=text,
                    text_source="primary_factual_statement",
                    evidence_url=url,
                    published_at=published,
                    topic=infer_topic(title, text),
                    notes="Official Kementerian Kesehatan RI release; requires manual review",
                )
            )
            stats.parsed += 1
        except Exception as exc:  # noqa: BLE001
            stats.fail(url, str(exc))
    return candidates


def bi_article_links(soup: BeautifulSoup, base_url: str) -> list[str]:
    return list(
        dict.fromkeys(
            urljoin(base_url, anchor.get("href", "")).split("#", 1)[0]
            for anchor in soup.select('a[href*="/news-release/Pages/"]')
            if str(anchor.get("href", "")).lower().endswith(".aspx")
        )
    )


def aspnet_hidden_fields(soup: BeautifulSoup) -> dict[str, str]:
    return {
        str(field.get("name")): str(field.get("value") or "")
        for field in soup.select('form input[type="hidden"][name]')
    }


def collect_bi_urls(
    client: PoliteClient, stats: SourceStats, *, desired_pool: int, max_pages: int
) -> list[str]:
    listing_url = "https://www.bi.go.id/id/publikasi/ruang-media/news-release/Default.aspx"
    try:
        response = client.get(listing_url)
        soup = BeautifulSoup(response.text, "html.parser")
    except Exception as exc:  # noqa: BLE001
        stats.fail(listing_url, str(exc))
        return []

    urls = bi_article_links(soup, listing_url)
    current_page = 1
    while current_page < max_pages and len(urls) < desired_pool:
        next_page = current_page + 1
        page_link = next(
            (
                anchor
                for anchor in soup.select('a.pagination-list[href*="__doPostBack"]')
                if anchor.get_text(" ", strip=True) == str(next_page)
            ),
            None,
        )
        if page_link is None:
            break
        match = re.search(r"__doPostBack\('([^']+)'", str(page_link.get("href") or ""))
        if not match:
            stats.fail(listing_url, f"pagination target for page {next_page} is malformed")
            break
        form = aspnet_hidden_fields(soup)
        form["__EVENTTARGET"] = match.group(1)
        form["__EVENTARGUMENT"] = ""
        try:
            response = client.post(listing_url, data=form)
            soup = BeautifulSoup(response.text, "html.parser")
        except Exception as exc:  # noqa: BLE001
            stats.fail(f"{listing_url} page {next_page}", str(exc))
            break
        urls.extend(url for url in bi_article_links(soup, listing_url) if url not in urls)
        current_page = next_page
    return urls


def collect_bank_indonesia(
    client: PoliteClient, stats: SourceStats, *, desired_pool: int, max_pages: int
) -> list[Candidate]:
    urls = collect_bi_urls(client, stats, desired_pool=desired_pool * 2, max_pages=max_pages)
    stats.discovered = len(urls)
    candidates: list[Candidate] = []
    for url in urls:
        if len(candidates) >= desired_pool:
            break
        try:
            response = client.get(url)
            stats.fetched += 1
            soup = BeautifulSoup(response.text, "html.parser")
            title_el = soup.select_one("#layout-title") or soup.select_one("title")
            title = normalized_text(title_el.get_text(" ", strip=True) if title_el else "")
            body_nodes = soup.select(".ms-rtestate-field")
            body_node = max(body_nodes, key=lambda node: len(node.get_text(" ", strip=True)), default=None)
            body = body_node.get_text(" ", strip=True) if body_node else ""
            date_el = soup.select_one("#layout-date")
            published = parse_date(date_el.get_text(" ", strip=True) if date_el else "")
            text = factual_text(title, body)
            if not text or not published:
                raise ValueError("missing factual article text or publication date")
            record_id = Path(urlparse(url).path).stem
            candidates.append(
                make_candidate(
                    source_key="bank_indonesia",
                    record_id=record_id,
                    text=text,
                    text_source="headline_and_primary_statement",
                    evidence_url=url,
                    published_at=published,
                    topic=infer_topic(text),
                    notes="Official Bank Indonesia release; requires manual review",
                )
            )
            stats.parsed += 1
        except Exception as exc:  # noqa: BLE001
            stats.fail(url, str(exc))
    return candidates


def collect_bmkg(
    client: PoliteClient, stats: SourceStats, *, desired_pool: int
) -> list[Candidate]:
    category_urls = [
        "https://www.bmkg.go.id/berita/utama",
        "https://www.bmkg.go.id/berita/kegiatan",
        "https://www.bmkg.go.id/berita/daerah",
        "https://www.bmkg.go.id/berita/kegiatan-internasional",
    ]
    article_urls: list[str] = []
    category_paths = {urlparse(url).path.lower().rstrip("/") for url in category_urls}
    for category_url in category_urls:
        try:
            response = client.get(category_url)
            soup = BeautifulSoup(response.text, "html.parser")
        except Exception as exc:  # noqa: BLE001
            stats.fail(category_url, str(exc))
            continue
        for anchor in soup.select('a[href*="/berita/"]'):
            url = urljoin(category_url, anchor.get("href", "")).split("#", 1)[0]
            if urlparse(url).path.lower().rstrip("/") in category_paths:
                continue
            if url not in article_urls:
                article_urls.append(url)

    stats.discovered = len(article_urls)
    candidates: list[Candidate] = []
    for url in article_urls:
        if len(candidates) >= desired_pool:
            break
        try:
            response = client.get(url)
            stats.fetched += 1
            soup = BeautifulSoup(response.text, "html.parser")
            title_el = soup.select_one("h1")
            body_el = soup.select_one(".prose")
            if not title_el or not body_el:
                raise ValueError("article title/body markup not found")
            title = normalized_text(title_el.get_text(" ", strip=True))
            body = body_el.get_text(" ", strip=True)
            text = factual_text(title, body)
            main_text = soup.select_one("main")
            published = parse_date(main_text.get_text(" ", strip=True) if main_text else "")
            if not text or not published:
                raise ValueError("missing factual article text or publication date")
            record_id = urlparse(url).path.rstrip("/").rsplit("/", 1)[-1]
            candidates.append(
                make_candidate(
                    source_key="bmkg",
                    record_id=record_id,
                    text=text,
                    text_source="headline_and_primary_statement",
                    evidence_url=url,
                    published_at=published,
                    topic=infer_topic(text),
                    notes="Official BMKG article; requires manual review",
                )
            )
            stats.parsed += 1
        except Exception as exc:  # noqa: BLE001
            stats.fail(url, str(exc))
    return candidates


def shingle_set(text: str) -> set[tuple[str, ...]]:
    return set(word_shingles(tokenize_norm(text), size=3))


class CorpusSimilarityIndex:
    def __init__(self, records: list[tuple[str, str]]) -> None:
        self.ids: list[str] = []
        self.shingles: list[set[tuple[str, ...]]] = []
        self.inverted: dict[tuple[str, ...], list[int]] = defaultdict(list)
        for record_id, text in records:
            index = len(self.ids)
            shingles = shingle_set(text)
            self.ids.append(record_id)
            self.shingles.append(shingles)
            for shingle in shingles:
                self.inverted[shingle].append(index)

    def closest(self, text: str) -> tuple[float, str]:
        query = shingle_set(text)
        intersections: Counter[int] = Counter()
        for shingle in query:
            intersections.update(self.inverted.get(shingle, ()))
        best_similarity = 0.0
        best_id = ""
        for index, intersection in intersections.items():
            union = len(query) + len(self.shingles[index]) - intersection
            similarity = intersection / union if union else 0.0
            if similarity > best_similarity:
                best_similarity = similarity
                best_id = self.ids[index]
        return best_similarity, best_id


def load_training_corpus(path: Path) -> list[tuple[str, str]]:
    if not path.is_file():
        raise FileNotFoundError(f"training corpus not found: {path}")
    records: list[tuple[str, str]] = []
    with path.open("r", encoding="utf-8") as handle:
        for line_number, line in enumerate(handle, start=1):
            if not line.strip():
                continue
            try:
                row = json.loads(line)
            except json.JSONDecodeError as exc:
                raise ValueError(f"invalid JSONL at {path}:{line_number}") from exc
            text = normalized_text(str(row.get("text") or ""))
            if text:
                records.append((str(row.get("record_id") or f"line:{line_number}"), text))
    return records


def candidate_sort_key(candidate: Candidate, cutoff: date) -> tuple[int, str, int, str]:
    published = parse_date(candidate.published_at)
    preferred = int(bool(published and published >= cutoff))
    source_rank = -SOURCE_ORDER.index(candidate.source_key)
    return preferred, candidate.published_at, source_rank, candidate.id


def exact_deduplicate(
    candidates: list[Candidate], cutoff: date
) -> tuple[list[Candidate], list[RejectedCandidate]]:
    seen: dict[str, str] = {}
    kept: list[Candidate] = []
    rejected: list[RejectedCandidate] = []
    for candidate in sorted(candidates, key=lambda item: candidate_sort_key(item, cutoff), reverse=True):
        key = normalize_key(candidate.text)
        if not key:
            rejected.append(RejectedCandidate(candidate, "empty_after_normalization"))
        elif key in seen:
            rejected.append(RejectedCandidate(candidate, "exact_duplicate", 1.0, seen[key]))
        else:
            seen[key] = candidate.id
            kept.append(candidate)
    return kept, rejected


def similarity(left: str, right: str) -> float:
    left_set = shingle_set(left)
    right_set = shingle_set(right)
    if not left_set or not right_set:
        return 0.0
    return len(left_set & right_set) / len(left_set | right_set)


def select_candidates(
    candidates: list[Candidate],
    corpus: CorpusSimilarityIndex,
    *,
    threshold: float,
    target_per_source: int,
    cutoff: date,
) -> tuple[list[Candidate], list[RejectedCandidate]]:
    eligible: list[Candidate] = []
    rejected: list[RejectedCandidate] = []
    for candidate in candidates:
        score, match_id = corpus.closest(candidate.text)
        if score >= threshold:
            rejected.append(
                RejectedCandidate(candidate, "near_duplicate_training", score, match_id)
            )
        else:
            eligible.append(candidate)

    by_source: dict[str, list[Candidate]] = defaultdict(list)
    for candidate in eligible:
        by_source[candidate.source_key].append(candidate)
    for source_candidates in by_source.values():
        source_candidates.sort(
            key=lambda item: candidate_sort_key(item, cutoff), reverse=True
        )

    selected: list[Candidate] = []
    for source_key in SOURCE_ORDER:
        source_count = 0
        for candidate in by_source.get(source_key, []):
            if source_count >= target_per_source:
                rejected.append(RejectedCandidate(candidate, "source_quota_exceeded"))
                continue
            closest_score = 0.0
            closest_id = ""
            for existing in selected:
                score = similarity(candidate.text, existing.text)
                if score > closest_score:
                    closest_score = score
                    closest_id = existing.id
            if closest_score >= threshold:
                rejected.append(
                    RejectedCandidate(
                        candidate,
                        "near_duplicate_challenge",
                        closest_score,
                        closest_id,
                    )
                )
                continue
            selected.append(candidate)
            source_count += 1

    selected.sort(
        key=lambda item: (
            SOURCE_ORDER.index(item.source_key),
            item.published_at,
            item.id,
        ),
        reverse=False,
    )
    return selected, rejected


def write_csv(path: Path, rows: Iterable[dict[str, Any]], fields: list[str]) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    with path.open("w", encoding="utf-8", newline="") as handle:
        writer = csv.DictWriter(handle, fieldnames=fields, extrasaction="ignore")
        writer.writeheader()
        writer.writerows(rows)


SOURCE_KEYS_BY_NAME = {name: key for key, name in SOURCE_NAMES.items()}


def candidate_from_csv_row(row: dict[str, str]) -> Candidate:
    source_key = SOURCE_KEYS_BY_NAME.get(row.get("evidence_source", ""))
    if source_key is None:
        raise ValueError(f"unknown evidence source: {row.get('evidence_source', '')}")
    return Candidate(
        id=row["id"],
        text=row["text"],
        label=row["label"],
        text_source=row["text_source"],
        evidence_source=row["evidence_source"],
        evidence_url=row["evidence_url"],
        published_at=row["published_at"],
        topic=row["topic"],
        verification_method=row["verification_method"],
        notes=row["notes"],
        approved=int(row.get("approved") or 0),
        source_key=source_key,
    )


def rejected_from_csv_row(row: dict[str, str]) -> RejectedCandidate:
    similarity_value = row.get("similarity") or ""
    candidate = candidate_from_csv_row(row)
    return RejectedCandidate(
        candidate=candidate,
        rejection_reason=row.get("rejection_reason", "legacy_rejection"),
        similarity=float(similarity_value) if similarity_value else None,
        matched_id=row.get("matched_id", ""),
    )


def load_previous_source_stats(report_path: Path, stats: dict[str, SourceStats]) -> None:
    if not report_path.is_file():
        return
    try:
        report = json.loads(report_path.read_text(encoding="utf-8"))
    except (OSError, json.JSONDecodeError):
        return
    for source_key, values in (report.get("sources") or {}).items():
        if source_key not in stats or not isinstance(values, dict):
            continue
        stats[source_key] = SourceStats(
            discovered=int(values.get("discovered") or 0),
            fetched=int(values.get("fetched") or 0),
            parsed=int(values.get("parsed") or 0),
            failures=list(values.get("failures") or []),
        )


def append_kemkes_candidates(
    existing: list[Candidate],
    additions: list[Candidate],
    corpus: CorpusSimilarityIndex,
    previous_rejected: list[RejectedCandidate],
    *,
    threshold: float,
    target_per_source: int,
    cutoff: date,
) -> tuple[list[Candidate], list[RejectedCandidate]]:
    rejected: list[RejectedCandidate] = list(previous_rejected)
    known_keys = {normalize_key(candidate.text) for candidate in existing}
    existing_ids = {candidate.id for candidate in existing}
    accepted = list(existing)
    source_count = Counter(candidate.source_key for candidate in existing)
    for candidate in sorted(additions, key=lambda item: candidate_sort_key(item, cutoff), reverse=True):
        if candidate.id in existing_ids:
            rejected.append(RejectedCandidate(candidate, "exact_duplicate"))
            continue
        key = normalize_key(candidate.text)
        if not key or key in known_keys:
            rejected.append(RejectedCandidate(candidate, "exact_duplicate"))
            continue
        score, matched_id = corpus.closest(candidate.text)
        if score >= threshold:
            rejected.append(RejectedCandidate(candidate, "near_duplicate_training", score, matched_id))
            continue
        if source_count[candidate.source_key] >= target_per_source:
            rejected.append(RejectedCandidate(candidate, "source_quota_exceeded"))
            continue
        closest_score = 0.0
        closest_id = ""
        for current in accepted:
            current_score = similarity(candidate.text, current.text)
            if current_score > closest_score:
                closest_score = current_score
                closest_id = current.id
        if closest_score >= threshold:
            rejected.append(RejectedCandidate(candidate, "near_duplicate_challenge", closest_score, closest_id))
            continue
        accepted.append(candidate)
        existing_ids.add(candidate.id)
        known_keys.add(key)
        source_count[candidate.source_key] += 1
    return accepted, rejected


def build_report(
    *,
    candidates: list[Candidate],
    rejected: list[RejectedCandidate],
    stats: dict[str, SourceStats],
    corpus_path: Path,
    corpus_records: int,
    threshold: float,
    cutoff: date,
    target_per_source: int,
) -> dict[str, Any]:
    by_source = Counter(candidate.source_key for candidate in candidates)
    by_label = Counter(candidate.label for candidate in candidates)
    rejection_reasons = Counter(item.rejection_reason for item in rejected)
    target_by_source = {source: target_per_source for source in SOURCE_ORDER}
    gaps = {
        source: max(0, target_per_source - by_source.get(source, 0))
        for source in SOURCE_ORDER
    }
    source_reports: dict[str, Any] = {}
    for source in SOURCE_ORDER:
        source_report = asdict(stats[source])
        source_report["name"] = SOURCE_NAMES[source]
        source_report["label"] = SOURCE_LABELS[source]
        source_report["candidates_kept"] = by_source.get(source, 0)
        source_report["target"] = target_per_source
        source_report["gap"] = gaps[source]
        source_reports[source] = source_report

    total_target = target_per_source * len(SOURCE_ORDER)
    return {
        "schema_version": "1.0.0",
        "generated_at": datetime.now(timezone.utc).isoformat(),
        "ground_truth_policy": "explicit fact-check verdict or first-party primary source; no LLM",
        "manual_review_required": True,
        "approved_default": 0,
        "cutoff_priority": cutoff.isoformat(),
        "target": {
            "total": total_target,
            "by_source": target_by_source,
            "by_label": {"hoax": target_per_source * 3, "valid": target_per_source * 3},
        },
        "result": {
            "total_candidates": len(candidates),
            "total_rejected": len(rejected),
            "by_source": dict(by_source),
            "by_label": dict(by_label),
            "gaps_by_source": gaps,
            "target_met": len(candidates) == total_target and not any(gaps.values()),
            "rejections_by_reason": dict(rejection_reasons),
        },
        "deduplication": {
            "normalization": "dataset.normalize.normalize_key",
            "shingling": "word trigram",
            "metric": "Jaccard",
            "threshold": threshold,
            "training_corpus": str(corpus_path),
            "training_records": corpus_records,
        },
        "sources": source_reports,
    }


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--output-dir", type=Path, default=DEFAULT_OUTPUT_DIR)
    parser.add_argument("--corpus", type=Path, default=DEFAULT_CORPUS)
    parser.add_argument("--target-per-source", type=int, default=DEFAULT_TARGET_PER_SOURCE)
    parser.add_argument("--cutoff", type=date.fromisoformat, default=DEFAULT_CUTOFF)
    parser.add_argument("--similarity-threshold", type=float, default=DEFAULT_SIMILARITY)
    parser.add_argument("--delay", type=float, default=0.5, help="minimum seconds between HTTP requests")
    parser.add_argument("--timeout", type=float, default=30.0)
    parser.add_argument("--max-pages", type=int, default=8)
    parser.add_argument(
        "--append-kemkes",
        action="store_true",
        help="preserve existing candidates.csv and collect only Kementerian Kesehatan RI",
    )
    return parser.parse_args()


def run_append_kemkes(
    args: argparse.Namespace, training_records: list[tuple[str, str]], corpus_index: CorpusSimilarityIndex
) -> int:
    output_dir = args.output_dir.resolve()
    candidates_path = output_dir / "candidates.csv"
    rejected_path = output_dir / "rejected.csv"
    report_path = output_dir / "collection_report.json"
    if not candidates_path.is_file():
        raise SystemExit(f"existing candidates.csv not found: {candidates_path}")

    with candidates_path.open("r", encoding="utf-8-sig", newline="") as handle:
        existing_rows = list(csv.DictReader(handle))
    if len(existing_rows) not in (100, 120):
        raise SystemExit(
            f"--append-kemkes requires the existing 100 candidates or a 120-record Kemenkes refresh; found {len(existing_rows)}"
        )
    refreshing_existing_kemkes = len(existing_rows) == 120
    existing = [
        candidate_from_csv_row(row)
        for row in existing_rows
        if row.get("evidence_source") != SOURCE_NAMES["kemkes"]
    ]
    previous_rejected: list[RejectedCandidate] = []
    if rejected_path.is_file():
        with rejected_path.open("r", encoding="utf-8-sig", newline="") as handle:
            previous_rejected = [
                rejected_from_csv_row(row)
                for row in csv.DictReader(handle)
                if row.get("evidence_source") != SOURCE_NAMES["kemkes"]
            ]

    stats = {source: SourceStats() for source in SOURCE_ORDER}
    load_previous_source_stats(report_path, stats)
    if refreshing_existing_kemkes:
        additions = [
            candidate_from_csv_row(row)
            for row in existing_rows
            if row.get("evidence_source") == SOURCE_NAMES["kemkes"]
        ]
        stats["kemkes"] = SourceStats(
            discovered=len(additions),
            fetched=len(additions),
            parsed=len(additions),
        )
        print(
            f"[challenge] reusing existing Kementerian Kesehatan RI candidates: {len(additions)}",
            flush=True,
        )
    else:
        desired_pool = args.target_per_source + 10
        with PoliteClient(delay=args.delay, timeout=args.timeout) as client:
            print("[challenge] collecting Kementerian Kesehatan RI...", flush=True)
            kemkes_stats = stats["kemkes"]
            additions = collect_kemkes(
                client,
                kemkes_stats,
                desired_pool=desired_pool,
                max_pages=args.max_pages,
            )
        print(
            f"[challenge] Kementerian Kesehatan RI: discovered={stats['kemkes'].discovered} "
            f"parsed={len(additions)} failures={len(stats['kemkes'].failures)}",
            flush=True,
        )

    normalized_additions: list[Candidate] = []
    new_rejections: list[RejectedCandidate] = []
    for candidate in additions:
        candidate.text = normalized_text(candidate.text)
        if len(candidate.text) < 25:
            new_rejections.append(RejectedCandidate(candidate, "text_too_short_after_normalization"))
        else:
            normalized_additions.append(candidate)
    selected, append_rejections = append_kemkes_candidates(
        existing,
        normalized_additions,
        corpus_index,
        previous_rejected,
        threshold=args.similarity_threshold,
        target_per_source=args.target_per_source,
        cutoff=args.cutoff,
    )
    rejected = new_rejections + append_rejections
    rejected.sort(key=lambda item: (item.rejection_reason, item.candidate.id))
    write_csv(candidates_path, (candidate.csv_row() for candidate in selected), CSV_FIELDS)
    write_csv(rejected_path, (item.csv_row() for item in rejected), REJECTED_FIELDS)
    report = build_report(
        candidates=selected,
        rejected=rejected,
        stats=stats,
        corpus_path=args.corpus.resolve(),
        corpus_records=len(training_records),
        threshold=args.similarity_threshold,
        cutoff=args.cutoff,
        target_per_source=args.target_per_source,
    )
    report_path.write_text(
        json.dumps(report, ensure_ascii=False, indent=2) + "\n", encoding="utf-8"
    )
    print("[challenge] final candidates by source:", flush=True)
    for source_key in SOURCE_ORDER:
        count = report["result"]["by_source"].get(source_key, 0)
        print(f"  - {SOURCE_NAMES[source_key]}: {count}/{args.target_per_source}", flush=True)
    print(
        f"[challenge] candidates={len(selected)} rejected={len(rejected)} "
        f"target_met={report['result']['target_met']}",
        flush=True,
    )
    print(f"[challenge] wrote {candidates_path}", flush=True)
    print(f"[challenge] wrote {rejected_path}", flush=True)
    print(f"[challenge] wrote {report_path}", flush=True)
    return 0


def main() -> int:
    args = parse_args()
    if args.target_per_source < 1:
        raise SystemExit("--target-per-source must be positive")
    if not 0.0 < args.similarity_threshold <= 1.0:
        raise SystemExit("--similarity-threshold must be within (0, 1]")

    stats = {source: SourceStats() for source in SOURCE_ORDER}
    desired_pool = max(args.target_per_source * 3, args.target_per_source)

    print(f"[challenge] loading training corpus: {args.corpus}", flush=True)
    training_records = load_training_corpus(args.corpus)
    corpus_index = CorpusSimilarityIndex(training_records)
    print(f"[challenge] indexed {len(training_records)} training records", flush=True)

    if args.append_kemkes:
        return run_append_kemkes(args, training_records, corpus_index)

    with PoliteClient(delay=args.delay, timeout=args.timeout) as client:
        collectors = [
            (
                "turnbackhoax",
                lambda: collect_turnbackhoax(
                    client,
                    stats["turnbackhoax"],
                    desired_pool=desired_pool,
                    max_pages=args.max_pages,
                ),
            ),
            (
                "cekfakta",
                lambda: collect_cekfakta(
                    client, stats["cekfakta"], desired_pool=desired_pool
                ),
            ),
            (
                "afp",
                lambda: collect_afp(
                    client,
                    stats["afp"],
                    desired_pool=desired_pool,
                    max_pages=args.max_pages,
                ),
            ),
            (
                "kemkes",
                lambda: collect_kemkes(
                    client,
                    stats["kemkes"],
                    desired_pool=desired_pool,
                    max_pages=args.max_pages,
                ),
            ),
            (
                "bank_indonesia",
                lambda: collect_bank_indonesia(
                    client,
                    stats["bank_indonesia"],
                    desired_pool=desired_pool,
                    max_pages=args.max_pages,
                ),
            ),
            (
                "bmkg",
                lambda: collect_bmkg(client, stats["bmkg"], desired_pool=desired_pool),
            ),
        ]
        raw_candidates: list[Candidate] = []
        for source_key, collector in collectors:
            print(f"[challenge] collecting {SOURCE_NAMES[source_key]}...", flush=True)
            try:
                collected = collector()
            except Exception as exc:  # noqa: BLE001
                stats[source_key].fail("collector", f"unexpected collector error: {exc}")
                collected = []
            raw_candidates.extend(collected)
            print(
                f"[challenge] {SOURCE_NAMES[source_key]}: "
                f"discovered={stats[source_key].discovered} parsed={len(collected)} "
                f"failures={len(stats[source_key].failures)}",
                flush=True,
            )

    normalized_candidates: list[Candidate] = []
    normalization_rejections: list[RejectedCandidate] = []
    for candidate in raw_candidates:
        candidate.text = normalized_text(candidate.text)
        if len(candidate.text) < 25:
            normalization_rejections.append(
                RejectedCandidate(candidate, "text_too_short_after_normalization")
            )
        elif candidate.label == "hoax" and contains_verdict_leak(candidate.text):
            normalization_rejections.append(
                RejectedCandidate(candidate, "verdict_or_evidence_leakage")
            )
        else:
            normalized_candidates.append(candidate)

    exact_kept, exact_rejected = exact_deduplicate(normalized_candidates, args.cutoff)
    selected, similarity_rejected = select_candidates(
        exact_kept,
        corpus_index,
        threshold=args.similarity_threshold,
        target_per_source=args.target_per_source,
        cutoff=args.cutoff,
    )
    rejected = normalization_rejections + exact_rejected + similarity_rejected
    rejected.sort(key=lambda item: (item.rejection_reason, item.candidate.id))

    output_dir = args.output_dir.resolve()
    candidates_path = output_dir / "candidates.csv"
    rejected_path = output_dir / "rejected.csv"
    report_path = output_dir / "collection_report.json"
    write_csv(candidates_path, (candidate.csv_row() for candidate in selected), CSV_FIELDS)
    write_csv(rejected_path, (item.csv_row() for item in rejected), REJECTED_FIELDS)

    report = build_report(
        candidates=selected,
        rejected=rejected,
        stats=stats,
        corpus_path=args.corpus.resolve(),
        corpus_records=len(training_records),
        threshold=args.similarity_threshold,
        cutoff=args.cutoff,
        target_per_source=args.target_per_source,
    )
    output_dir.mkdir(parents=True, exist_ok=True)
    report_path.write_text(
        json.dumps(report, ensure_ascii=False, indent=2) + "\n", encoding="utf-8"
    )

    print("[challenge] final candidates by source:", flush=True)
    for source_key in SOURCE_ORDER:
        count = report["result"]["by_source"].get(source_key, 0)
        print(
            f"  - {SOURCE_NAMES[source_key]}: {count}/{args.target_per_source}",
            flush=True,
        )
    print(
        f"[challenge] candidates={len(selected)} rejected={len(rejected)} "
        f"target_met={report['result']['target_met']}",
        flush=True,
    )
    print(f"[challenge] wrote {candidates_path}", flush=True)
    print(f"[challenge] wrote {rejected_path}", flush=True)
    print(f"[challenge] wrote {report_path}", flush=True)
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
