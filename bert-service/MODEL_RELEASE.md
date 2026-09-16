# BERT Model Release Contract

The canonical source export is immutable. `scripts/package_model_release.py`
copies its exact payload bytes into a new self-contained release and writes a
strict `manifest.json` inside that release. It never calls `save_pretrained()`
and refuses to overwrite an existing output directory.

## Package v1.0.0

From the project root on Windows:

```powershell
bert-service\.venv\Scripts\python.exe bert-service\scripts\package_model_release.py `
  --source models\indobert-hoax\v1.0.0 `
  --output artifacts\indobert-hoax\v1.0.0 `
  --version v1.0.0
```

Linux equivalent:

```bash
bert-service/.venv/bin/python bert-service/scripts/package_model_release.py \
  --source models/indobert-hoax/v1.0.0 \
  --output artifacts/indobert-hoax/v1.0.0 \
  --version v1.0.0
```

Generated files under `/artifacts/` are intentionally ignored by Git. Do not
stage the model weights or a portable archive in normal Git history.

## Validate a release

```powershell
bert-service\.venv\Scripts\python.exe bert-service\scripts\package_model_release.py `
  --validate-only artifacts\indobert-hoax\v1.0.0
```

Validation fails non-zero when a required file or manifest is missing, a size
or SHA-256 differs, an unexpected file is present, an absolute path leaks into
the manifest, or the architecture/labels/threshold/calibration contract does
not match Hoaxlin's verified classifier.

For release qualification, compare the source and packaged directories with
the same fixed inputs. The command requires at least three `--text` arguments,
uses CPU inference and `local_files_only=True`, and exits non-zero if labels or
scores differ beyond the requested tolerance:

```powershell
bert-service\.venv\Scripts\python.exe bert-service\scripts\compare_model_releases.py `
  --original models\indobert-hoax\v1.0.0 `
  --packaged artifacts\indobert-hoax\v1.0.0 `
  --text "First fixed qualification input" `
  --text "Second fixed qualification input" `
  --text "Third fixed qualification input"
```

The critical files are:

- `model.safetensors`
- `config.json`
- `tokenizer.json`
- `tokenizer_config.json`
- `threshold.json`
- `calibration.json`

The verified v1.0.0 export also contains `evaluation.json` and `MODEL_CARD.md`;
both are copied byte-for-byte and checksummed. `vocab.txt` and
`special_tokens_map.json` are not fabricated because the exported
`tokenizer.json` has been verified to load offline.

## Redistribution status

The base model repository identifies `indobenchmark/indobert-base-p1` as MIT.
The local dataset manifest does not establish redistribution rights for the
Komdigi and Antara-derived training data. Public redistribution of the
fine-tuned artifact is therefore **NOT VERIFIED**. Do not publish the release
or a public container image until that review is completed.

Until then, the recommended transfer mechanism is a private versioned image or
controlled `docker save`/`docker load` handoff. Publication is outside D1.

## Build the local inference image

D2 uses the repository root as an allowlisted build context. The selected
release is the only artifact directory admitted by the root `.dockerignore`:

```powershell
docker build `
  -f bert-service/Dockerfile `
  --build-arg MODEL_RELEASE_PATH=artifacts/indobert-hoax/v1.0.0 `
  --build-arg MODEL_VERSION=v1.0.0 `
  -t hoaxlin-bert:v1.0.0 `
  .
```

The image uses pinned Python 3.12, installs PyTorch from the CPU-only wheel
index, requires the embedded release manifest, and enables both Hugging Face
offline flags and `BERT_LOCAL_FILES_ONLY=true`. Its Docker healthcheck calls
`/health/ready`; `/health/live` remains a process-only diagnostic.

Supply authentication only when the container starts. Never use a real token
in an image build argument or Dockerfile environment instruction:

```powershell
docker run --rm `
  --network none `
  --env BERT_INTERNAL_API_TOKEN='<temporary-or-local-secret>' `
  hoaxlin-bert:v1.0.0
```

The image includes `/app/runtime_smoke_test.py` for isolated localhost checks
from inside the container. It validates readiness, version metadata,
authentication rejection, and the three frozen D1 inference fixtures without
printing the token.

## Controlled client transfer

Public distribution remains prohibited until redistribution rights are
verified. A controlled offline handoff can preserve the immutable tag:

```powershell
docker save --output artifacts/docker/hoaxlin-bert-v1.0.0.tar hoaxlin-bert:v1.0.0
docker load --input artifacts/docker/hoaxlin-bert-v1.0.0.tar
```

The `/artifacts/` path is ignored by normal Git. Communicate the archive's
SHA-256 through a trusted channel, and do not substitute `latest` for the
tested `v1.0.0` tag.
