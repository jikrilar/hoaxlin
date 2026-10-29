"""Read-only prerelease partition checks. Does not assign or freeze partitions.

Exhaustive lexical checking for bounded audit sets (not MinHash sampling).
Semantic paraphrase/translation/event relations still require human group review.
"""
from collections import Counter, defaultdict
from itertools import combinations

from dataset.curation import review_gate
from dataset.normalize import normalize_key, tokenize_norm, word_shingles
from dataset.dedup import jaccard

PARTITIONS = ('train', 'val', 'dev', 'calibration', 'holdout')


def text_overlap(left, right):
    a, b = normalize_key(left), normalize_key(right)
    if a and a == b:
        return 'exact_text'
    # At least seven tokens; contiguous containment, not a generic word match.
    ta, tb = tokenize_norm(left), tokenize_norm(right)
    if min(len(ta), len(tb)) >= 7 and (f' {a} ' in f' {b} ' or f' {b} ' in f' {a} '):
        return 'contained_fragment'
    if min(len(ta), len(tb)) >= 3 and jaccard(set(word_shingles(ta)), set(word_shingles(tb))) >= 0.65:
        return 'near_text'
    return None


def validate_partitions(rows, legacy, *, check_review=True):
    """No release approval: reports necessary checks, requiring semantic signoff separately."""
    if len(rows) > 5000:
        raise ValueError('Exhaustive validator bounded to 5000 records; do not silently sample')
    violations, blockers = [], []
    ids = Counter(r.get('record_id') for r in rows)
    if any(not k or n > 1 for k, n in ids.items()): blockers.append('duplicate_record_ids')
    if any(r.get('partition') not in PARTITIONS for r in rows): blockers.append('unknown_or_missing_partition')
    coverage = {p: dict(Counter(r.get('final_label') for r in rows if r.get('partition') == p)) for p in PARTITIONS}
    if any(coverage[p].get(label, 0) < 100 for p in ('dev', 'holdout') for label in ('valid', 'hoax')):
        blockers.append('minimum_reviewed_benchmark_counts_not_met')
    for p in ('train', 'val', 'calibration'):
        if not any(r.get('partition') == p for r in rows): blockers.append(f'empty_{p}')
    unreviewed = [r['record_id'] for r in rows if check_review and review_gate(r)]
    if unreviewed: blockers.append('unreviewed_records')
    for kind, getter in (
        ('claim_group', lambda r: r.get('claim_group')),
        ('event_group', lambda r: r.get('event_group')),
        ('parent_artifact', lambda r: r.get('source', {}).get('artifact_sha256')),
    ):
        groups = defaultdict(list)
        for r in rows:
            key = getter(r)
            if not key or str(key).startswith('unreviewed'):
                blockers.append(f'missing_or_provisional_{kind}')
            else: groups[key].append(r)
        for members in groups.values():
            if len({r.get('partition') for r in members}) > 1:
                violations.append({'kind': kind, 'record_ids': sorted(r['record_id'] for r in members)})
    for a, b in combinations(rows, 2):
        if a.get('partition') == b.get('partition'): continue
        kind = text_overlap(a['claim']['text'], b['claim']['text'])
        if kind: violations.append({'kind': kind, 'record_ids': sorted([a['record_id'], b['record_id']])})
    overlap = []
    for row in rows:
        for old in legacy:
            kind = text_overlap(row['claim']['text'], old['text'])
            if kind: overlap.append({'kind': kind, 'record_id': row['record_id'], 'legacy_id': old['record_id']})
    if violations: blockers.append('cross_partition_overlap')
    if overlap: blockers.append('legacy_challenge_overlap_requires_exclusion_or_review')
    sources = defaultdict(set)
    for row in rows:
        sources[row.get('source', {}).get('publisher', 'MISSING')].add(row.get('final_label'))
    confounds = sorted(source for source, labels in sources.items() if len(labels & {'valid', 'hoax'}) == 1)
    # Coverage is not a sufficient release gate: never fabricate semantic/time/source signoff.
    blockers.append('human_semantic_temporal_source_holdout_signoff_required')
    return {'ready_to_freeze': False, 'coverage': coverage, 'violations': violations,
            'legacy_overlap': overlap, 'unreviewed_records': unreviewed,
            'single_label_source_confounds': confounds, 'blockers': sorted(set(blockers)),
            'policy': {'normalized_word_trigram_jaccard_min': 0.65,
                       'contained_fragment_min_tokens': 7, 'exhaustive_record_limit': 5000,
                       'semantic_translation_detection': 'human-reviewed claim/event groups required'},
            'row_count': len(rows), 'legacy_count': len(legacy)}
