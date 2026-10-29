# Q-006 — Versioned preprocessing and sequence policy

Status: **DONE — input contract and bounded policy/fixture evaluation only**.
Base: `40fa7106fb2510864e0611ab887196af1c1ad7dd`, branch
`quality/02-input-contract`. No retraining, holdout tuning, download, deployment,
push, migration, or changes to frozen v1.0.0 payload/historical reports.
The two owner files remain untracked and unchanged (hashes in verification.json).

## Findings and implementation

- Shared `app/preprocessing.py` implements preprocessing 1.0.0: no text rewrite,
  right truncation, max length including special tokens. Actual training,
  evaluation and runtime encoding entrypoints use it. The audit invokes those
  entrypoints with the same frozen tokenizer, not three duplicated tokenizer
  calls. At the same 256-token budget all eight fixtures match; at training 256
  versus legacy serving 512, long inputs match only up to the retained prefix,
  **not** as full sequences. Training padding is excluded from active comparison.
- Training max_length remains 256, historical serving fallback remains 512.
  No claim that frozen weights were trained at 512. Config rejects unimplemented
  preprocessing/truncation/multi-claim policies. Original training tokenizer
  revision/artifacts are unavailable: parity with the original historical
  training run remains unproven. Tests use the actual local release tokenizer,
  local_files_only, and skip explicitly rather than downloading a replacement.
- Export stores effective sequence fields and tokenizer/config hashes. Packaging
  preserves source-export fields (including 256), rather than overwriting with
  512. Legacy export packaging explicitly records
  `legacy-serving-512-not-training-provenance` in provenance.
- Manifest schema 1.1 requires complete implemented contract fields, matching
  tokenizer/config hashes, consistent max_token_length and max_sequence_length,
  integer length 2–512 (not bool). Rejects claim-selection and window pooling as
  serving policies: they are experimental only. Schema 1.0 is retained with an
  explicit legacy 512 head-only compatibility contract; frozen manifests unchanged.
- Runtime uses release length. An explicit env/direct-Settings mismatch fails
  load; an unset env default defers to the release. Token count is observed before
  token truncation; `/predict` reports token_count/truncated/preprocessing_version
  and `/version` reports effective sequence metadata. Real local TestClient
  inference checks both short and truncated inputs through authenticated endpoint.
- Laravel keeps the existing 50–50,000 submission acceptance limit and original
  text. Only classifier-bound analysis input above the service limit is
  trim/head-bounded to 20,000 Unicode characters. Whitespace cannot bypass this
  boundary. Configurable smaller caps are named with the **actual** cap in
  `classifier-input:trim-head-<limit>-v1`; invalid/over-20,000 caps fail safely.
  Private event trace keeps exact bounded hash, original parent hash, character
  truncation, service-observed token count/truncation and preprocessing version.
  No raw text added to logs/public trace. DTO rejects malformed metadata and
  preserves metadata on threshold/cache copies. Character count is not tokens.
- This is a bounded head policy, **not** claim-aware full-article verification.
  User-facing truncation disclosure is not added here; discarded trailing claims
  remain a limitation. Cache normalization/decision authority remain Q-007 risks;
  a cache-hit trace does not prove the HTTP service saw identical raw bytes.

## Actual measurements (offline, fixed v1.0.0)

`measure.py` reads only local release and historical challenge, emits hashes and
aggregate numbers, no article/text/token-ID previews. Values in measurements.json.
Challenge is **historical regression only**, never tuning or blind holdout.

- 120 samples: min 7, median 16, mean **19.14**, max 76 tokens.
  Above 256: 0/120; above 512: 0/120 (0%). Identical active token inputs at either
  budget on these records; no fresh challenge prediction metrics measured here
  and no quality improvement inferred. Other missing historical corpora cannot
  have their truncation rates measured.
- Eight synthetic structural fixtures (not new labeled benchmark): short 13,
  long 662, claim-at-end 552, negation 19, code-switched 30, noisy OCR 65,
  long preamble/window-context 316, contradicting claims 57 tokens.
  Truncated at 256: 3/8; at 512: 2/8. Denominator is these fixtures only, not users.
- At 552 tokens head-only loses the concluding marker; experimental sentence
  selector retains it. This proves only marker retention, not semantic correctness
  or better predictions. Selection uses lexical heuristics and is not deployed.
- Separate exact boundary fixture puts negation across a 128-token boundary;
  overlap retains the whole claim in the next window. Separate contradicting
  windows isolate quoted claim and denial. No mean/max-confidence aggregation or
  claim-level quality scoring performed; pooling hazard is structural, not a
  measured model-quality comparison. No window pooling installed.
- Real frozen inference smoke: 53-character/10-token input not truncated;
  7,000-character/702-token input truncated at 512. Smoke labels are predictions
  on synthetic text, not reviewed ground truth or accuracy evidence.

## Verification

Reproducible targeted command: `bash reports/ai-quality/20261029-q006/run-tests.sh`.
Fresh full runs and targeted results are recorded in verification.json.

- RED: original manifest contract test failed missing preprocessing_version.
  Further review added 10 failing contract regression cases: export 256 overwritten,
  bad booleans/range/aliases/strategies/hash/missing fields accepted, and default
  settings incorrectly overriding release. All corrected, not weakened.
- RED: whitespace let >20,000 chars through; configured 100-char cap mislabeled
  20,000. Both failed first, then corrected. DTO malformed metadata also failed
  before validation was implemented. Right-truncation/training-policy guard tests
  failed before shared production preprocessing was introduced.
- Targeted: **37 Python passed**, 2 dependency deprecation warnings; **8 Laravel
  passed, 186 assertions**. Expected safe error from simulated unavailable RAG.
- Full Python: **144 passed, 5 failed, 2 warnings**, 25.88s. All five failures are
  unchanged baseline `test_api.py` Windows model-path assumptions:
  `test_readiness_200_when_ready`, `test_version_exposes_labels_and_threshold`,
  `test_predict_happy_path_hoax_and_valid`, `test_predict_threshold_meragukan`,
  `test_request_id_propagation`. They load nonexistent `C:/xampp/...` on Linux.
- Full Laravel: **759 passed, 4 failed, 25 warnings, 4 skipped**, 5,434 assertions,
  39.24s. Four pre-existing owner-untracked FilamentAuditObserverTest failures:
  user redaction, unknown/nested attribute exclusion, dataset redaction, submission
  redaction. 25 existing progress DOM warnings; four RealBertInferenceTest skips
  because no background HTTP BERT service is running. Local Python real inference
  smoke is separate, not proof of Laravel-to-running-service end-to-end success.
- Pint: six touched PHP files, final result in verification.json. Full suites
  are **not green**; unchanged failure signatures are not proof of zero regressions.
- No frontend build/browser checks (no UI changes); no Python training/RAG changes.

## Resource and reproducibility

Pinned Python Docker image `sha256:046ca71ead3af0bbb8a6be04e109f41b86dc29e1314eb19f899f46c53cafd688`,
offline read-only checkout; test deps from existing `/tmp/opencode/q002-deps`.
2 CPUs / 2 GiB, tmpfs, network none. Python 3.12; torch 2.13.0+cpu,
transformers 5.14.1, tokenizers 0.22.2, pydantic 2.13.4. No dependency upgrade.
Laravel runner/pinned image inherited from Q-005, SQLite in-memory/cache array,
PHP 8.4.11/Laravel 12.64.0, no host .env/user DB mounts. No stochastic training;
seed N/A, fixtures deterministic. Peak RSS not measured. Model/tokenizer hashes
in measurements.json. Model revision v1.0.0 with immutable hash; upstream
tokenizer revision unknown, not guessed.

Reproduce measurement using Q-002 runner's Docker isolation flags, replacing
`-m pytest ...` with `/workspace/reports/ai-quality/20261029-q006/measure.py`.
No source payload is copied or repackaged for measurement.

Next: Q-007 decision-policy authority and immutable/exact-input cache identity.
Q-003 missing-data/legacy-runner blockers remain; no tuning authorized by this log.
