"""Recompute saved TF-IDF predictions only; missing fit data is not fabricated."""
import csv
import hashlib
import json
from pathlib import Path
import sys

sys.path.insert(0, '/workspace/bert-service')
from scripts.evaluate_tfidf_logreg_baseline import LABEL_TO_ID, compute_metrics, load_jsonl_split

root = Path('/workspace/datasets/challenge/external-challenge-v1/results')
result = {'task':'Q-003','measurement':'saved_prediction_files_only_not_new_fit','splits':{}}
for split in ['external','internal']:
    p = root / f'baseline_{split}_predictions.csv'
    with p.open(encoding='utf-8-sig',newline='') as f:
        rows = list(csv.DictReader(f))
    metrics = compute_metrics([LABEL_TO_ID[r['true_label']] for r in rows],
                              [LABEL_TO_ID[r['predicted_label']] for r in rows])
    old = json.loads((root / f'baseline_{split}_evaluation.json').read_text())
    for key in ['samples','accuracy','macro_f1','confusion_matrix']:
        assert metrics[key] == old[key], key
    result['splits'][split] = {'rows':len(rows),'prediction_sha256':hashlib.sha256(p.read_bytes()).hexdigest(),
                               'metrics':metrics,'matches_historical':True}
try:
    load_jsonl_split(Path('/workspace/datasets/processed/komdigi-antara-v1/train.jsonl'), 'train',6253)
except Exception as exc:
    result['new_fit_status'] = 'BLOCKED'
    result['new_fit_blocker'] = f'{type(exc).__name__}: {exc}'
Path('/out/tfidf-historical.json').write_text(json.dumps(result,indent=2)+'\n')
print(json.dumps(result,indent=2))
