"""Verify migration provenance without model loading or emitting dataset text."""
import ast
import csv
from datetime import datetime, timezone
import hashlib
import io
import json
from pathlib import Path
import subprocess
import sys

sys.path.insert(0, str(Path('bert-service').resolve()))
from scripts.evaluate_external_challenge import validate_frozen_dataset

ROOT = Path('datasets/challenge/external-challenge-v1')
SOURCE = '6979509'
original = subprocess.check_output(['git', 'show', f'{SOURCE}:{ROOT}/challenge.csv'])
current = (ROOT / 'challenge.csv').read_bytes()
assert original == current
historical = current.replace(b'\n', b'\r\n')
parse = lambda raw: list(csv.reader(io.StringIO(raw.decode('utf-8-sig'), newline='')))
assert parse(historical) == parse(original)
assert hashlib.sha256(historical).hexdigest() == '8ff83e37e7db10b0ab231618c6834d45325a9aacb2676ae0fcd2069b30e51708'
assert hashlib.sha256(current).hexdigest() == 'd9237395948dc42cbcfec328a4f2a74bbff73d66537bced27ef4746b76c32f7b'
frozen = validate_frozen_dataset(ROOT / 'challenge.csv', ROOT / 'manifest.json')
q001 = json.loads(Path('reports/ai-quality/20261029-q001/inventory.json').read_text())
preserved = {}
for name, info in q001['files'].items():
    if name in {str(ROOT / 'manifest.json'), '.gitattributes'}:
        continue
    actual = hashlib.sha256(Path(name).read_bytes()).hexdigest()
    assert actual == info['sha256'], name
    preserved[name] = actual
changed = subprocess.check_output(['git', 'diff', SOURCE, '--name-only'], text=True).splitlines()
for name in changed:
    if name.endswith('.py'):
        ast.parse(Path(name).read_text())
records = parse(current)
record_digest = hashlib.sha256(json.dumps(records, ensure_ascii=False, separators=(',', ':')).encode()).hexdigest()
result = {
    'task': 'Q-002', 'source_commit': subprocess.check_output(['git', 'rev-parse', SOURCE], text=True).strip(),
    'observed_at_utc': datetime.now(timezone.utc).isoformat(),
    'policy': 'csv-utf8-bom-lf-v1', 'rows': len(frozen.rows),
    'raw_sha256': frozen.raw_sha256, 'canonical_sha256': frozen.canonical_sha256,
    'historical_raw_sha256': frozen.sha256,
    'ordered_csv_arrays_json_sha256': record_digest,
    'proof': {'csv_bytes_equal_q001': True, 'all_ordered_fields_equal_reconstructed_crlf': True,
              'q001_files_preserved_except_manifest_and_gitattributes': len(preserved)},
    'preserved_file_sha256': preserved,
    'new_metric_measurement': False, 'new_inference_samples': 0,
    'seed': None, 'quality_metrics': None,
    'artifact_sha256': {str(p): hashlib.sha256(p.read_bytes()).hexdigest() for p in [
        ROOT / 'manifest.json', Path('bert-service/scripts/evaluate_external_challenge.py'),
        Path('bert-service/scripts/evaluate_tfidf_logreg_baseline.py'),
        Path('bert-service/scripts/build_external_challenge.py'),
    ]},
}
print(json.dumps(result, indent=2))
