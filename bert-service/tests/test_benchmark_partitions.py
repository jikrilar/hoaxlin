"""Synthetic partition checks; none of these rows are approved corpus data."""
from dataset.benchmark_partitions import validate_partitions, text_overlap


def row(rid, partition, text, claim=None, event=None, parent=None):
    return {'record_id': rid, 'partition': partition, 'claim': {'text': text},
            'claim_group': claim or rid, 'event_group': event or rid,
            'source': {'artifact_sha256': parent or rid, 'publisher': 'fixture'},
            'final_label': 'valid'}


def test_cross_source_event_and_parent_links_are_not_allowed_across_partitions():
    rows = [row('a', 'train', 'klaim berbeda satu', event='shared'),
            row('b', 'holdout', 'proposisi lain dua', event='shared'),
            row('c', 'dev', 'teks unik tiga', parent='article'),
            row('d', 'calibration', 'teks unik empat', parent='article')]
    result = validate_partitions(rows, [], check_review=False)
    assert {x['kind'] for x in result['violations']} >= {'event_group', 'parent_artifact'}
    assert not result['ready_to_freeze']


def test_exact_and_near_leak_and_legacy_challenge_are_detected():
    text = 'pemerintah tidak membagikan emas gratis kepada semua warga kota jakarta'
    rows = [row('a', 'train', text), row('b', 'dev', text.upper()),
            row('c', 'holdout', text.replace('jakarta', 'bandung'))]
    result = validate_partitions(rows, [{'record_id': 'old-1', 'text': text}], check_review=False)
    assert any(v['kind'] == 'exact_text' for v in result['violations'])
    assert any(v['kind'] == 'near_text' for v in result['violations'])
    assert result['legacy_overlap']


def test_small_benchmarks_and_unreviewed_records_cannot_freeze():
    result = validate_partitions([row('a', 'dev', 'teks')], [])
    assert not result['ready_to_freeze']
    assert result['unreviewed_records'] == ['a']
    assert result['coverage']['dev']['valid'] == 1
    assert 'minimum_reviewed_benchmark_counts_not_met' in result['blockers']


def test_contained_article_fragment_is_flagged_but_single_common_word_is_not():
    assert text_overlap('satu dua tiga empat lima enam tujuh', 'awal satu dua tiga empat lima enam tujuh akhir') == 'contained_fragment'
    assert text_overlap('banjir jakarta', 'banjir bandung') is None


def test_duplicate_record_ids_and_unknown_partition_fail_closed():
    result = validate_partitions([row('a', 'train', 'a'), row('a', 'test', 'b')], [], check_review=False)
    assert 'duplicate_record_ids' in result['blockers']
    assert 'unknown_or_missing_partition' in result['blockers']
