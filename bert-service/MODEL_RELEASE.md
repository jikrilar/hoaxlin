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
