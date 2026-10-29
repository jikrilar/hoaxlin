"""Q-003 measurement only: reuse existing evaluator, never train/tune or emit text."""
import csv
import hashlib
import importlib.metadata as metadata
import json
from pathlib import Path
import resource
import sys
import time

sys.path.insert(0, '/workspace/bert-service')
from app.model_release import validate_release
from app.config import Settings
from app.inference import ModelRuntime
from scripts.evaluate_external_challenge import (
    CORPUS_PATH, EvaluationError, _load_runtime, compute_abstention_metrics,
    compute_binary_metrics, infer_rows, validate_dataset_quality, validate_frozen_dataset,
    validate_model_metadata,
)

start = time.monotonic()
root = Path('/workspace/datasets/challenge/external-challenge-v1')
release = Path('/app/models/indobert-hoax/v1.0.0')
dataset = validate_frozen_dataset(root / 'challenge.csv', root / 'manifest.json')
validated = validate_release(release, expected_version='v1.0.0')
blocked = {}
for name, check in [
    ('training_overlap_revalidation', lambda: validate_dataset_quality(dataset, CORPUS_PATH)),
    ('legacy_evaluator_model_metadata', lambda: validate_model_metadata(release)),
]:
    try:
        check()
    except EvaluationError as exc:
        blocked[name] = str(exc)

with (root / 'results/predictions.csv').open(encoding='utf-8-sig', newline='') as handle:
    historical = list(csv.DictReader(handle))
assert [row['id'] for row in historical] == [row['id'] for row in dataset.rows]
assert all(old['text'] == row['text'] and old['true_label'] == row['label']
           for old, row in zip(historical, dataset.rows))
old_binary = compute_binary_metrics(historical)
old_abstention = compute_abstention_metrics(historical, 0.99)
stored_binary = json.loads((root / 'results/binary_evaluation.json').read_text())
stored_abstention = json.loads((root / 'results/abstention_evaluation.json').read_text())
for key in ['accuracy', 'macro_f1', 'confusion_matrix', 'samples']:
    assert old_binary[key] == stored_binary[key], key
for key in ['covered_samples', 'abstained_samples', 'coverage', 'covered_accuracy']:
    assert old_abstention[key] == stored_abstention[key], key

import torch
torch.set_num_threads(2)
torch.manual_seed(42)
try:
    runtime = _load_runtime(release)
except EvaluationError as exc:
    blocked['legacy_evaluator_runtime_loader'] = str(exc)
    # Existing strict release path, not a checksum bypass or rewritten manifest.
    runtime = ModelRuntime(Settings(
        service_name='q003-isolated-baseline', service_version='q003',
        model_path=str(release), model_version='v1.0.0', internal_api_token=None,
        max_concurrency=1, max_text_length=20000, max_sequence_length=512,
        local_files_only=True, require_release_manifest=True,
    ))
    runtime.load()
    assert runtime.ready, runtime.load_error
load_seconds = time.monotonic() - start
inference_start = time.monotonic()
predictions = infer_rows(runtime, dataset.rows, batch_size=1)
elapsed = time.monotonic() - inference_start
binary = compute_binary_metrics(predictions)
abstention = compute_abstention_metrics(predictions, 0.99)
diffs = []
for old, new in zip(historical, predictions):
    delta = max(abs(float(old[k]) - new[k]) for k in ['prob_valid', 'prob_hoax'])
    diffs.append({'id': new['id'], 'max_score_delta': delta,
                  'binary_label_changed': old['predicted_binary_label'] != new['predicted_binary_label'],
                  'served_label_changed': old['served_label'] != new['served_label']})
safe_predictions = [{
    k: row[k] for k in ['id', 'true_label', 'predicted_binary_label', 'prob_valid',
                       'prob_hoax', 'confidence', 'correct', 'served_label', 'abstained']
} | {'text_sha256': hashlib.sha256(row['text'].encode()).hexdigest()} for row in predictions]
result = {
    'task': 'Q-003', 'measurement': 'real_cpu_model_inference_not_full_legacy_runner',
    'status': 'PARTIAL_BLOCKED', 'blocked_checks': blocked,
    'dataset': {'raw_sha256': dataset.raw_sha256, 'canonical_sha256': dataset.canonical_sha256,
                'legacy_sha256': dataset.sha256, 'rows': len(dataset.rows)},
    'release_validation': validated,
    'effective_config': {'seed': 42, 'batch_size': 1, 'cpu_threads': 2, 'max_sequence_length': 512,
                         'max_text_length': 20000, 'threshold': runtime.threshold,
                         'temperature': runtime.temperature, 'local_files_only': True,
                         'preprocessing_version': 'unversioned_existing_serving',
                         'tokenizer_upstream_revision': None},
    'historical_prediction_recalculation': {'binary': old_binary, 'abstention': old_abstention},
    'new_inference': {'binary': binary, 'abstention': abstention, 'predictions': safe_predictions},
    'comparison_to_historical': {
        'max_probability_absolute_delta': max(d['max_score_delta'] for d in diffs),
        'binary_label_changes': sum(d['binary_label_changed'] for d in diffs),
        'served_label_changes': sum(d['served_label_changed'] for d in diffs),
        'per_record': diffs,
    },
    'runtime': {'load_and_validation_seconds': load_seconds, 'inference_seconds': elapsed,
                'total_seconds': time.monotonic()-start, 'max_rss_kib': resource.getrusage(resource.RUSAGE_SELF).ru_maxrss},
    'dependencies': {p: metadata.version(p) for p in ['torch', 'transformers', 'tokenizers', 'numpy']},
    'limits': ['No fresh training-overlap validation without actual training corpus.',
               'Historical challenge is known, not a new blind holdout.',
               'No calibration, threshold search, fitting or model selection performed.'],
}
Path('/out/bert-baseline.json').write_text(json.dumps(result, indent=2)+'\n')
print(json.dumps({'samples':len(predictions), 'binary':binary, 'abstention':abstention,
                  'max_score_delta':result['comparison_to_historical']['max_probability_absolute_delta'],
                  'blocked_checks':blocked, 'runtime':result['runtime']}, indent=2))
