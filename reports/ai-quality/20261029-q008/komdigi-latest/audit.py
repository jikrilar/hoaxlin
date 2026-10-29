"""Read-only audit of updated Komdigi CSV, never historical training reproduction.

Run with PYTHONPATH=bert-service. Outputs metadata/IDs only, no article previews.
No network, model loading, preparation export, relabeling or row deletion.
"""
import argparse
import csv
import hashlib
import json
import re
from collections import Counter, defaultdict
from pathlib import Path

from dataset.normalize import clean_text, cut_claim, is_indonesian, normalize_key
from dataset.sources.komdigi_csv import EXPECTED_COLUMNS, LINK_COUNTER_MARKERS, KomdigiCsvAdapter

VERSION = 'q008-komdigi-latest-audit-v1'
SEED = 'Q008-42'
# Mirrors normalize.cut_claim's marker vocabulary only to explain its false flag.
# Actual cut result and selected text always come from the unchanged adapter.
MARKERS = ('faktanya', 'setelah dilakukan penelusuran', 'dilansir dari',
           'melansir dari', 'dikutip dari', 'berdasarkan laman',
           'berdasarkan penelusuran', 'melalui akun resmi',
           'melalui laman resmi', 'link counter')
VERDICT = re.compile(r'\bfaktanya\b|(?:klaim|informasi|kabar|berita|narasi|unggahan|pesan)\s+(?:tersebut\s+(?:adalah\s+|merupakan\s+)?|(?:adalah|merupakan)\s+)(?:tidak benar|tidak berdasar|palsu|hoaks|hoax)|(?:hasil|setelah dilakukan)\s+penelusuran', re.I)
SOURCE = re.compile(r'\b(?:dilansir|dikutip|melansir|bersumber)\s+(?:dari\s+)?|\b(?:komdigi|kominfo|antaranews|turnbackhoax|cekfakta)\b', re.I)
BYLINE = re.compile(r'\b(?:penulis|reporter|editor|pewarta)\s*:', re.I)
DATELINE = re.compile(r'^.{0,60}\((?:ANTARA|Kompas|Detik)[^)]*\)\s*[-–—]', re.I)


def digest(path):
    return hashlib.sha256(Path(path).read_bytes()).hexdigest()


def cut_reason(text):
    if cut_claim(text)[1]:
        return 'cut'
    cleaned = re.sub(r'^\s*penjelasan\s*:?\s*', '', clean_text(text), flags=re.I).lower()
    positions = [cleaned.find(m) for m in MARKERS if cleaned.find(m) >= 0]
    return 'earliest_marker_before_120' if positions else 'no_marker'


def indicators(text):
    return [name for name, pattern in [('verdict_phrase', VERDICT), ('source_marker', SOURCE),
                                      ('author_marker', BYLINE), ('dateline', DATELINE)] if pattern.search(text)]


def sample(ids):
    return sorted(set(ids), key=lambda value: (hashlib.sha256(f'{SEED}|{value}'.encode()).hexdigest(), value))[:5]


def duplicate_groups(pairs):
    groups = defaultdict(list)
    for record_id, text in pairs:
        key = hashlib.sha256(normalize_key(text).encode()).hexdigest()
        groups[key].append(record_id)
    return {key: sorted(ids) for key, ids in sorted(groups.items()) if len(ids) > 1}


def length_stats(values):
    values = sorted(values)
    if not values:
        return {'n': 0, 'min': None, 'p50': None, 'p90': None, 'p95': None, 'max': None, 'mean': None}
    return {'n': len(values), 'min': values[0], 'p50': values[int((len(values)-1)*0.5)],
            'p90': values[int((len(values)-1)*0.9)], 'p95': values[int((len(values)-1)*0.95)],
            'max': values[-1], 'mean': round(sum(values)/len(values), 4)}


def bucket(n):
    return '<100' if n < 100 else '100-255' if n < 256 else '256-999' if n < 1000 else '1000-3999' if n < 4000 else '>=4000'


def write_json(path, value):
    path.write_text(json.dumps(value, ensure_ascii=False, sort_keys=True, indent=2) + '\n')


def run(source, output):
    # Refuse overwrite so prior audit evidence cannot be silently replaced.
    output.mkdir(parents=True, exist_ok=False)
    with source.open(encoding='utf-8-sig', newline='') as handle:
        reader = csv.DictReader(handle)
        columns = reader.fieldnames
        rows = list(reader)
    assert EXPECTED_COLUMNS <= set(columns), 'Missing adapter columns'
    assert all(None not in row and all(v is not None for v in row.values()) for row in rows), 'Ragged CSV rows'
    adapter = KomdigiCsvAdapter(source)
    adapted = iter(adapter.read())
    records, selected_pairs, raw_pairs = [], [], []
    strata, reasons, matrix = defaultdict(list), defaultdict(list), Counter()
    counters = Counter()
    source_ids = Counter(row['id'] for row in rows)
    for number, row in enumerate(rows, 1):
        # row ordinal disambiguates repeated/empty source IDs. Not a physical CSV line.
        rid = f"komdigi:{row['id']}"
        audit_id = f'{rid}@row-{number}'
        body = clean_text(row['body_text'])
        for indicator in indicators(body): counters[f'raw_body_indicator:{indicator}'] += 1
        if re.search(r'\[\s*(?:hoaks|hoax)\s*\]', row['title'], re.I):
            counters['title_bracket_hoax_marker'] += 1
        raw_pairs.append((audit_id, body))
        full = body
        for marker in LINK_COUNTER_MARKERS:
            pos = full.lower().find(marker)
            if pos >= 120:
                full = full[:pos].strip()
                counters['link_counter_pretrim'] += 1
                break
        claim, cut = cut_claim(full)
        for indicator in indicators(full): counters[f'adapter_full_indicator:{indicator}'] += 1
        selected = claim if cut else full
        eligible = bool(selected and clean_text(row['title']))
        reason = cut_reason(full)
        flags = []
        if not row['id'].strip(): flags.append('empty_source_id')
        if source_ids[row['id']] > 1: flags.append('duplicate_source_id')
        if not body: flags.append('empty_body')
        if not clean_text(row['title']): flags.append('empty_title')
        date = adapter._parse_datetime(row['published_at'])
        if date is None: flags.append('missing_date' if not row['published_at'].strip() else 'invalid_date')
        if not row['topics'].strip(): flags.append('missing_topics')
        if not row['url'].strip(): flags.append('missing_url')
        if eligible:
            actual = next(adapted)
            assert actual.source_id == row['id'] and actual.text == selected and actual.full_text == full
            assert actual.text_source == ('claim' if cut else 'full')
            counters['adapter_emitted'] += 1
            counters[f'cut_reason:{reason}'] += 1
            if not cut: flags.append(f'full_fallback:{reason}')
            flags += indicators(selected)
            if len(selected) < 100: flags.append('below_default_min_length')
            if not is_indonesian(selected, 0.20): flags.append('fails_default_language_heuristic')
            gate_pass = len(selected) >= 100 and is_indonesian(selected, 0.20)
            counters['default_quality_gate_pass_before_dedup' if gate_pass else 'default_quality_gate_fail'] += 1
            counters[f'text_source:{actual.text_source}'] += 1
            selected_pairs.append((audit_id, selected))
            topic = tuple(sorted(actual.topics)) or ('MISSING',)
            month = date.strftime('%Y-%m') if date else ('INVALID' if row['published_at'].strip() else 'MISSING')
            stratum = ('hoax', 'komdigi', topic, month, bucket(len(selected)), actual.text_source)
            key = json.dumps(stratum, ensure_ascii=False, separators=(',', ':'))
            strata[key].append(audit_id)
            matrix[key] += 1
        else:
            gate_pass = False
            counters['adapter_skipped'] += 1
        record = {'audit_id': audit_id, 'record_id': rid, 'csv_record_ordinal': number,
                  'source_id': row['id'], 'adapter_emitted': eligible,
                  'raw_body_clean_chars': len(body), 'adapter_full_chars': len(full),
                  'selected_chars': len(selected), 'selected_sha256': hashlib.sha256(selected.encode()).hexdigest(),
                  'text_source': ('claim' if cut else 'full') if eligible else None,
                  'cut_reason': reason if eligible else None, 'default_quality_gate_pass': gate_pass,
                  'reasons': sorted(set(flags)), 'review_status': 'pending_human_review'}
        records.append(record)
    assert next(adapted, None) is None
    selected_dups = duplicate_groups(selected_pairs)
    raw_dups = duplicate_groups(raw_pairs)
    for group in selected_dups.values():
        for audit_id in group: reasons['normalized_selected_duplicate'].append(audit_id)
    dup_ids = {i for group in selected_dups.values() for i in group}
    for record in records:
        if record['audit_id'] in dup_ids: record['reasons'].append('normalized_selected_duplicate')
        for flag in record['reasons']:
            if flag != 'normalized_selected_duplicate': reasons[flag].append(record['audit_id'])
    # Quarantine is all flagged metadata, not just samples. No automatic exclusion.
    with (output / 'records.jsonl').open('w') as handle:
        for record in records: handle.write(json.dumps(record, sort_keys=True) + '\n')
    with (output / 'quarantine-candidates.jsonl').open('w') as handle:
        for record in records:
            if record['reasons']: handle.write(json.dumps(record, sort_keys=True) + '\n')
    sampling = {'version': 'q008-sampling-v1', 'seed': SEED, 'limit_per_stratum': 5,
                'review_status': 'pending_human_review',
                'by_reason': {k: {'population': len(set(v)), 'audit_ids': sample(v)} for k, v in sorted(reasons.items())},
                'by_matrix_stratum': [{'stratum': json.loads(k), 'population': len(v), 'audit_ids': sample(v)} for k, v in sorted(strata.items())]}
    write_json(output / 'sampling.json', sampling)
    write_json(output / 'duplicates.json', {'normalized_selected': selected_dups, 'normalized_raw_body': raw_dups,
               'cross_label_conflicts': None, 'cross_label_conflict_status': 'NOT_ASSESSABLE_SINGLE_SOURCE_IMPLIED_LABEL',
               'near_duplicates': None, 'near_duplicate_status': 'NOT_MEASURED_EXACT_NORMALIZED_ONLY'})
    write_json(output / 'matrix.json', [{'dimensions': json.loads(k), 'count': n} for k, n in sorted(matrix.items())])
    stats = {'audit_version': VERSION, 'dataset_role': 'UPDATED_KAGGLE_SNAPSHOT_NOT_V1_TRAINING_REPRODUCTION',
             'provenance_basis': 'user reports Kaggle author update; dataset slug/version/retrieval time not supplied',
             'source_sha256': digest(source), 'source_bytes': source.stat().st_size,
             'columns': columns, 'extra_columns': sorted(set(columns)-EXPECTED_COLUMNS), 'raw_records': len(rows),
             'published_date_range': [min((r['published_at'] for r in rows if adapter._parse_datetime(r['published_at'])), default=None),
                                      max((r['published_at'] for r in rows if adapter._parse_datetime(r['published_at'])), default=None)],
             'category_counts': dict(sorted(Counter(r['category'] or 'MISSING' for r in rows).items())),
             'unique_source_ids': len(source_ids),
             'stage_counts': dict(sorted(counters.items())),
             'reason_counts': {k: len(set(v)) for k,v in sorted(reasons.items())},
             'length_characters': {field: length_stats([r[field] for r in records if r['adapter_emitted']])
                                   for field in ('raw_body_clean_chars', 'adapter_full_chars', 'selected_chars')},
             'length_quantile_method': 'sorted floor((n-1)*p), Unicode chars not tokens',
             'normalized_selected_duplicate_groups': len(selected_dups),
             'normalized_selected_duplicate_members': len(dup_ids),
             'normalized_selected_first_wins_excess': sum(len(g)-1 for g in selected_dups.values()),
             'normalized_raw_duplicate_groups': len(raw_dups),
             'quarantine_candidates': sum(bool(r['reasons']) for r in records),
             'verdict_indicator_by_text_source': dict(Counter(r['text_source'] for r in records if 'verdict_phrase' in r['reasons'])),
             'human_review_performed': False, 'label_changes': 0, 'record_deletions': 0}
    write_json(output / 'statistics.json', stats)
    print(json.dumps({'records': len(rows), 'emitted': counters['adapter_emitted'], 'sha256': digest(source),
                      'output': str(output)}, sort_keys=True))


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--source', type=Path, required=True)
    parser.add_argument('--output', type=Path, required=True)
    args = parser.parse_args()
    run(args.source, args.output)
