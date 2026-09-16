"""Compare deterministic CPU inference between two local model releases."""

from __future__ import annotations

import argparse
import gc
import json
import math
import sys
from pathlib import Path
from typing import Any

from package_model_release import validate_model_contract


def evaluate(model_path: Path, texts: list[str]) -> list[dict[str, Any]]:
    from transformers import AutoModelForSequenceClassification, AutoTokenizer
    import torch

    contract = validate_model_contract(model_path)
    tokenizer = AutoTokenizer.from_pretrained(model_path, local_files_only=True)
    model = AutoModelForSequenceClassification.from_pretrained(
        model_path, local_files_only=True
    )
    model.to("cpu")
    model.eval()

    results = []
    for text in texts:
        encoded = tokenizer(
            text,
            return_tensors="pt",
            truncation=True,
            max_length=512,
        )
        with torch.inference_mode():
            logits = model(**encoded).logits[0] / contract["temperature"]
            probabilities = torch.softmax(logits, dim=-1).detach().cpu().tolist()
        raw_scores = {
            contract["id2label"][index]: float(score)
            for index, score in enumerate(probabilities)
        }
        best_index = max(range(len(probabilities)), key=probabilities.__getitem__)
        confidence = float(probabilities[best_index])
        label = contract["id2label"][best_index]
        if confidence < contract["threshold"]:
            label = "meragukan"
        results.append(
            {
                "label": label,
                "confidence": confidence,
                "raw_scores": raw_scores,
            }
        )

    del model
    del tokenizer
    gc.collect()
    return results


def compare(
    original: Path,
    packaged: Path,
    texts: list[str],
    tolerance: float,
) -> dict[str, Any]:
    original_results = evaluate(original, texts)
    packaged_results = evaluate(packaged, texts)
    comparisons = []
    all_passed = True

    for text, source, release in zip(texts, original_results, packaged_results):
        confidence_delta = abs(source["confidence"] - release["confidence"])
        score_deltas = {
            label: abs(source["raw_scores"][label] - release["raw_scores"][label])
            for label in source["raw_scores"]
        }
        passed = (
            source["label"] == release["label"]
            and confidence_delta <= tolerance
            and all(delta <= tolerance for delta in score_deltas.values())
        )
        all_passed = all_passed and passed
        comparisons.append(
            {
                "input": text,
                "original": source,
                "packaged": release,
                "confidence_delta": confidence_delta,
                "raw_score_deltas": score_deltas,
                "passed": passed,
            }
        )

    return {
        "device": "cpu",
        "local_files_only": True,
        "tolerance": tolerance,
        "passed": all_passed,
        "comparisons": comparisons,
    }


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--original", type=Path, required=True)
    parser.add_argument("--packaged", type=Path, required=True)
    parser.add_argument(
        "--text",
        action="append",
        required=True,
        help="Fixed input text; provide this option at least three times",
    )
    parser.add_argument("--tolerance", type=float, default=1e-7)
    return parser.parse_args()


def main() -> int:
    args = parse_args()
    if len(args.text) < 3:
        print("ERROR: provide at least three --text inputs", file=sys.stderr)
        return 2
    if not math.isfinite(args.tolerance) or args.tolerance < 0:
        print("ERROR: --tolerance must be a finite non-negative number", file=sys.stderr)
        return 2

    result = compare(
        args.original.resolve(),
        args.packaged.resolve(),
        args.text,
        args.tolerance,
    )
    print(json.dumps(result, indent=2, ensure_ascii=False))
    return 0 if result["passed"] else 1


if __name__ == "__main__":
    raise SystemExit(main())
