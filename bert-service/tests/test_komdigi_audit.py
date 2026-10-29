"""Audit tooling regression tests; synthetic fixtures, not corpus annotations."""
import importlib.util
from pathlib import Path

spec = importlib.util.spec_from_file_location('komdigi_audit', Path(__file__).resolve().parents[2] / 'reports/ai-quality/20261029-q008/komdigi-latest/audit.py')
audit = importlib.util.module_from_spec(spec)
spec.loader.exec_module(audit)


def test_cut_reason_matches_actual_early_marker_guard():
    assert audit.cut_reason('Faktanya informasi tersebut salah.') == 'earliest_marker_before_120'
    assert audit.cut_reason('Pengantar ' * 20 + 'Faktanya klaim salah.') == 'cut'
    assert audit.cut_reason('Tidak ada penanda dalam kalimat ini.') == 'no_marker'


def test_sampling_is_order_invariant_and_bounded():
    ids = ['komdigi:1', 'komdigi:2', 'komdigi:3', 'komdigi:4', 'komdigi:5', 'komdigi:6']
    assert audit.sample(ids) == audit.sample(list(reversed(ids)))
    assert len(audit.sample(ids)) == 5


def test_indicator_is_not_all_occurrences_of_word_hoax():
    assert 'verdict_phrase' not in audit.indicators('Beredar klaim hoaks di media sosial.')
    assert 'verdict_phrase' in audit.indicators('Faktanya klaim tersebut tidak benar.')


def test_duplicate_group_tracks_all_members_not_first_wins():
    groups = audit.duplicate_groups([('a', 'Klaim SAMA!'), ('b', 'klaim sama'), ('c', 'berbeda')])
    assert list(groups.values()) == [['a', 'b']]
