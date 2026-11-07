# Hoax BERT Inference Service

FastAPI service used by Laravel for frozen `indobert-hoax v1.0.0` inference. The service starts without a model so liveness can be monitored; readiness returns `503` until the approved private release is configured. See the [main setup guide](../README.md#instalasi-docker) and [release contract](MODEL_RELEASE.md).

**Current project policy:** no BERT retraining/fine-tuning in the active roadmap. Further classifier dataset curation and benchmarks are deferred; RAG quality evaluation is the priority. `dataset/` and `train/` documentation below is retained as historical tooling reference, not a setup prerequisite or authorization to run it. Never overwrite v1.0.0 or reconstruct missing historical training data from reports.

## Requirements

- Python 3.10 or newer
- Python 3.12 matches the Docker/CI reference; other interpreters require compatible wheels
- Windows PowerShell examples are shown below

## Setup

From `bert-service/`:

```powershell
python -m venv .venv
.\.venv\Scripts\python.exe -m pip install --index-url https://download.pytorch.org/whl/cpu torch==2.13.0
.\.venv\Scripts\python.exe -m pip install -r requirements-runtime.txt
Copy-Item .env.example .env
```

If PowerShell prevents activation, the environment can be used directly:

```powershell
.\.venv\Scripts\python.exe -m pip install -r requirements-runtime.txt
```

Set the same private random secret in Laravel's `BERT_SERVICE_TOKEN` and this service's `.env`. `app/config.py` loads service `.env` before root `.env`; process environment takes precedence. Replace the template's old machine-specific model path and placeholder token. For Linux/Fish, use `.venv/bin/python`; activation is optional. Never source a dotenv file as shell code.

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

Without the approved release/configuration, the expected response is HTTP `503` with `model_unavailable`. Configure the approved local v1.0.0 release rather than training or downloading a substitute.

Version metadata remains available with or without a model:

```powershell
Invoke-RestMethod http://127.0.0.1:8001/version
```

## Dependency Verification

```powershell
.\.venv\Scripts\python.exe -m pip check
.\.venv\Scripts\python.exe -c "import fastapi, pydantic, torch, transformers, uvicorn; print(fastapi.__version__, torch.__version__, transformers.__version__)"
.\.venv\Scripts\python.exe -m compileall -q app
.\.venv\Scripts\python.exe -m pip install -r requirements-test.txt
.\.venv\Scripts\python.exe -m pytest -m 'not release'
```

## Model Serving (C4)

The exported model `models/indobert-hoax/v1.0.0/` is wired via `BERT_MODEL_PATH` with startup label-map verification.

**Configuration (`bert-service/.env`):**

```ini
BERT_MODEL_PATH=/absolute/private/path/indobert-hoax/v1.0.0
BERT_MODEL_VERSION=v1.0.0
BERT_SERVICE_TOKEN=<private-random-token-shared-with-laravel>
BERT_LOCAL_FILES_ONLY=true
BERT_REQUIRE_RELEASE_MANIFEST=true
```

Use an absolute platform-appropriate path (on Windows, for example a private drive path). `BERT_MODEL_PATH` is resolved at startup; `app/config.py` loads dotenv via a lightweight parser. Keep the token in sync with Laravel; do not use the placeholder literally.

**Label-map and release verification (`app/inference.py`):**

On `ModelRuntime.load()` the service asserts:

- `config.json` has `num_labels=2`, `id2label {0: valid, 1: hoax}`, `label2id {valid:0, hoax:1}` — any mismatch fails startup (`status failed`, readiness 503, `load_error` logged). This guarantees PRD's `valid`/`hoax` contract; `meragukan` is not a trained class but is derived via confidence threshold.

- Required files (`model.safetensors`, `config.json`, tokenizer) exist; weights and tokenizer are loaded with `local_files_only=true` by default.

- The approved release requires `threshold.json` (0.99) and `calibration.json` (T=0.8706620666110391); inference applies temperature scaling (`logits/T`) and abstention. Raw scores for `valid`/`hoax` are always returned.

- Strict packaged-release mode (Docker default) requires an internal `manifest.json` and verifies file checksums, known v1.0.0 weight identity, architecture, calibration/threshold and sequence contract. Legacy source-export manifest handling is not a replacement for release qualification.

**Health & version:**

```powershell
Invoke-RestMethod http://127.0.0.1:8001/health/live          # 200 ok
Invoke-RestMethod http://127.0.0.1:8001/health/ready         # 200 when ready, 503 model_unavailable otherwise
Invoke-RestMethod http://127.0.0.1:8001/version              # runtime model/status/labels/threshold/temperature + optional evaluation provenance
Invoke-RestMethod http://127.0.0.1:8001/health -Headers @{Authorization="Bearer $env:BERT_SERVICE_TOKEN"}
```

**Predict (requires Bearer token):**

```powershell
$headers = @{Authorization="Bearer $env:BERT_SERVICE_TOKEN"; "Content-Type"="application/json"}
Invoke-RestMethod -Method Post -Uri http://127.0.0.1:8001/predict -Headers $headers -Body '{"text":"Beredar unggahan di media sosial yang mengklaim bansos Rp 50 juta"}'
# Inspect the real response; do not assume a label/confidence from an example.
Invoke-RestMethod -Method Post -Uri http://127.0.0.1:8001/predict -Headers $headers -Body '{"text":"Jakarta (ANTARA) - Bank Indonesia mencatat pertumbuhan ekonomi 5,2 persen"}'
```

## Dataset Preparation (historical tooling — deferred)

Do not execute curation/crawling as part of installation. Original v1.0.0 raw and processed training data are unavailable from the checkout; retained code/defaults do not establish historical training provenance. Commands below describe tooling only and require a separate authorized task.

The `dataset/` package turns raw fact-check exports into a versioned, deduplicated,
leakage-safe training corpus. Pipeline stages: source adapters -> canonical
schema -> normalization -> label reconciliation + quality gates -> exact +
near-duplicate (MinHash LSH) dedup with claim-group clustering -> group-stratified
train/val/test split -> provenance manifest + data card.

This is an offline, file-based workflow. It reads explicitly supplied source files
and writes versioned artifacts under `datasets/processed/`; it does not read the
Laravel `datasets` table or synchronize records from the production admin catalog.
Any future use of catalog records would require a separate reviewed offline curation
workflow, which is not implemented by the application.

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

Historical artifact descriptions (not files guaranteed present): `komdigi-hoaks-v1/` (3832 hoax) for inspection;
`komdigi-antara-v1/` (7816 rows: 3832 hoax + 3984 valid, 6253/782/781 split,
stratified) for training.

Tests:

```powershell
.\.venv\Scripts\python.exe -m pytest -m 'not release'
```

## Fine-tuning (historical tooling — not active)

**No execution is authorized by this section.** Training/calibration/export is not an active roadmap item. In particular, the historical export command below must never be used to overwrite the frozen v1.0.0 release.

`train/` fine-tunes `indobenchmark/indobert-base-p1` with HuggingFace Trainer.
The configured classes are binary (`valid`, `hoax`); `meragukan` is derived later
from the calibrated confidence threshold and is not a trained class.

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
.\.venv\Scripts\python.exe -m train.pipeline evaluate   # writes held-out-test metrics to the versioned artifact
.\.venv\Scripts\python.exe -m train.pipeline export     # safe serialization to models/indobert-hoax/v1.0.0
```

Artifacts: `models/indobert-hoax/v1.0.0/` (model.safetensors, tokenizer, config
with id2label {0:valid,1:hoax}, calibration.json, threshold.json,
evaluation.json, MODEL_CARD.md) + `models/runs/indobert-hoax-eval/`
(evaluation_report.md, confusion_matrix.png, reliability_diagram.png).

Exporting an artifact does not activate it in production. The inference service
loads only the versioned path supplied through `BERT_MODEL_PATH`; release validation
and deployment are explicit operational steps.

**Current artifact note (indobert-hoax v1.0.0):** `evaluation.json` records the
held-out test split for dataset `komdigi-antara` v1.0.0 (`n=781`), including
accuracy, macro precision/recall/F1, confusion matrix, and calibration metrics.
These are offline evaluation metrics for that artifact, not production
accuracy or a guarantee for every input. The known limitation remains: valid
is long Antara news vs hoax is short claim
(`"Beredar unggahan..."` vs `"Jakarta (ANTARA) -"`). The held-out score is
inflated by style/length; short valid claims will score near the hoax side and
may fall to `meragukan` under the 0.99 threshold. Earlier proposals for short-valid
headlines and length-controlled hard negatives remain deferred, not active work.
