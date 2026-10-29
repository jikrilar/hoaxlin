from __future__ import annotations

import re
from pathlib import Path
from typing import Any
from train.data import encode_training
from train.evaluate import encode_evaluation
from app.config import Settings
from app.inference import ModelRuntime


CLAIM_MARKERS = (
    "klaim",
    "mengklaim",
    "beredar",
    "viral",
    "hoaks",
    "hoax",
    "fakta",
    "faktanya",
    "klarifikasi",
    "palsu",
    "bohong",
    "tidak",
    "bukan",
    "belum",
    "tanpa",
    "resmi",
    "membantah",
    "menegaskan",
    "mengonfirmasi",
)


def compare_tokenization(
    text: str,
    tokenizer: Any,
    train_max_len: int = 256,
    serve_max_len: int = 512,
) -> dict[str, Any]:
    """Compare exact token IDs produced across train, eval, and serve tokenization paths."""
    raw = tokenizer(text, add_special_tokens=True)["input_ids"]
    total_tokens = len(raw)

    train_encoded = encode_training(tokenizer, text, train_max_len)["input_ids"]

    eval_encoded = encode_evaluation(tokenizer, text, train_max_len)["input_ids"]

    runtime = ModelRuntime(Settings("audit", "test", None, None, None, 1, 20000, serve_max_len, True))
    runtime._tokenizer = tokenizer
    serve_encoded = runtime._encode(text)["input_ids"][0].tolist()

    # In train/eval, padding tokens (0) follow [SEP]. Compare active tokens up to [SEP].
    train_active = [tid for tid in train_encoded if tid != tokenizer.pad_token_id]
    eval_active = [tid for tid in eval_encoded if tid != tokenizer.pad_token_id]

    parity_train_eval = train_encoded == eval_encoded

    # Check if serve matches train active tokens up to train_max_len
    # Exclude trailing [SEP] for prefix comparison if lengths differ
    if total_tokens > train_max_len:
        # train cut earlier than serve
        prefix_matches = train_active[:-1] == serve_encoded[: len(train_active) - 1]
    else:
        prefix_matches = train_active == serve_encoded

    return {
        "text_chars": len(text),
        "total_tokens": total_tokens,
        "train_max_length": train_max_len,
        "serve_max_length": serve_max_len,
        "train_tokens_count": len(train_active),
        "eval_tokens_count": len(eval_active),
        "serve_tokens_count": len(serve_encoded),
        "train_truncated": total_tokens > train_max_len,
        "serve_truncated": total_tokens > serve_max_len,
        "train_eval_parity": parity_train_eval,
        "train_serve_prefix_parity": prefix_matches,
    }


def head_only_sequence(
    text: str,
    tokenizer: Any,
    max_tokens: int = 512,
) -> dict[str, Any]:
    """Head-only truncation: preserves the opening tokens, drops subsequent text."""
    encoded = tokenizer(
        text,
        truncation=True,
        max_length=max_tokens,
        add_special_tokens=True,
    )
    raw_tokens = tokenizer(text, add_special_tokens=True)["input_ids"]
    return {
        "strategy": "head-only",
        "max_tokens": max_tokens,
        "total_tokens": len(raw_tokens),
        "output_tokens": len(encoded["input_ids"]),
        "truncated": len(raw_tokens) > max_tokens,
        "input_ids": encoded["input_ids"],
    }


def _split_sentences(text: str) -> list[str]:
    # Split on sentence boundaries while preserving content
    raw_parts = re.split(r"(?<=[.!?])\s+", text.strip())
    sentences = [p.strip() for p in raw_parts if p.strip()]
    return sentences or [text.strip()]


def claim_sentence_selection(
    text: str,
    tokenizer: Any,
    max_tokens: int = 512,
) -> dict[str, Any]:
    """Candidate policy: prioritizes sentences containing factual claim markers and lead paragraph."""
    sentences = _split_sentences(text)
    if not sentences:
        return head_only_sequence(text, tokenizer, max_tokens)

    # Score each sentence: lead sentence gets priority, then claim marker density
    scored: list[tuple[float, int, str]] = []
    for idx, sent in enumerate(sentences):
        score = 0.0
        if idx == 0:
            score += 2.0  # Lead sentence priority
        lowered = sent.lower()
        for marker in CLAIM_MARKERS:
            if marker in lowered:
                score += 1.0
        # Dates and numbers
        if re.search(r"\b\d+(?:[.,]\d+)?\b", sent):
            score += 0.5
        scored.append((score, idx, sent))

    # Sort descending by score, but preserve chronological order when packing
    scored.sort(key=lambda item: item[0], reverse=True)

    # Budget tokens: account for [CLS] and [SEP]
    budget = max_tokens - 2
    selected_indices: set[int] = set()
    current_tokens = 0

    for score, idx, sent in scored:
        sent_tokens = len(tokenizer(sent, add_special_tokens=False)["input_ids"])
        if current_tokens + sent_tokens <= budget:
            selected_indices.add(idx)
            current_tokens += sent_tokens
        elif not selected_indices:
            # If even the top sentence exceeds budget, add it for head truncation
            selected_indices.add(idx)
            break

    # Reconstruct text in original document order
    ordered_sentences = [sentences[i] for i in sorted(selected_indices)]
    selected_text = " ".join(ordered_sentences)
    encoded = tokenizer(
        selected_text,
        truncation=True,
        max_length=max_tokens,
        add_special_tokens=True,
    )
    raw_tokens = tokenizer(text, add_special_tokens=True)["input_ids"]

    return {
        "strategy": "claim-selection",
        "max_tokens": max_tokens,
        "total_tokens": len(raw_tokens),
        "output_tokens": len(encoded["input_ids"]),
        "selected_sentence_count": len(selected_indices),
        "total_sentence_count": len(sentences),
        "truncated": len(raw_tokens) > max_tokens,
        "selected_text": selected_text,
        "input_ids": encoded["input_ids"],
    }


def bounded_windows(
    text: str,
    tokenizer: Any,
    window_size: int = 256,
    max_windows: int = 4,
    overlap: int = 32,
) -> list[dict[str, Any]]:
    """Segment text into bounded token windows with specified stride."""
    tokens = tokenizer(text, add_special_tokens=False)["input_ids"]
    cls_id = tokenizer.cls_token_id or 2
    sep_id = tokenizer.sep_token_id or 3
    stride = max(1, window_size - 2 - overlap)
    content_capacity = window_size - 2

    windows: list[dict[str, Any]] = []
    for w_idx in range(max_windows):
        start = w_idx * stride
        if start >= len(tokens) and windows:
            break
        chunk = tokens[start : start + content_capacity]
        if not chunk and windows:
            break
        window_ids = [cls_id] + chunk + [sep_id]
        windows.append(
            {
                "window_index": w_idx,
                "token_start": start,
                "token_end": start + len(chunk),
                "token_count": len(window_ids),
                "input_ids": window_ids,
            }
        )
        if start + len(chunk) >= len(tokens):
            break

    return windows


def get_standard_fixtures() -> dict[str, str]:
    """Standardized test fixtures covering challenging sequence boundary scenarios."""
    # 1. Short claim
    short = "Menteri Kesehatan Budi Gunadi menegaskan vaksin flu burung gratis."

    # 2. Long multi-paragraph article (> 512 tokens)
    long_parts = [
        "Pemerintah Provinsi DKI Jakarta kembali menggelar rapat koordinasi terkait program bantuan sosial.",
        "Rapat yang dipimpin langsung oleh jajaran dinas sosial membahas mekanisme penyaluran kepada keluarga penerima manfaat.",
        "Dalam pembahasannya disampaikan bahwa anggaran telah dialokasikan sesuai ketentuan APBD tahun anggaran berjalan.",
        "Setiap kelurahan diminta untuk memverifikasi data kependudukan secara berkala guna mencegah terjadinya duplikasi data penerima.",
        "Selain itu koordinasi dengan pihak perbankan penyalur juga terus ditingkatkan agar proses transfer dapat terlaksana tepat waktu.",
        "Warga yang belum terdaftar diimbau segera melapor ke posko kelurahan setempat dengan membawa kartu keluarga dan KTP elektronik.",
        "Petugas lapangan akan melakukan verifikasi faktual terhadap kondisi ekonomi keluarga pemohon sebelum menetapkan kelayakan penerimaan.",
        "Dinas sosial mengingatkan agar masyarakat waspada terhadap pungutan liar yang mengatasnamakan petugas pendataan bantuan sosial.",
    ]
    # Repeat to ensure > 512 tokens
    long_article = " ".join(long_parts * 5)

    # 3. Claim at end (background context first, crucial falsifiable claim only in the final sentence)
    claim_at_end = (
        " ".join(long_parts * 4)
        + " Namun di media sosial beredar klaim bohong bahwa pemerintah akan membagikan uang tunai Rp 100 juta kepada setiap pemilik rekening BCA."
    )

    # 4. Negation preservation
    negation = "Kementerian Keuangan TIDAK PERNAH membagikan dana hibah Rp 50 juta melalui tautan Telegram mana pun."

    # 5. Code-switched (mixed Indonesian and English technical terms)
    code_switched = (
        "Breaking news: Viral broadcast di group WhatsApp mengenai free vaccine giveaway "
        "dinyatakan sebagai phishing scam oleh Satgas Siber BSSN."
    )

    # 6. Noisy OCR (erratic spacing, broken words, extra newlines)
    noisy_ocr = (
        "K E M E N T E R I A N   K E S E H A T A N\n\n"
        "P E N G U M U M A N   R E S M I\n\n"
        "Kemen- terian Kesehatan meng- klarifikasi bahwa su- rat edaran bernomor 04/2026 "
        "adalah DOKUMEN PALSU dan tidak per- nah diterbitkan."
    )

    # Long preamble fixture; exact token-boundary test is constructed separately.
    boundary_preamble = " ".join(
        [f"Paragraf pengantar nomor {i} untuk menguji batas jendela token IndoBERT." for i in range(25)]
    )
    window_boundary = (
        boundary_preamble
        + " Poin penting: Presiden Joko Widodo TIDAK PERNAH menerbitkan keputusan pembagian emas batangan."
    )

    # 8. Contradicting claims in a single article (debunking structure: quotes hoax, then refutes it)
    contradicting = (
        "Beredar pesan viral di media sosial yang mengklaim bahwa Menkes membagikan bantuan tunai Rp 50 juta. "
        "Pesan tersebut mencantumkan nomor kontak dan tautan pendaftaran palsu. "
        "Menanggapi hal tersebut, Kementerian Kesehatan menegaskan bahwa informasi tersebut adalah hoaks dan tidak benar. "
        "Masyarakat diminta tidak memberikan data pribadi ke tautan tersebut."
    )

    return {
        "short": short,
        "long": long_article,
        "claim_at_end": claim_at_end,
        "negation": negation,
        "code_switched": code_switched,
        "noisy_ocr": noisy_ocr,
        "window_boundary": window_boundary,
        "contradicting_claims": contradicting,
    }


def evaluate_candidate_policies(tokenizer: Any) -> dict[str, Any]:
    """Evaluate candidate sequence policies across the standard fixture suite."""
    fixtures = get_standard_fixtures()
    results: dict[str, Any] = {}

    for name, text in fixtures.items():
        comp = compare_tokenization(text, tokenizer, train_max_len=256, serve_max_len=512)
        head = head_only_sequence(text, tokenizer, max_tokens=512)
        claim_sel = claim_sentence_selection(text, tokenizer, max_tokens=512)
        windows = bounded_windows(text, tokenizer, window_size=256, max_windows=4)

        # Check whether crucial claim is preserved in the output text/tokens
        # For 'claim_at_end', the claim contains '100 juta'
        claim_preserved_head = None
        claim_preserved_sel = None
        if name == "claim_at_end":
            claim_needle = "100 juta"
            head_decoded = tokenizer.decode(head["input_ids"])
            claim_preserved_head = claim_needle in head_decoded
            claim_preserved_sel = claim_needle in claim_sel.get("selected_text", "")

        results[name] = {
            "total_tokens": comp["total_tokens"],
            "train_truncated": comp["train_truncated"],
            "serve_truncated": comp["serve_truncated"],
            "train_eval_parity": comp["train_eval_parity"],
            "train_serve_prefix_parity": comp["train_serve_prefix_parity"],
            "head_preserves_claim": claim_preserved_head,
            "claim_selection_preserves_claim": claim_preserved_sel,
            "num_windows": len(windows),
        }

    return results


def measure_dataset_truncation(
    csv_path: Path,
    tokenizer: Any,
    text_column: str = "text",
    thresholds: tuple[int, ...] = (256, 512),
) -> dict[str, Any]:
    """Measure token lengths and truncation rates over a dataset."""
    import csv

    lengths: list[int] = []
    with open(csv_path, encoding="utf-8-sig") as f:
        reader = csv.DictReader(f)
        for row in reader:
            text = row.get(text_column, "")
            tokens = tokenizer(text, add_special_tokens=True)["input_ids"]
            lengths.append(len(tokens))

    if not lengths:
        return {"total_samples": 0}

    total = len(lengths)
    sorted_len = sorted(lengths)
    stats: dict[str, Any] = {
        "total_samples": total,
        "min_tokens": min(lengths),
        "median_tokens": sorted_len[total // 2],
        "mean_tokens": round(sum(lengths) / total, 2),
        "max_tokens": max(lengths),
    }

    for thr in thresholds:
        over = [l for l in lengths if l > thr]
        stats[f"over_{thr}_tokens_count"] = len(over)
        stats[f"over_{thr}_tokens_rate"] = round(len(over) / total, 4)

    return stats
