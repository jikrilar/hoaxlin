"""Offline Q-004 report from locked predictions; no fitting, inference or policy search.

Input JSON array: id, group_id, true_label, predicted_binary_label, confidence,
strata (nonempty object of string values). Group IDs must come from reviewed
claim/event grouping, never be invented by this reporter. This is reporting,
not an automated PROMOTE decision or a replacement for artifact validation.
"""
from __future__ import annotations

import argparse
from collections import defaultdict
import hashlib
import json
import math
from pathlib import Path
import random
import sys

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))
from scripts.evaluate_external_challenge import compute_abstention_metrics, compute_binary_metrics


def load_predictions(path: Path) -> list[dict]:
    rows = json.loads(path.read_text())
    if not isinstance(rows, list) or not rows:
        raise ValueError('Predictions must be a nonempty array')
    ids = set()
    dimensions = None
    for row in rows:
        if not isinstance(row, dict):
            raise ValueError('Prediction must be an object')
        for key in ['id', 'group_id']:
            if not isinstance(row.get(key), str) or not row[key].strip():
                raise ValueError(f'Missing {key}')
        if row['id'] in ids:
            raise ValueError('Duplicate prediction ID')
        ids.add(row['id'])
        if any(row.get(key) not in ('valid', 'hoax') for key in ['true_label', 'predicted_binary_label']):
            raise ValueError('Labels must be binary')
        value = row.get('confidence')
        if isinstance(value, bool) or not isinstance(value, (int, float)) or not math.isfinite(value) or not 0.5 <= value <= 1:
            raise ValueError('Invalid binary winning confidence')
        strata = row.get('strata')
        if not isinstance(strata, dict) or not strata or any(
            not isinstance(k, str) or not k.strip() or not isinstance(v, str) or not v.strip()
            for k, v in strata.items()
        ):
            raise ValueError('Nonempty string strata required')
        if dimensions is not None and set(strata) != dimensions:
            raise ValueError('Stratum dimensions must match on all rows')
        dimensions = set(strata)
    return sorted(rows, key=lambda r: r['id'])


def summarize(rows: list[dict], threshold: float) -> dict:
    binary = compute_binary_metrics(rows)
    selective = compute_abstention_metrics(rows, threshold)
    n = len(rows)
    covered = selective['covered_samples']
    matrix = binary['confusion_matrix']
    metrics = {k: binary[k] for k in ['accuracy', 'macro_f1']}
    metrics.update({
        'valid_to_hoax_fpr': matrix[0][1] / sum(matrix[0]) if sum(matrix[0]) else None,
        'coverage': covered / n,
        'covered_accuracy': selective['covered_accuracy'],
        'risk': 1 - selective['covered_accuracy'] if covered else None,
    })
    denominators = {'accuracy': n, 'macro_f1': n, 'coverage': n,
                    'covered_accuracy': covered, 'risk': covered, 'valid_to_hoax_fpr': sum(matrix[0])}
    for label in ['valid', 'hoax']:
        actual = [r for r in rows if r['true_label'] == label]
        predicted = [r for r in rows if r['predicted_binary_label'] == label]
        metrics[f'coverage_{label}'] = sum(r['confidence'] >= threshold for r in actual) / len(actual) if actual else None
        denominators[f'coverage_{label}'] = len(actual)
        for metric, denominator in [('precision', len(predicted)), ('recall', len(actual))]:
            metrics[f'{metric}_{label}'] = binary['per_class'][label][metric] if denominator else None
            denominators[f'{metric}_{label}'] = denominator
    groups = len({r['group_id'] for r in rows})
    per_label_groups = {label: len({r['group_id'] for r in rows if r['true_label'] == label}) for label in ['valid', 'hoax']}
    return {'sample_count': n, 'group_count': groups, 'binary': binary, 'selective': selective,
            'groups_per_true_label': per_label_groups,
            'metrics': metrics, 'denominators': denominators,
            'status': 'supported' if all(per_label_groups[l] >= 30 and binary['per_class'][l]['support'] >= 30 for l in ['valid', 'hoax']) else 'preliminary'}


def group_buckets(rows: list[dict]) -> list[list[list[dict]]]:
    groups = defaultdict(list)
    for row in rows:
        groups[row['group_id']].append(row)
    buckets = defaultdict(list)
    for group_id in sorted(groups):
        group = groups[group_id]
        buckets[tuple(sorted({r['true_label'] for r in group}))].append(group)
    return [buckets[key] for key in sorted(buckets)]


def interval(values: list[float], replicates: int) -> dict:
    values = sorted(values)
    def quantile(p):
        position = (len(values) - 1) * p
        lo, hi = math.floor(position), math.ceil(position)
        return values[lo] + (values[hi] - values[lo]) * (position - lo)
    return {'low': quantile(0.025) if values else None,
            'high': quantile(0.975) if values else None,
            'valid_replicates': len(values), 'requested_replicates': replicates}


def resamples(rows, count, seed):
    rng = random.Random(seed)
    buckets = group_buckets(rows)
    for _ in range(count):
        yield [row for bucket in buckets for _ in bucket for row in rng.choice(bucket)]


def report_slice(rows, threshold, replicates, seed):
    result = summarize(rows, threshold)
    samples = defaultdict(list)
    for draw in resamples(rows, replicates, seed):
        for key, value in summarize(draw, threshold)['metrics'].items():
            if value is not None:
                samples[key].append(value)
    result['confidence_intervals'] = {k: interval(samples[k], replicates) for k in result['metrics']}
    return result


def build_report(rows, threshold, replicates, seed, baseline=None):
    result = {
        'schema_version': '1.0.0', 'protocol_version': 'q004-v1',
        'threshold': threshold,
        'prediction_records_sha256': hashlib.sha256(json.dumps(rows, sort_keys=True, separators=(',', ':')).encode()).hexdigest(),
        'bootstrap': {'unit': 'group_id', 'stratification': 'group true-label set',
                      'method': 'percentile_linear_95', 'replicates': replicates, 'seed': seed},
        'overall': report_slice(rows, threshold, replicates, seed), 'strata': {},
        'eligibility': 'INSUFFICIENT_EVIDENCE',
        'eligibility_reason': 'Reporting only: reviewed/frozen manifests, baseline, calibration, RAG and integration gates must be verified separately.',
    }
    for dimension in sorted(rows[0]['strata']):
        result['strata'][dimension] = {
            value: report_slice([r for r in rows if r['strata'][dimension] == value], threshold, replicates, seed)
            for value in sorted({r['strata'][dimension] for r in rows})
        }
    if baseline is not None:
        other = {r['id']: r for r in baseline}
        if set(other) != {r['id'] for r in rows} or any(
            any(r[key] != other[r['id']][key] for key in ['group_id', 'true_label', 'strata']) for r in rows
        ):
            raise ValueError('Baseline must have identical IDs, groups, labels and strata')
        delta = result['overall']['binary']['macro_f1'] - compute_binary_metrics(baseline)['macro_f1']
        differences = [compute_binary_metrics(draw)['macro_f1'] - compute_binary_metrics([other[r['id']] for r in draw])['macro_f1']
                       for draw in resamples(rows, replicates, seed)]
        result['paired_comparison'] = {'macro_f1_delta': delta, 'confidence_interval': interval(differences, replicates)}
    return result


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--predictions', type=Path, required=True)
    parser.add_argument('--baseline', type=Path)
    parser.add_argument('--output', type=Path, required=True)
    parser.add_argument('--threshold', type=float, required=True)
    parser.add_argument('--bootstrap-replicates', type=int, default=2000)
    parser.add_argument('--seed', type=int, default=1729)
    args = parser.parse_args()
    if not math.isfinite(args.threshold) or not 0.5 <= args.threshold <= 1 or args.bootstrap_replicates < 100:
        parser.error('Threshold must be in [0.5,1]; at least 100 bootstrap replicates required')
    try:
        rows = load_predictions(args.predictions)
        baseline = load_predictions(args.baseline) if args.baseline else None
        result = build_report(rows, args.threshold, args.bootstrap_replicates, args.seed, baseline)
        args.output.write_text(json.dumps(result, indent=2, allow_nan=False) + '\n')
    except (OSError, ValueError, KeyError, TypeError) as exc:
        parser.exit(2, f'Protocol report blocked: {exc}\n')
    return 0


if __name__ == '__main__':
    raise SystemExit(main())
