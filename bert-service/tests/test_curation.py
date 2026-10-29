"""Synthetic safety tests, not human-reviewed annotations."""
import copy

import pytest

from dataset.curation import review_gate, stage_candidates, normalized_conflicts


def test_unreviewed_claim_cannot_be_exported():
    reasons = review_gate({'review_status': 'proposed', 'final_label': None})
    assert 'not_approved' in reasons
    assert 'two_distinct_human_reviews_required' in reasons


def test_duplicate_label_conflicts_keep_both_ids():
    rows = [{'record_id': 'a', 'claim': {'text': 'Tidak benar!'}, 'final_label': 'valid'},
            {'record_id': 'b', 'claim': {'text': 'tidak benar'}, 'final_label': 'hoax'}]
    assert normalized_conflicts(rows) == [['a', 'b']]
    assert len(rows) == 2


def test_low_confidence_or_ai_or_duplicate_reviewer_never_passes():
    row = {'review_status': 'approved', 'final_label': 'valid', 'benchmark_eligible': True,
           'origin': 'observed_source', 'disposition': 'binary_candidate',
           'reviews': [{'reviewer_id': 'one', 'actor_type': 'ai', 'label': 'valid', 'confidence': 'high'},
                       {'reviewer_id': 'one', 'actor_type': 'human', 'label': 'valid', 'confidence': 'low'}]}
    assert 'two_distinct_human_reviews_required' in review_gate(row)


def test_readiness_checks_evidence_and_consensus_for_both_labels():
    for label, relation in [('valid', 'supports'), ('hoax', 'refutes')]:
        row = {'review_status': 'approved', 'final_label': label, 'benchmark_eligible': True,
               'origin': 'observed_source', 'disposition': 'binary_candidate',
               'source': {'artifact_sha256': 'a' * 64, 'rights_status': 'authorized_private'},
               'justification': 'Synthetic test rationale, not a corpus approval.',
               'claim_group': 'c', 'event_group': 'e', 'context': {'valid_as_of': '2026-10-29'},
               'reviews': [{'reviewer_id': who, 'actor_type': 'human', 'label': label,
                            'confidence': 'high', 'justification': 'fixture', 'reviewed_at': '2026-10-29T00:00:00Z'}
                           for who in ('fixture-1', 'fixture-2')],
               'evidence': [{'relation': relation, 'url': 'https://example.test/evidence',
                             'publisher': 'fixture', 'title': 'fixture', 'accessed_at': '2026-10-29',
                             'locator': 'section', 'excerpt': 'fixture'}]}
        assert review_gate(row) == []
        bad = copy.deepcopy(row)
        bad['reviews'][1]['label'] = 'hoax' if label == 'valid' else 'valid'
        assert 'disagreement_requires_authenticated_adjudication' in review_gate(bad)
        bad = copy.deepcopy(row)
        bad['evidence'][0]['url'] = 'javascript:alert(1)'
        assert 'missing_relevant_evidence' in review_gate(bad)
        bad = copy.deepcopy(row)
        bad['reviews'][1]['confidence'] = 'low'
        assert 'two_distinct_human_reviews_required' in review_gate(bad)


def test_staging_uses_adapter_but_never_approves_or_exposes_text_in_summary(tmp_path):
    import csv
    from dataset.sources.komdigi_csv import EXPECTED_COLUMNS
    source = tmp_path / 'source.csv'
    body = 'Beredar klaim bahwa pemerintah tidak pernah mengubah dosis 5 mg pada tanggal tersebut. ' * 3 + 'Faktanya klaim tersebut salah.'
    row = dict.fromkeys(sorted(EXPECTED_COLUMNS), '')
    row.update(id='123', title='Fixture', body_text=body, url='https://example.test/123',
               published_at='2026-01-01', category='Klarifikasi Hoaks')
    with source.open('w') as handle:
        writer = csv.DictWriter(handle, fieldnames=sorted(EXPECTED_COLUMNS))
        writer.writeheader(); writer.writerow(row)
    output = tmp_path / 'new-version'
    summary = stage_candidates(source, output, 'test-v1')
    import json
    staged = json.loads((output / 'candidates.jsonl').read_text().splitlines()[0])
    assert 'tidak' in staged['claim']['text'] and '5 mg' in staged['claim']['text']
    assert 'Faktanya' not in staged['claim']['text']
    assert staged['source']['artifact_sha256']
    assert staged['source']['rights_status'] == 'pending'
    assert staged['reviews'] == [] and staged['final_label'] is None
    assert staged['benchmark_eligible'] is False
    assert review_gate(staged)
    assert summary['approved_records'] == 0
    assert 'Beredar' not in json.dumps(summary)
    assert (output / 'benchmark.jsonl').read_text() == ''
    provenance = json.loads((output / 'provenance.jsonl').read_text().splitlines()[0])
    assert provenance['parent_csv_sha256']
    assert provenance['source_id'] == '123'
    assert provenance['source_implied_label'] == 'hoax'
    with pytest.raises(FileExistsError):
        stage_candidates(source, output, 'test-v1')
