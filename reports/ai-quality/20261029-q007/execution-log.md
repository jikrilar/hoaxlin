# Q-007 — Release decision authority and prediction cache identity

Status: DONE (contract/cache implementation, not a quality improvement claim).
Branch `quality/02-input-contract`, base Q-006 `7c47994`.
No push, merge, deployment, historical result rewrite, dataset change, training,
model download, paid API, schema migration or modification of owner-untracked files.
Owner task-file hash differs from Q-006 at final verification (external/user change);
current hash recorded, file not reverted or staged. Audit test hash still matches.

## Decision contract

- Python `bert-decision-v1` exposes raw argmax, uncalibrated binary probabilities,
  calibrated binary probabilities, served label, threshold and abstention reason.
  Existing `raw_scores` remains the calibrated scores for compatibility (its old
  name did not mean pre-calibration). Argmax tie uses canonical valid/hoax ordering;
  temperature scaling preserves ordering. Below threshold abstains; equality is
  covered, not abstained. Missing/malformed sidecar no longer silently shifts
  authority to Laravel: runtime fails to load.
- Python decision fingerprint uses canonical sorted JSON SHA-256 over exact model,
  model-config, tokenizer, tokenizer-config, calibration and threshold file hashes,
  model version, effective temperature/threshold, preprocessing version, sequence
  limit, truncation and multi-claim policy, and decision schema. Changing any of
  these changes identity. Frozen payloads and sidecars were not modified.
- `/version` exposes `policy_fingerprint`; authenticated `/predict` includes the
  decision object. Laravel validates schema, identity format/pin, binary probability
  ranges/sums, confidence, argmax, threshold, abstention reason and served label.
  It never applies its own threshold to versioned decisions. DTO guards against
  accidentally rethresholding them elsewhere and preserves all metadata on cache
  copies. Decision metadata is retained in private processing events, not public
  logs. Existing persisted DetectionResult rows/labels are untouched.
- Legacy API is rejected by default. `BERT_ALLOW_LEGACY_RESPONSE=true` explicitly
  permits old threshold behavior only without a pinned identity. It never recovers
  `meragukan` by lowering the local threshold and never caches legacy output.
  Existing old-API tests now opt in explicitly rather than weakening production.

## Cache design and operational boundary

- Cache stores **served predictions**, not raw predictions to rethreshold later.
  Key: `bert:v2:<pinned immutable policy fingerprint>:<SHA-256 exact inference text>`.
  No case/whitespace/normalizer folding. The job's character-bounded classifier
  input is the exact string hashed and sent. No token/character estimation in key.
- Pin obtained from verified local `/version`, then set
  `BERT_POLICY_FINGERPRINT` for Laravel app/queue/scheduler. Empty/invalid pin disables
  prediction caching. Every cold response and cache hit is revalidated against pin;
  a mismatch fails closed. Old `bert:` namespace is not reused or globally flushed.
- **Deployment invariant:** model and all inference-policy artifacts must be
  immutable for a pin. Rotate the Laravel pin and reload workers with every
  serving release/config change, before accepting traffic. A warm hit cannot
  detect a server illegally replaced in-place under a stale pin; no per-request
  `/version` discovery or claim of automatic detection is made. Unpinned mode
  validates self-consistency/schema but is not an authenticated immutable release
  attestation and deliberately bypasses cache.
- Compose and example env files pass the explicit pin/legacy options. No actual
  .env read/edited, containers recreated or pin installed in deployment. Old images
  without this response contract require deliberate legacy opt-in or rebuilding
  BERT before app rollout; defaults fail closed rather than silently disagree.

## Tests and real runtime evidence

`bash reports/ai-quality/20261029-q007/run-tests.sh` runs targeted suites offline.
New Laravel tests failed first for double thresholding, shared normalized keys,
unvalidated identity and implicit legacy acceptance; missing-sidecar Python test
failed before fail-closed guard. New policy module initially absent (collection
error recorded separately, not passed off as an assertion regression). Boundary
test fixture corrected to HTTP response sequence because repeated Http::fake
registrations retained the first response; production checks were not weakened.

- Targeted Python: 39 passed, 2 dependency deprecation warnings. Includes real
  frozen model authenticated endpoint, version/predict fingerprint consistency,
  token metadata, probabilities/served label and boundary helper tests.
- Targeted Laravel: 35 passed; exact final counts in verification.json. Covers
  distinct case/spacing inputs, changed pins/releases, cold/warm equality except
  cached flag, preserved token metadata, threshold boundary, mismatch, malformed
  served label, explicit legacy rejection/opt-in and no abstention recovery.
- Full Python: 149 passed, 5 failed, 2 warnings, 28.04s. Same baseline Windows model
  path failures in test_api.py: readiness_200_when_ready,
  version_exposes_labels_and_threshold, predict_happy_path_hoax_and_valid,
  predict_threshold_meragukan, request_id_propagation (all with test_ prefix).
- Full Laravel: 767 passed, 4 failed, 25 warnings, 4 skipped; 5,453 assertions,
  41.32s. Failures remain owner's untracked FilamentAuditObserverTest redaction
  and unknown-attribute assertions (user, nested attributes, dataset, submission).
  Existing DOM warnings; RealBertInferenceTest skips background HTTP service.
  Full suites are not green; no zero-regression claim based on counts alone.
- Pint PASS on nine touched PHP files; git diff --check clean.
- Separate local real frozen inference emitted fingerprint
  `0c2331e8f85ab15ebf0763e14d9d36dc9a2bfd3d2dbbbc77f7209d9faf6ddb47`,
  threshold 0.99, uncalibrated hoax 0.9983305335 versus calibrated 0.9993535876,
  served hoax, no abstention. Synthetic smoke only, not reviewed ground truth,
  accuracy, production pin recommendation or Laravel end-to-end service proof.

## Environment / limits / handoff

Same pinned Docker runners as Q-006: Python image
`046ca71ead3af0bbb8a6be04e109f41b86dc29e1314eb19f899f46c53cafd688`,
PHP image `7a9c88f9b9345037ef841e4366bc31abe57b7ef29bdd449b9f7f599bfd9e7eb3`.
Offline network-none, read-only source, tmpfs, 2 CPUs / 2 GiB; SQLite in-memory,
cache array, no user data/secret mounts. Python 3.12, torch 2.13.0+cpu,
transformers 5.14.1/tokenizers 0.22.2; PHP 8.4.11/Laravel 12.64.0. No stochastic
training, seed N/A, peak RSS not measured. Model/tokenizer hashes unchanged from
Q-006 measurements.json; tokenizer upstream revision unknown, not invented.
No benchmark quality measurement or change to historical challenge role.

Next Q-008: audit source corpus and shortcut hypotheses. Missing historical data
may block corpus measurement; do not synthesize statistics or fill reviewed labels.
