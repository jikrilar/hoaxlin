"""Protocol reporting must retain denominators and resample event groups, not rows."""
import json
from pathlib import Path
import subprocess
import sys

import pytest

SCRIPT = Path(__file__).resolve().parents[1] / 'scripts/classifier_protocol_report.py'


def _rows():
    return [
        {'id': 'v1', 'group_id': 'event-v', 'true_label': 'valid', 'predicted_binary_label': 'valid',
         'confidence': 0.95, 'strata': {'source': 'source-a', 'topic': 'health'}},
        {'id': 'v2', 'group_id': 'event-v', 'true_label': 'valid', 'predicted_binary_label': 'hoax',
         'confidence': 0.6, 'strata': {'source': 'source-a', 'topic': 'health'}},
        {'id': 'h1', 'group_id': 'event-h', 'true_label': 'hoax', 'predicted_binary_label': 'hoax',
         'confidence': 0.9, 'strata': {'source': 'source-b', 'topic': 'health'}},
        {'id': 'h2', 'group_id': 'event-h', 'true_label': 'hoax', 'predicted_binary_label': 'valid',
         'confidence': 0.55, 'strata': {'source': 'source-b', 'topic': 'health'}},
    ]


def _run(tmp_path, rows, baseline=None, threshold='0.9'):
    path = tmp_path / 'predictions.json'
    path.write_text(json.dumps(rows))
    output = tmp_path / 'report.json'
    command = [sys.executable, str(SCRIPT), '--predictions', str(path), '--output', str(output),
               '--threshold', threshold, '--bootstrap-replicates', '200', '--seed', '1729']
    if baseline is not None:
        other = tmp_path / 'baseline.json'
        other.write_text(json.dumps(baseline))
        command.extend(['--baseline', str(other)])
    process = subprocess.run(command, capture_output=True, text=True)
    return process, output


def test_cli_reports_binary_all_rows_selective_denominators_and_strata(tmp_path):
    process, output = _run(tmp_path, _rows())
    assert process.returncode == 0, process.stderr
    result = json.loads(output.read_text())
    overall = result['overall']
    assert overall['sample_count'] == 4 and overall['group_count'] == 2
    assert overall['groups_per_true_label'] == {'valid': 1, 'hoax': 1}
    assert overall['binary']['confusion_matrix'] == [[1, 1], [1, 1]]
    assert overall['binary']['macro_f1'] == 0.5  # abstained errors still count
    assert overall['binary']['per_class']['valid']['precision'] == 0.5
    assert overall['selective']['covered_samples'] == 2
    assert overall['metrics']['risk'] == 0.0
    assert overall['metrics']['coverage'] == 0.5
    assert overall['metrics']['coverage_valid'] == overall['metrics']['coverage_hoax'] == 0.5
    assert overall['metrics']['valid_to_hoax_fpr'] == 0.5
    assert overall['denominators']['risk'] == 2
    assert overall['denominators']['coverage_valid'] == 2
    assert result['strata']['source']['source-a']['sample_count'] == 2
    assert result['strata']['source']['source-a']['group_count'] == 1
    assert result['strata']['source']['source-a']['status'] == 'preliminary'
    assert overall['confidence_intervals']['accuracy']['low'] == 0.5
    assert overall['confidence_intervals']['accuracy']['high'] == 0.5
    assert result['bootstrap']['unit'] == 'group_id'
    assert result['eligibility'] == 'INSUFFICIENT_EVIDENCE'


def test_cli_paired_identical_baseline_has_zero_delta_and_reproducible_ci(tmp_path):
    first, output = _run(tmp_path, _rows(), baseline=list(reversed(_rows())))
    assert first.returncode == 0, first.stderr
    expected = output.read_bytes()
    second, output = _run(tmp_path, _rows(), baseline=_rows())
    assert second.returncode == 0, second.stderr
    assert output.read_bytes() == expected
    result = json.loads(expected)
    assert result['paired_comparison']['macro_f1_delta'] == 0.0
    assert result['paired_comparison']['confidence_interval']['low'] == 0.0
    assert result['paired_comparison']['confidence_interval']['high'] == 0.0


def test_cli_all_abstained_has_undefined_risk_not_perfect_accuracy(tmp_path):
    process, output = _run(tmp_path, _rows(), threshold='1')
    assert process.returncode == 0, process.stderr
    report = json.loads(output.read_text())['overall']
    assert report['metrics']['risk'] is None
    assert report['metrics']['covered_accuracy'] is None
    assert report['denominators']['risk'] == 0
    assert report['confidence_intervals']['risk']['low'] is None
    assert report['metrics']['coverage'] == 0.0


@pytest.mark.parametrize('change', ['duplicate_id', 'missing_group', 'nan', 'confidence', 'label', 'baseline_group'])
def test_cli_rejects_invalid_or_unpaired_inputs_without_report(tmp_path, change):
    rows = _rows()
    baseline = None
    if change == 'duplicate_id':
        rows[1]['id'] = rows[0]['id']
    elif change == 'missing_group':
        del rows[0]['group_id']
    elif change == 'nan':
        rows[0]['confidence'] = float('nan')
    elif change == 'confidence':
        rows[0]['confidence'] = 1.1
    elif change == 'label':
        rows[0]['predicted_binary_label'] = 'meragukan'
    else:
        baseline = _rows()
        baseline[0]['group_id'] = 'wrong-event'
    process, output = _run(tmp_path, rows, baseline)
    assert process.returncode != 0
    assert 'Protocol report blocked:' in process.stderr
    assert not output.exists()
