# Hoax BERT Inference Service

FastAPI service used by Laravel for IndoBERT inference. Training is in `dataset/` + `train/` (see below); the service hosts the exported artifact at `models/indobert-hoax/v1.0.0/`. The service starts without a model so liveness can be monitored; readiness returns `503` until `BERT_MODEL_PATH` points to a compatible fine-tuned Hugging Face sequence-classification model with verified `id2label` {0:valid,1:hoax}.

## Requirements

- Python 3.10 or newer
- Python 3.12 or newer is recommended; the pinned stack has also been verified on Python 3.14
- Windows PowerShell examples are shown below

## Setup

From `bert-service/`:

```powershell
python -m venv .venv
.\.venv\Scripts\Activate.ps1
python -m pip install --upgrade pip
python -m pip install -r requirements.txt
Copy-Item .env.example .env
```

If PowerShell prevents activation, the environment can be used directly:

```powershell
.\.venv\Scripts\python.exe -m pip install -r requirements.txt
```

Set the same secret in Laravel's `BERT_SERVICE_TOKEN` and this service's `.env`. Environment variables are read by the process; load `.env` before starting when necessary:

```powershell
Get-Content .env | ForEach-Object {
    if ($_ -match '^\s*([^#][^=]*)=(.*)$') {
        [Environment]::SetEnvironmentVariable($matches[1].Trim(), $matches[2].Trim().Trim('"'), 'Process')
    }
}
```

## Start

Development server with reload:

```powershell
.\.venv\Scripts\python.exe -m uvicorn app.main:app --host 127.0.0.1 --port 8001 --reload
```

Production-style local process:

```powershell
.\.venv\Scripts\python.exe -m uvicorn app.main:app --host 127.0.0.1 --port 8001
```

## Health Checks

Liveness confirms the FastAPI process is running:

```powershell
Invoke-RestMethod http://127.0.0.1:8001/health/live
```

Expected without a configured model:

```json
{"status":"ok"}
```

Readiness confirms that the IndoBERT model is loaded:

```powershell
Invoke-WebRequest http://127.0.0.1:8001/health/ready -SkipHttpErrorCheck
```

Before model training/configuration, the expected response is HTTP `503` with `model_unavailable`. After setting `BERT_MODEL_PATH` to a compatible local model, it should return HTTP `200`.

Version metadata remains available with or without a model:

```powershell
Invoke-RestMethod http://127.0.0.1:8001/version
```

## Dependency Verification

```powershell
.\.venv\Scripts\python.exe -m pip check
.\.venv\Scripts\python.exe -c "import fastapi, pydantic, torch, transformers, uvicorn; print(fastapi.__version__, torch.__version__, transformers.__version__)"
.\.venv\Scripts\python.exe -m compileall -q app
.\.venv\Scripts\python.exe -m pytest -q
```

## Model Serving (C4)

The exported model `models/indobert-hoax/v1.0.0/` is wired via `BERT_MODEL_PATH` with startup label-map verification.

**Configuration (`bert-service/.env`):**

```ini
BERT_MODEL_PATH=C:/xampp/htdocs/hoax-detector/models/indobert-hoax/v1.0.0
BERT_MODEL_VERSION=v1.0.0
BERT_SERVICE_TOKEN=local-dev-token-change-me
```

`BERT_MODEL_PATH` is resolved at startup; `app/config.py:13` loads `bert-service/.env` and project `.env` via a lightweight dotenv parser (no extra dependency). Keep `BERT_SERVICE_TOKEN` in sync with Laravel's `BERT_SERVICE_TOKEN` (`.env`).

**Label-map verification (`app/inference.py:111`):**

On `ModelRuntime.load()` the service asserts:

- `config.json` has `num_labels=2`, `id2label {0: valid, 1: hoax}`, `label2id {valid:0, hoax:1}` — any mismatch fails startup (`status failed`, readiness 503, `load_error` logged). This guarantees PRD's `valid`/`hoax` contract; `meragukan` is not a trained class but is derived via confidence threshold.

- Required files (`model.safetensors`, `config.json`, tokenizer) exist; weights and tokenizer are loaded with `local_files_only=true` by default.

- Sidecars `threshold.json` (0.99) and `calibration.json` (T=0.87) are loaded if present; inference applies temperature scaling (`logits/T`) and threshold logic per `MODEL_CARD.md`: `label = id2label[argmax] if confidence >= threshold else "meragukan"`. Raw scores for `valid`/`hoax` are always returned.

- Checksum verification against `models/indobert-hoax/manifest.json` (SHA-256 per file) if manifest is present — mismatch fails startup.

**Health & version:**

```powershell
Invoke-RestMethod http://127.0.0.1:8001/health/live          # 200 ok
Invoke-RestMethod http://127.0.0.1:8001/health/ready         # 200 when ready, 503 model_unavailable otherwise
Invoke-RestMethod http://127.0.0.1:8001/version              # {service, service_version, model_version, model_status, model_labels:[valid,hoax], threshold:0.99, temperature:0.87}
Invoke-RestMethod http://127.0.0.1:8001/health -Headers @{Authorization="Bearer $env:BERT_SERVICE_TOKEN"}
```

**Predict (requires Bearer token):**

```powershell
$headers = @{Authorization="Bearer local-dev-token-change-me"; "Content-Type"="application/json"}
Invoke-RestMethod -Method Post -Uri http://127.0.0.1:8001/predict -Headers $headers -Body '{"text":"Beredar unggahan di media sosial yang mengklaim bansos Rp 50 juta"}'
# -> {"label":"hoax","confidence_score":0.999,"raw_scores":{"valid":0.0,"hoax":0.999}, "model_version":"v1.0.0", ...}
# Short valid news below threshold returns "meragukan":
Invoke-RestMethod -Method Post -Uri http://127.0.0.1:8001/predict -Headers $headers -Body '{"text":"Jakarta (ANTARA) - Bank Indonesia mencatat pertumbuhan ekonomi 5,2 persen"}'
# -> {"label":"meragukan","confidence_score":0.974, ...}
```

## Dataset Preparation (C2)

The `dataset/` package turns raw fact-check exports into a versioned, deduplicated,
leakage-safe training corpus. Pipeline stages: source adapters -> canonical
schema -> normalization -> label reconciliation + quality gates -> exact +
near-duplicate (MinHash LSH) dedup with claim-group clustering -> group-stratified
train/val/test split -> provenance manifest + data card.

Run against the prepared Komdigi export:

```powershell
.\.venv\Scripts\python.exe -m dataset.prepare `
  --source ..\datasets\komdigi_hoaks.csv `
  --out ..\datasets\processed\komdigi-hoaks-v1
```

Artifacts: `train.jsonl`, `val.jsonl`, `test.jsonl`, `all.jsonl`,
`rejected.jsonl`, `manifest.json` (provenance + hashes), `data_card.md`.
Loadable directly with HuggingFace `load_dataset("json", data_files=...)`.

Options:

- `--split-mode group|time` — `group` = greedy group-stratified (label-balanced,
  claim groups never cross splits); `time` = newest ~10% held out as test to
  simulate deployment drift.
- `--extra-source <labeled.csv>` — add another labeled corpus (columns `text`,
  `label`) for the `valid` class; labels are reconciled from aliases
  (`valid/benar/fakta/true` -> `valid`, `hoaks/salah/keliru/false` -> `hoax`).
- `--train-ratio/--val-ratio/--test-ratio`, `--seed`, `--min-text-len`,
  `--min-id-ratio`, `--min-similarity` (near-dup Jaccard), `--n-permutations`,
  `--n-bands`.

Methodology: Komdigi articles are cut at the first verdict/evidence marker
("Faktanya", "dilansir dari", ...) so verdict boilerplate never leaks into the
training text; near-duplicate claims form `claim_group`s that are kept intact
across splits.

Valid-news sidecar: `scripts/build_valid_news.py` crawls Antara News category
indexes (respectful rate, checkpointed) and extracts bodies from
`.post-content`. Output `datasets/valid_antara.csv` (text, label=valid).

Combined run for fine-tuning:

```powershell
.\.venv\Scripts\python.exe -m dataset.prepare `
  --source ..\datasets\komdigi_hoaks.csv `
  --extra-source ..\datasets\valid_antara.csv `
  --out ..\datasets\processed\komdigi-antara-v1
```

Current artifacts: `komdigi-hoaks-v1/` (3832 hoax) for inspection;
`komdigi-antara-v1/` (7816 rows: 3832 hoax + 3984 valid, 6253/782/781 split,
stratified) for training.

Tests:

```powershell
.\.venv\Scripts\python.exe -m pytest
```

## Fine-tuning (C3)

`train/` fine-tunes `indobenchmark/indobert-base-p1` with HuggingFace Trainer.

Install training extras:

```powershell
.\.venv\Scripts\python.exe -m pip install datasets accelerate matplotlib
# or
.\.venv\Scripts\python.exe -m pip install -e ".[train]"
```

Full pipeline (train -> calibrate -> threshold -> evaluate -> export):

```powershell
.\.venv\Scripts\python.exe -m train.pipeline
```

Single steps (resumable via `models/runs/pipeline_state.json`):

```powershell
.\.venv\Scripts\python.exe -m train.pipeline train       # Trainer, best by val macro-F1, early stopping
.\.venv\Scripts\python.exe -m train.pipeline calibrate  # temperature scaling on validation (T=0.87)
.\.venv\Scripts\python.exe -m train.pipeline threshold  # meragukan threshold 0.99 on validation
.\.venv\Scripts\python.exe -m train.pipeline evaluate   # held-out test: accuracy 1.0, per-class P/R/F1 1.0, confusion [[398,0],[0,383]], ECE 0.0007
.\.venv\Scripts\python.exe -m train.pipeline export     # safe serialization to models/indobert-hoax/v1.0.0
```

Artifacts: `models/indobert-hoax/v1.0.0/` (model.safetensors, tokenizer, config
with id2label {0:valid,1:hoax}, calibration.json, threshold.json,
evaluation.json, MODEL_CARD.md) + `models/runs/indobert-hoax-eval/`
(evaluation_report.md, confusion_matrix.png, reliability_diagram.png).

**Known limitation:** valid is long Antara news vs hoax is short claim
(`"Beredar unggahan..."` vs `"Jakarta (ANTARA) -"`). Held-out 1.0 is inflated
by style/length; short valid claims will score near the hoax side and may fall
to `meragukan` under the 0.99 threshold. Future work: add short valid headlines
and length-controlled hard negatives.
