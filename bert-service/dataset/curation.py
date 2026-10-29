"""Private, versioned proposal staging; never publish or approve ground truth.

No source collection, training, split assignment or existing corpus overwrite.
Review gate is a readiness check, NOT authentication of human identities/evidence.
No reviewed exporter is enabled by this module; staged benchmark is always empty.
"""
from __future__ import annotations

import argparse
import hashlib
import json
import os
from collections import Counter, defaultdict
from datetime import datetime, timezone
from pathlib import Path
from urllib.parse import urlsplit

from dataset.normalize import normalize_key, is_indonesian
from dataset.sources.komdigi_csv import KomdigiCsvAdapter


def sha256(path):
    return hashlib.sha256(Path(path).read_bytes()).hexdigest()


def safe_reference(url):
    try:
        parsed = urlsplit(url)
        return parsed.scheme == 'https' and bool(parsed.hostname) and not parsed.username and not parsed.password
    except (ValueError, TypeError):
        return False


def review_gate(record):
    """Fail-closed readiness reasons; semantic/authenticated release review still required."""
    reasons = []
    if record.get('review_status') != 'approved': reasons.append('not_approved')
    if record.get('benchmark_eligible') is not True: reasons.append('not_benchmark_eligible')
    if record.get('final_label') not in ('valid', 'hoax'): reasons.append('missing_binary_final_label')
    if record.get('origin') not in ('observed_source', 'documented_translation'): reasons.append('unobserved_or_synthetic_origin')
    if record.get('disposition') != 'binary_candidate': reasons.append('not_binary_candidate')
    reviews = record.get('reviews', [])
    qualified = [r for r in reviews if r.get('actor_type') == 'human' and r.get('confidence') in ('high', 'medium')
                 and r.get('reviewer_id') and r.get('justification') and r.get('reviewed_at')]
    if len({r['reviewer_id'] for r in qualified}) < 2:
        reasons.append('two_distinct_human_reviews_required')
    if any(r.get('label') != record.get('final_label') for r in qualified):
        # Adjudication support deliberately not implemented: conflicts stay blocked.
        reasons.append('disagreement_requires_authenticated_adjudication')
    source = record.get('source', {})
    if source.get('rights_status') not in ('authorized_private', 'redistribution_authorized'):
        reasons.append('rights_pending')
    digest = source.get('artifact_sha256')
    if not isinstance(digest, str) or len(digest) != 64 or any(c not in '0123456789abcdef' for c in digest):
        reasons.append('missing_source_hash')
    evidence = record.get('evidence', [])
    relation = 'supports' if record.get('final_label') == 'valid' else 'refutes'
    if not any(e.get('relation') == relation and safe_reference(e.get('url'))
               and all(e.get(k) for k in ('publisher', 'title', 'accessed_at', 'locator', 'excerpt')) for e in evidence):
        reasons.append('missing_relevant_evidence')
    if not record.get('justification') or not record.get('claim_group') or not record.get('event_group'):
        reasons.append('missing_rationale_or_groups')
    if not record.get('context', {}).get('valid_as_of'): reasons.append('freshness_unreviewed')
    return sorted(set(reasons))


def normalized_conflicts(records):
    groups = defaultdict(list)
    for row in records:
        groups[normalize_key(row['claim']['text'])].append(row)
    return sorted(sorted(r['record_id'] for r in group) for group in groups.values()
                  if len({r.get('final_label') for r in group if r.get('final_label') in ('valid', 'hoax')}) > 1)


def stage_candidates(source: Path, output: Path, version: str):
    source = source.resolve()
    output = output.resolve()
    if output == source or output in source.parents or source in output.parents:
        raise ValueError('Source and output must be separate')
    # New private directory only; refuse overwrite even if an earlier run failed.
    output.mkdir(parents=True, exist_ok=False, mode=0o700)
    os.chmod(output, 0o700)
    input_hash = sha256(source)
    timestamp = datetime.now(timezone.utc).isoformat()
    candidates, quarantine, provenance, seen = [], [], [], set()
    counts = Counter()
    for ordinal, row in enumerate(KomdigiCsvAdapter(source).read(), 1):
        rid = f'komdigi:{row.source_id}'
        full = row.full_text or row.text
        (output / 'source-texts').mkdir(exist_ok=True, mode=0o700)
        # Adapter full text is already cleaned/pretrimmed. Original CSV remains parent.
        artifact = output / 'source-texts' / f'{ordinal}.txt'
        artifact.write_text(full, encoding='utf-8'); os.chmod(artifact, 0o600)
        key = hashlib.sha256(normalize_key(row.text).encode()).hexdigest()
        flags = ['human_review_missing', 'source_rights_pending', 'claim_event_group_review_missing']
        if row.text_source == 'full': flags.append('full_text_fallback_review_required')
        if rid in seen: flags.append('duplicate_source_id')
        seen.add(rid)
        if len(row.text) < 100: flags.append('short_claim')
        if not is_indonesian(row.text, 0.20): flags.append('language_heuristic_failed')
        if not safe_reference(row.url): flags.append('invalid_source_url')
        start = full.find(row.text)
        candidate = {
            'schema_version': 'claim-annotation-v1', 'record_id': f'{rid}@adapter-{ordinal}',
            'origin': 'observed_source',
            'source': {'url': row.url or '', 'publisher': 'Komdigi',
                       'artifact_ref': f'source-texts/{ordinal}.txt', 'artifact_sha256': sha256(artifact),
                       'published_at': row.published_at.isoformat() if row.published_at else None,
                       'retrieved_at': timestamp, 'rights_status': 'pending', 'text_source': 'article'},
            'claim': {'text': row.text, 'language': row.language, 'representation': 'verbatim',
                      'source_span': {'start': start, 'end': start + len(row.text)} if start >= 0 else None,
                      'transform_version': 'komdigi-existing-adapter-proposal-v1', 'translation_provenance': None},
            'context': {'time': None, 'place': None, 'limitations': 'Claim/event time and place not reviewed; publication time is not event time.',
                        'topic': ','.join(row.topics) or 'unknown', 'style': 'other', 'valid_as_of': timestamp[:10]},
            'evidence': [], 'proposed_label': None, 'final_label': None,
            'justification': 'Unreviewed adapter extraction; source-implied hoax is not claim ground truth.',
            'disposition': 'abstention_candidate', 'claim_group': f'unreviewed-key:{key}',
            'event_group': f'unreviewed-source:{row.source_id}', 'reviews': [], 'adjudication': None,
            'review_status': 'quarantined', 'benchmark_eligible': False,
        }
        assert start >= 0 and full[start:start+len(row.text)] == row.text
        candidates.append(candidate)
        provenance.append({'record_id': candidate['record_id'], 'source_id': row.source_id,
                           'parent_csv_sha256': input_hash, 'adapter_ordinal': ordinal,
                           'source_implied_label': row.raw_label, 'selected_sha256': hashlib.sha256(row.text.encode()).hexdigest(),
                           'cleaned_parent_sha256': sha256(artifact), 'source_hash_meaning': 'cleaned adapter parent, not raw body',
                           'transform_chain': ['clean_text:NFKC-html-whitespace', 'komdigi-link-counter-pretrim', 'cut_claim:existing'],
                           'source_code_sha256': {str(p.relative_to(Path(__file__).resolve().parents[1])): sha256(p)
                                                  for p in (Path(__file__), Path(__file__).parent / 'normalize.py', Path(__file__).parent / 'sources/komdigi_csv.py')}})
        quarantine.append({'record_id': candidate['record_id'], 'source_record_id': rid,
                           'normalized_key': key, 'reasons': flags, 'review_gate': review_gate(candidate)})
        counts[row.text_source] += 1
    by_key = defaultdict(list)
    for row in quarantine: by_key[row['normalized_key']].append(row)
    for group in by_key.values():
        if len(group) > 1:
            for row in group: row['reasons'].append('normalized_duplicate_review_required')
    for name, rows in [('candidates.jsonl', candidates), ('quarantine.jsonl', quarantine), ('provenance.jsonl', provenance), ('benchmark.jsonl', [])]:
        path = output / name
        with path.open('w', encoding='utf-8') as handle:
            for row in rows: handle.write(json.dumps(row, ensure_ascii=False, sort_keys=True) + '\n')
        os.chmod(path, 0o600)
    summary = {'schema': 'curation-staging-v1', 'version': version, 'status': 'BLOCKED_REVIEW_AND_MULTI_SOURCE',
               'dataset_role': 'NEW_UPDATED_KOMDIGI_PROPOSALS_NOT_HISTORICAL_TRAINING',
               'created_at': timestamp, 'input_sha256': input_hash, 'emitted_candidates': len(candidates),
               'text_source_counts': dict(counts), 'approved_records': 0, 'benchmark_records': 0,
               'quarantined_records': len(quarantine), 'labels': {'valid': 0, 'hoax': 0, 'unreviewed': len(candidates)},
               'duplicate_groups': sum(len(g)>1 for g in by_key.values()),
               'cross_label_conflicts': None, 'cross_label_status': 'UNASSESSABLE_WITHOUT_REVIEWED_LABELS',
               'parent_source': str(source), 'source_snapshot_meaning': 'source-texts are adapter-cleaned/pretrimmed, not raw source; original CSV hash above',
               'timestamp_meaning': 'local staging access time, not original Kaggle download time',
               'artifacts': {name: sha256(output / name) for name in ('candidates.jsonl', 'quarantine.jsonl', 'provenance.jsonl', 'benchmark.jsonl')},
               'export_gate': 'Readiness helper only; no reviewed exporter enabled, benchmark always empty'}
    assert sha256(source) == input_hash
    (output / 'manifest.json').write_text(json.dumps(summary, indent=2, sort_keys=True)+'\n')
    os.chmod(output / 'manifest.json', 0o600)
    return summary


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--source', type=Path, required=True)
    parser.add_argument('--output', type=Path, required=True)
    parser.add_argument('--version', required=True)
    args = parser.parse_args()
    summary = stage_candidates(args.source, args.output, args.version)
    print(json.dumps({k: summary[k] for k in ('status', 'emitted_candidates', 'approved_records', 'benchmark_records', 'input_sha256')}))
