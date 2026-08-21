"""Build a valid-news corpus from Antara News (antaranews.com).

Antara (Lembaga Kantor Berita Nasional) publishes Indonesian news. Article
URLs are discovered from paginated category index pages, bodies are extracted
from the ``.post-content`` div, and every article is validated as Indonesian.
Output: ``datasets/valid_antara.csv`` (columns text, label, title, url,
published_at, category, body_text).

Run::

    .\\.venv\\Scripts\\python.exe -m scripts.build_valid_news --out ..\\datasets\\valid_antara.csv --target 4000

The crawl is resumable: a checkpoint JSON tracks fetched URLs.
"""

from __future__ import annotations

import argparse
import csv
import html
import json
import random
import re
import sys
import time
import urllib.request
from concurrent.futures import ThreadPoolExecutor, as_completed
from datetime import datetime, timezone
from pathlib import Path
from typing import Optional

from dataset.normalize import clean_text, is_indonesian

USER_AGENT = "hoaxlin-id-research/1.0 (thesis dataset preparation; contact: user)"

CATEGORIES = [
    "nasional",
    "ekonomi",
    "metro",
    "olahraga",
    "hiburan",
    "gaya",
    "internasional",
    "nusantara",
    "otomotif",
    "humaniora",
]

MIN_BODY_CHARS = 300
MAX_RETRIES = 2
REQUEST_TIMEOUT = 25
STOP_MARKERS = (
    "baca juga",
    "berita terkait",
    "copyright",
    "editor:",
    "editor :",
    "reporter:",
    "reporter :",
    "penulis:",
    "penulis :",
    "ikutlah berlangganan",
)


def _http_get(url: str, timeout: int = REQUEST_TIMEOUT) -> str:
    req = urllib.request.Request(url, headers={"User-Agent": USER_AGENT})
    last_error: Optional[Exception] = None
    for attempt in range(MAX_RETRIES + 1):
        try:
            with urllib.request.urlopen(req, timeout=timeout) as resp:
                return resp.read().decode("utf-8", "ignore")
        except Exception as exc:  # noqa: BLE001
            last_error = exc
            status = getattr(exc, "code", None)
            if status in (403, 429):
                time.sleep(1.5 * (attempt + 1))
                continue
            if attempt < MAX_RETRIES:
                time.sleep(0.5 * (attempt + 1))
                continue
    raise RuntimeError(f"request failed after {MAX_RETRIES + 1} attempts: {url}") from last_error


def discover_article_urls(max_pages_per_category: int, max_urls: int) -> list[str]:
    """Crawl paginated category indexes and collect unique article URLs."""
    urls: set[str] = set()
    for category in CATEGORIES:
        if len(urls) >= max_urls:
            break
        for page in range(1, max_pages_per_category + 1):
            page_url = (
                f"https://www.antaranews.com/{category}"
                if page == 1
                else f"https://www.antaranews.com/{category}/{page}"
            )
            try:
                body = _http_get(page_url)
            except Exception:
                break  # category exhausted (404) or unreachable
            found = set(
                m.split("?")[0]
                for m in re.findall(r'href="(https://www\.antaranews\.com/berita/\d+/[^"]+)"', body)
            )
            urls.update(found)
            if len(urls) >= max_urls:
                break
            if not found:
                break
            time.sleep(0.15)
    return sorted(urls)


def _parse_published_at(article_html: str) -> Optional[str]:
    patterns = [
        r'<meta[^>]+property="article:published_time"[^>]+content="([^"]+)"',
        r'"datePublished"\s*:\s*"([^"]+)"',
        r'<time[^>]+datetime="([^"]+)"',
    ]
    for pattern in patterns:
        match = re.search(pattern, article_html)
        if match:
            value = match.group(1).strip()
            try:
                dt = datetime.fromisoformat(value.replace("Z", "+00:00"))
                return dt.astimezone(timezone.utc).isoformat()
            except ValueError:
                pass
    return None


def _extract_body(article_html: str) -> str:
    """Return the cleaned news body from the .post-content container."""
    match = re.search(r'<div[^>]+class="[^"]*post-content[^"]*"[^>]*>', article_html)
    if match:
        start = match.end()
        depth = 1
        end = start
        for div in re.finditer(r"<div\b|</div>", article_html[start:]):
            depth += 1 if div.group(0) == "<div" else -1
            if depth == 0:
                end = start + div.start()
                break
        container = article_html[start:end]
    else:
        container = article_html
    container = re.sub(r"<script.*?</script>", " ", container, flags=re.S | re.I)
    container = re.sub(r"<style.*?</style>", " ", container, flags=re.S | re.I)
    container = re.sub(r"<iframe.*?</iframe>", " ", container, flags=re.S | re.I)
    paragraphs = re.findall(r"<p[^>]*>(.*?)</p>", container, re.S)
    kept: list[str] = []
    for paragraph in paragraphs:
        text = clean_text(html.unescape(re.sub(r"<[^>]+>", "", paragraph)))
        lowered = text.lower()
        if len(text) < 60:
            continue
        if any(marker in lowered for marker in ("baca juga:", "berita terkait:")):
            continue
        if any(lowered.startswith(marker) for marker in ("editor:", "reporter:", "penulis:")):
            break
        if any(marker in lowered for marker in STOP_MARKERS):
            break
        kept.append(text)
    return " ".join(kept)


def fetch_article(url: str) -> dict:
    article_html = _http_get(url)
    title_match = re.search(r"<h1[^>]*>(.*?)</h1>", article_html, re.S)
    title = clean_text(html.unescape(re.sub(r"<[^>]+>", "", title_match.group(1)))) if title_match else ""
    body = _extract_body(article_html)
    return {
        "url": url,
        "title": title,
        "body_text": body,
        "published_at": _parse_published_at(article_html),
    }


def main() -> None:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--out", default=Path(__file__).resolve().parents[2] / "datasets" / "valid_antara.csv")
    parser.add_argument("--target", type=int, default=4000, help="target number of articles")
    parser.add_argument("--max-pages", type=int, default=120, help="max category pages crawled")
    parser.add_argument("--workers", type=int, default=6)
    args = parser.parse_args()

    out_path = Path(args.out)
    out_path.parent.mkdir(parents=True, exist_ok=True)
    checkpoint_path = out_path.with_suffix(".checkpoint.json")

    done: set[str] = set()
    if checkpoint_path.is_file():
        done = set(json.loads(checkpoint_path.read_text(encoding="utf-8")))

    print(f"[scraper] discovering article URLs (target {args.target})...", flush=True)
    urls = discover_article_urls(args.max_pages, max(args.target * 2, 1000))
    print(f"[scraper] discovered {len(urls)} unique URLs", flush=True)

    random.Random(7).shuffle(urls)
    urls = [u for u in urls if u not in done][: args.target]

    fetched: list[dict] = []
    failed: list[str] = []
    wrote_any = out_path.is_file()

    def write_row(row: dict) -> None:
        nonlocal wrote_any
        text = f"{row['title']}\n{row['body_text']}"
        with out_path.open("a", encoding="utf-8", newline="") as handle:
            writer = csv.DictWriter(
                handle,
                fieldnames=["text", "label", "title", "url", "published_at", "category", "body_text"],
            )
            if not wrote_any:
                writer.writeheader()
                wrote_any = True
            writer.writerow(
                {
                    "text": text,
                    "label": "valid",
                    "title": row["title"],
                    "url": row["url"],
                    "published_at": row["published_at"] or "",
                    "category": row.get("category", ""),
                    "body_text": row["body_text"],
                }
            )

    if urls:
        with ThreadPoolExecutor(max_workers=args.workers) as pool:
            futures = {pool.submit(fetch_article, url): url for url in urls}
            for idx, future in enumerate(as_completed(futures), start=1):
                url = futures[future]
                try:
                    row = future.result()
                    if len(row["body_text"]) < MIN_BODY_CHARS:
                        raise ValueError(f"body too short ({len(row['body_text'])})")
                    if not row["title"]:
                        raise ValueError("missing title")
                    if not is_indonesian(row["body_text"]):
                        raise ValueError("not Indonesian")
                    write_row(row)
                    fetched.append(row)
                except Exception as exc:  # noqa: BLE001
                    failed.append(url)
                    if idx <= 3 or len(failed) <= 3:
                        print(f"[scraper] FAIL {url}: {exc}", flush=True)
                finally:
                    done.add(url)
                    if idx % 50 == 0:
                        checkpoint_path.write_text(json.dumps(sorted(done)), encoding="utf-8")
                        print(f"[scraper] progress {idx}/{len(urls)} kept={len(fetched)}", flush=True)

    checkpoint_path.write_text(json.dumps(sorted(done)), encoding="utf-8")
    print(
        f"[scraper] done: kept={len(fetched)} failed={len(failed)} out={out_path}",
        flush=True,
    )
    if failed:
        print(f"[scraper] failed sample: {failed[:5]}", flush=True)
    if not fetched and not wrote_any:
        sys.exit(1)


if __name__ == "__main__":
    main()
