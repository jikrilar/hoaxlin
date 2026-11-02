"""Synthetic metric contracts only, not reviewed relevance judgments."""
import math
import sys
from pathlib import Path

import pytest

sys.path.insert(0, str(Path(__file__).resolve().parents[2]))
from scripts.rag_retrieval_metrics import GradedCase, calculate_graded_metrics, MetricInputError


def test_returned_fixed_direct_and_ndcg_denominators():
    case = GradedCase('q', 'claim', True, {'a': 3, 'b': 1, 'c': 0}, True)
    result = calculate_graded_metrics([case], {'q': ['a', 'a', 'b']}, (3,))
    m = result['metrics']
    assert m['returned_precision@3'] == 0.5
    assert m['fixed_precision@3'] == pytest.approx(1/3, abs=1e-6)
    assert m['direct_evidence_precision@3'] == 0.5
    assert m['answerable_recall@3'] == 1
    assert m['ndcg@3'] == 1


def test_unjudged_is_not_grade_zero_and_marks_report_preliminary():
    case = GradedCase('q', 'claim', True, {'a': 3}, False)
    result = calculate_graded_metrics([case], {'q': ['unknown', 'a']}, (3,))
    assert result['status'] == 'preliminary'
    assert result['metrics']['returned_precision@3'] is None
    assert result['metrics']['ndcg@3'] is None
    assert result['metrics']['unjudged_rate@3'] == 0.5
    assert result['per_query']['q']['@3']['grades'] == [None, 3]


def test_no_answer_is_excluded_from_recall_and_always_empty_cannot_win():
    cases = [GradedCase('a', 'claim', True, {'d': 3}, True),
             GradedCase('n', 'none', False, {'d': 0}, True)]
    r = calculate_graded_metrics(cases, {'a': [], 'n': []}, (3,))
    assert r['metrics']['answerable_recall@3'] == 0
    assert r['metrics']['answerable_empty_rate@3'] == 1
    assert r['metrics']['no_answer_false_reference_rate@3'] == 0
    assert r['metrics']['ndcg@3'] == 0
    r = calculate_graded_metrics(cases, {'a': ['d'], 'n': ['d']}, (3,))
    assert r['metrics']['no_answer_false_reference_rate@3'] == 1


@pytest.mark.parametrize('case', [GradedCase('q', 'x', True, {}, True),
    GradedCase('q', 'x', False, {'a': 2}, True), GradedCase('q', 'x', True, {'a': True}, True)])
def test_invalid_judgments_rejected(case):
    with pytest.raises(MetricInputError): calculate_graded_metrics([case], {'q': []})


def test_wrong_results_and_reversed_grades_have_honest_scores():
    c = GradedCase('q', 'claim', True, {'a':3, 'b':2, 'x':0}, True)
    r = calculate_graded_metrics([c], {'q':['x']}, (3,))
    assert r['metrics']['returned_precision@3'] == 0
    assert r['metrics']['answerable_hit@3'] == 0
    r = calculate_graded_metrics([c], {'q':['b','a']}, (3,))
    assert 0 < r['metrics']['ndcg@3'] < 1
    assert r['metrics']['direct_evidence_precision@3'] == 0.5


def test_null_grade_no_answer_requires_explicit_flag_empty_cases_and_ks_rejected():
    for case in [GradedCase('q','x', None, {}, True), GradedCase('q','x',False,{'a':None},False)]:
        with pytest.raises(MetricInputError): calculate_graded_metrics([case], {'q':[]})
    with pytest.raises(MetricInputError): calculate_graded_metrics([], {})
    with pytest.raises(MetricInputError): calculate_graded_metrics([GradedCase('q','x',False,{},True)], {'q':[]}, (True,))
