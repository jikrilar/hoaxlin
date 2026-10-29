"""Offline summary only: no text, token IDs, or article previews in output."""
import hashlib
import importlib.metadata
import json
from pathlib import Path

from transformers import AutoTokenizer
from app.config import Settings
from app.inference import ModelRuntime
from train.sequence_policy import evaluate_candidate_policies, measure_dataset_truncation

release = Path('/app/models/indobert-hoax/v1.0.0')
challenge = Path('/workspace/datasets/challenge/external-challenge-v1/challenge.csv')
tokenizer = AutoTokenizer.from_pretrained(release, local_files_only=True)
runtime = ModelRuntime(Settings('audit', 'test', str(release), 'v1.0.0', None, 1, 20000, 512, True, True))
runtime.load()
assert runtime.ready, runtime.load_error
smoke = []
for text in ('Pemerintah tidak membagikan emas gratis kepada warga.', 'pengantar ' * 700):
    prediction = runtime._predict_sync(text)
    smoke.append({'characters': len(text), 'tokens': prediction.token_count,
                  'truncated': prediction.truncated, 'label': prediction.label,
                  'preprocessing_version': prediction.preprocessing_version})
print(json.dumps({
    'dataset_role': 'historical-regression-only-not-tuning-or-blind-holdout',
    'challenge_sha256': hashlib.sha256(challenge.read_bytes()).hexdigest(),
    'release_hashes': {name: hashlib.sha256((release / name).read_bytes()).hexdigest()
                       for name in ('model.safetensors', 'tokenizer.json', 'tokenizer_config.json', 'manifest.json')},
    'dependencies': {name: importlib.metadata.version(name) for name in ('torch', 'transformers', 'tokenizers', 'pydantic')},
    'truncation': measure_dataset_truncation(challenge, tokenizer),
    'synthetic_fixture_policy_summary_not_quality_metrics': evaluate_candidate_policies(tokenizer),
    'real_frozen_inference_smoke_not_accuracy_evaluation': smoke,
}, indent=2))
