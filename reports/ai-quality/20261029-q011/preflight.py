"""Read-only Q-011 readiness: no partition assignment or freeze on pending data."""
import csv
import hashlib
import json
from collections import Counter
from pathlib import Path

ROOT = Path('/workspace')
staging = Path('/output/komdigi-proposals-v0.1.1')
sha = lambda p: hashlib.sha256(p.read_bytes()).hexdigest()
challenge = ROOT / 'datasets/challenge/external-challenge-v1/challenge.csv'
with challenge.open(encoding='utf-8-sig', newline='') as handle:
    old_count = sum(1 for _ in csv.DictReader(handle))
present = staging.is_dir()
rows = [json.loads(line) for line in (staging / 'candidates.jsonl').read_text().splitlines()] if present else []
from dataset.curation import review_gate
eligible = [r for r in rows if not review_gate(r)]
print(json.dumps({
    'task': 'Q-011', 'status': 'BLOCKED_NO_REVIEWED_MULTI_SOURCE_CORPUS',
    'staging_present': present, 'staged_proposals': len(rows), 'eligible_reviewed_records': len(eligible),
    'review_status_counts': dict(Counter(r['review_status'] for r in rows)),
    'candidate_sha256': sha(staging / 'candidates.jsonl') if present else None,
    'legacy_challenge': {'rows': old_count, 'sha256': sha(challenge), 'role': 'historical_regression_not_blind_holdout'},
    'partitions_frozen': False, 'freeze_timestamp': None,
    'partition_hashes': None, 'partition_counts': None,
    'actual_eligible_partition_overlap_audit': 'NOT_RUN_NO_ELIGIBLE_PARTITIONS',
    'temporal_or_source_holdout': 'NOT_ASSIGNED_INSUFFICIENT_COVERAGE',
    'minimum_target_each_dev_and_holdout': {'valid': 100, 'hoax': 100},
    'remaining_blockers': ['human approval and source rights', 'reviewed valid/hoax coverage',
                           'reviewed cross-source claim/event/translation groups',
                           'temporal cutoff and source holdout signoff', 'legacy challenge overlap on actual candidate partitions'],
}, sort_keys=True, indent=2))
