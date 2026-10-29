"""Release-authoritative binary argmax + confidence abstention, version 1."""
import hashlib
import json


def fingerprint(identity: dict) -> str:
    return hashlib.sha256(json.dumps(identity, sort_keys=True, separators=(',', ':'), allow_nan=False).encode()).hexdigest()


def decide(scores: dict[str, float], threshold: float) -> dict:
    # Stable tie behavior matches canonical id2label ordering.
    raw = max(('valid', 'hoax'), key=scores.__getitem__)
    abstain = scores[raw] < threshold
    return {'raw_argmax': raw, 'served_label': 'meragukan' if abstain else raw,
            'abstention_reason': 'below_threshold' if abstain else None}
