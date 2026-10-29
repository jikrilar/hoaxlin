# Q-004 execution log

**Status: DONE for protocol preregistration and reporting contract only.**
No candidate is accepted/promoted; no new data/reviewer/freeze is claimed.
Base HEAD `702b4e10d079e083ccd3cfb365835bd596958501`, branch
`quality/01-baseline`. Q-001/Q-002 completed; Q-003 BLOCKED partial, reviewed in
`q003-review.md`. Q-005 and all subsequent tasks **not started**.

## Scope and decisions

- Read actual `AGENTS.md`, audit global execution/context rules, Q-004 and Section 8.
  Git status initially contains only the owner's two untracked files; preserved.
- Adopt all Section 8 numerical gates unchanged and add explicit conservative
  uncertainty rules; lock before any candidate experiments/holdout opening.
- Owner confirmation of deleted historical training data is recorded as a new
  addendum, not a rewrite of Q-003 or old manifests/results. Updated Kaggle data
  belongs to a future new corpus. No synthetic labels/review approvals.
- `evaluation-protocol.md` defines scope, six disjoint BERT partitions, independent
  RAG dev/policy/final query partitions, reviewed event grouping, no-answer/judgment
  rules, sample/group minima, freeze/access milestones, metrics/denominators/CIs,
  budgets and rejection/insufficient-evidence rules. Final holdout is once-only.
- Existing evaluator provides binary/abstention math but lacks grouped CIs/strata.
  Added an **offline reporting adapter** reusing `compute_binary_metrics` and
  `compute_abstention_metrics`; no inference, fitting, threshold search, model/data
  changes, no Q-017 graded-RAG implementation. Output cannot auto-PROMOTE.
- Reporter input requires reviewed groups and consistent strata; it never infers
  missing grouping or creates new ground truth. Fixture tests only exercise math.
  Calibration NLL/Brier/ECE uses existing training tooling at Q-015, not falsely
  claimed present in the Q-004 operating-point reporter.

## Acceptance assessment

| Q-004 requirement | Evidence / disposition |
| --- | --- |
| Dependency Q-002, available baseline Q-003 | Existing commits; 11 Q-003 artifact hashes verified, review addendum |
| Dev, validation, calibration, locked final holdout | Protocol Sections 3,4,9: explicit disjoint purpose and freeze sequence |
| Challenge v1 historical only | Protocol Sections 1,2,6; no tuning/inference on challenge this task |
| Scope, experiment/resource/latency caps | Protocol Sections 1,3,4,8; prospective budgets, not SLA claims |
| Section 8 BERT/RAG/integration gates | Protocol Section 6 includes every original numerical target, completeness and real checks |
| Uncertainty/insufficient-data policy | Section 7: grouped paired bootstrap, boundary cautions, null denominators, evidence decision rules |
| Sample counts per class/stratum, matrix, macro F1, precision/recall, risk/coverage, CI output | Actual reporter CLI exercised by 9 tests; hand-computed fixture metrics and group-level behavior asserted |
| Gate cannot change after holdout opening | Versioned prospective amendments only, new cycle required; immutable run protocol hash |
| Data not yet sufficient | No actual new freeze or quality pass; new holdout/reviewer absent, candidates cannot be promoted |

Completion is for defining/validating this protocol; dataset collection, real-model
candidate evaluation, calibration policy selection and RAG evaluator extension
are later tasks, not hidden claims of Q-004 completion.

## Commands and observed results

All commands from repository root; no external network, install, training or model
download. Tests reuse Q-002 runner pinned BERT image and temporary dependencies:
`reports/ai-quality/20261029-q002/run-tests.sh`, image
`sha256:046ca71ead3af0bbb8a6be04e109f41b86dc29e1314eb19f899f46c53cafd688`,
source checkout read-only, network none, 2 CPUs/2 GiB, tmpfs 512 MiB. Dependency
versions in Q-002 `environment.json`; no dependency/lockfile upgrade.

1. `git status --short --branch`, `git log -3 --format='%H %s'`: phase A branch,
   HEAD Q-003; only untracked task document and `FilamentAuditObserverTest.php`.
2. Python stdlib hash/JSON inspection: all **11 Q-003 artefact hashes match**;
   corroborated 120 BERT rows/matrix/coverage, six adapter cases, 20 RAG queries,
   concrete legacy loader failures; current expected Komdigi CSV path absent.
3. TDD **RED** before reporter implementation:

   ```bash
   bash reports/ai-quality/20261029-q002/run-tests.sh tests/test_classifier_protocol_report.py
   ```

   Exit 1, **3 failed / 6 passed**, 0.33 s. Positive CLI cases fail because reporter
   is absent. Negative cases originally pass on missing executable; tightened
   assertions later require explicit `Protocol report blocked:` from the real
   reporter, so absence no longer counts as validating input rejection.
4. Implement reporter, rerun: **9 passed**, 7.14 s. Added per-class group-support
   assertion before implementing that field: RED **1 failed / 8 passed**, missing
   `groups_per_true_label`. Implemented group counts and >=30-group support label.
5. Targeted regression run:

   ```bash
   bash reports/ai-quality/20261029-q002/run-tests.sh tests/test_classifier_protocol_report.py tests/test_external_challenge_evaluator.py tests/test_tfidf_logreg_baseline.py tests/test_external_challenge_error_analysis.py
   ```

   Exit 0: **44 passed in 10.14 s**. Includes group bootstrap preserving linked
   variants, deterministic paired zero delta, all-abstained null risk, invalid/
   mismatched input rejection, historical checksum and evaluation regressions.
6. Full BERT suite:

   ```bash
   bash reports/ai-quality/20261029-q002/run-tests.sh > /tmp/opencode/q004-full-suite.log 2>&1
   ```

   Exit 1: **114 passed, 5 failed, 2 warnings in 26.36 s**, no skips. Same existing
   Windows model path `C:/xampp/...` failures (503 model unavailable), not fixed or
   assertions weakened in Q-004:
   - `tests/test_api.py::test_readiness_200_when_ready`
   - `tests/test_api.py::test_version_exposes_labels_and_threshold`
   - `tests/test_api.py::test_predict_happy_path_hoax_and_valid`
   - `tests/test_api.py::test_predict_threshold_meragukan`
   - `tests/test_api.py::test_request_id_propagation`

   Warnings: Starlette httpx TestClient deprecation and anyio BlockingPortal alias.
   Full suite is **not green**. No PHP/UI/runtime changes; no PHP build/browser,
   Python RAG suite or real-service inference run claimed.
7. Final checks: Python AST/JSON and report/hash preservation checks, CLI `--help`,
   `git diff --cached --check`; results captured in `verification.json` and handoff.
   No whole-dataset loading into context or raw user text in reports.
   Host CLI default 2,000 grouped resamples also exercised on a four-row fictional
   unit fixture: exit 0, nullable/insufficient-evidence report, not benchmark data.
   Verified 11 Q-003 artifact hashes and 47 preserved historical/user file hashes.

## Artifact/provenance and remaining blockers

- Protocol version `q004-v1`, statistical seed 1729, 2,000 grouped resamples;
  tests use 200 deterministic resamples on explicitly fictional unit fixtures.
- New model inference samples: **0**, training runs: **0**; quality metrics: **N/A**.
  Tests show reporting behavior, not improved model quality or reviewer approval.
- Immutable release/tokenizer/calibration/KB/query identities remain in Q-001/Q-003;
  no replacement artifact/model download used. Historical data loss remains open.
- No release runner fix attempted; strict release verification remains mandatory.
- Dataset new version and final holdout do not exist yet; reviewer/custodian not
  appointed. This is a freeze plan and gate preregistration, not a data freeze.
- Two files belonging to owner remain unchanged/uncommitted; no merge/push/PR/deploy.

## Files changed for the atomic task commit

1. `reports/ai-quality/20261029-q004/evaluation-protocol.md`
2. `reports/ai-quality/20261029-q004/q003-review.md`
3. `reports/ai-quality/20261029-q004/execution-log.md`
4. `reports/ai-quality/20261029-q004/verification.json`
5. `bert-service/scripts/classifier_protocol_report.py`
6. `bert-service/tests/test_classifier_protocol_report.py`

Commit message: `docs(ai-quality): define evaluation protocol and release gates`.
No task document supplied by owner or historical execution log overwritten.
