# Q-011 — Grouped benchmark freeze preflight

## Owner-directed closeout 2026-10-30

Q-010/Q-011 remain BLOCKED, not phase acceptance. Q-012–Q-016 explicitly must
not start. No corpus approved, benchmark allocation or freeze timestamp supplied.
Owner pilot is20 records; Komdigi metadata/license verification pending.

Dataset-review tooling finalized separately on quality/03-bert-data commit
`d889d21` (private copy-verification, configurable persistent storage, deterministic
review packets and owner handoff). Not merged/cherry-picked into this model branch.
Read that commit's `reports/ai-quality/dataset-review-readiness/phase-closeout.md`
for operational steps. This branch contains only Q-011 and this log addendum above
the prior data base; dataset tooling stays in its appropriate phase branch.

3485 staging files now verified on persistent local storage
`$HOME/.local/share/hoaxlin/dataset-review/komdigi-proposals-v0.1.1` against saved
receipt. Pilot20 tasks there remain UNASSIGNED/null; no human review performed.
Oct30 stdlib checks validate hashes/permissions/new deterministic packet/copy-only
no-overwrite. Fresh pytest blocked because temporary pytest deps disappeared after
restart. Oct29 targeted/full results below are preserved historical evidence,
not represented as fresh passing results. Existing baseline failures remain open.

Remaining acceptance: human reviews/evidence/rights, multi-source label coverage,
semantic claim/event grouping, temporal/source-holdout signoff, actual partition
overlap audit, sufficient dev/holdout and immutable hashes/counts/freeze UTC.
Tooling availability cannot satisfy those data-quality gates.

Git transition check: local main remains3f17a20 (stale); remote main verified via
ls-remote at57649cb81b8895d95ea18251988369c77dcc5a4f, includes baseline/input/data
through9cc78ce. Recommended RAG base is that approved main snapshot, not this
unfinished model branch. No quality/05-rag-evaluation branch/worktree created,
no Q-017 started. Wait for owner approval; recheck refs before transition. If
owner first integrates dataset follow-up, choose newly approved main SHA instead.
No push/merge/rebase/deployment or branch deletion performed.

**Status BLOCKED. No partition freeze or release gate pass.**
Branch at start: quality/04-bert-model (already selected by owner/environment).
Base dependency Q-010: 9cc78ce. Did not switch/reset user branch or modify datasets.

## Actual readiness result

Read-only preflight on private Q-010 v0.1.1 staging: 3,480 quarantined proposals,
0 reviewed eligible rows. Candidate hash still
307a40aeede525d50f788de030be8114aa7280a1705eabde8da79948292e805c.
Legacy challenge: 120 rows, SHA-256
d9237395948dc42cbcfec328a4f2a74bbff73d66537bced27ef4746b76c32f7b,
historical regression only, never blind holdout.

`preflight-result.json` intentionally has null partition counts/hashes/freeze
timestamp; no empty artificial benchmark manifest created. No claim that group
intersections are empty or challenge overlap absent on real final partitions:
there are no eligible partitions yet. Historical corpus/Antara remain missing.

## Tooling delivered, not a completed frozen benchmark

`dataset/benchmark_partitions.py` is an additive read-only preflight validator,
not a replacement for existing three-way splitter and not an automatic allocator.
Recognizes train/val/dev/calibration/holdout as separate partitions, rejects
missing/unknown partitions and duplicate IDs. Checks claim/event groups and
shared parent artifact across publishers, normalized exact matches, word-trigram
Jaccard >=0.65, and contiguous normalized fragments with >=7 tokens. Reports
overlap with legacy challenge IDs without storing text. No first-wins deletions.

Lexical checks are exhaustive up to 5,000 candidate records, never sampled;
larger inputs rejected explicitly. This is quadratic tooling for bounded audits,
not a scalable semantic duplicate detector. Translations/paraphrases and split
article spans must share reviewed claim/event/parent links. Failure to detect
lexical similarity does not establish semantic independence.

Reports single-label-source confounds, per-partition binary counts, review blockers,
minimum 100 valid +100 hoax in EACH dev and final holdout; train never downsized
to meet benchmark counts. Existing training schema/split/manifest unchanged.
Validator deliberately NEVER approves a freeze: human semantic, temporal and
source-holdout signoff plus authenticated review gates remain outside this helper.
Fixture check_review=False is only to isolate tests, never release authorization.

## Freeze policy required when unblocked

1. Approve actual multi-source claim-level corpus under Q-009/Q-010. Resolve
   rights, two-reviewer agreement/adjudication, evidence/freshness; provisional
   group IDs are unacceptable. Report label×source/topic/style/date coverage.
2. Join all known edges (claim/event, translations, article parent, exact/near
   matches) into connected groups across sources BEFORE allocation. Conflicting
   labels require review, not first occurrence selection.
3. Predeclare UTC cutoffs and held-out source publishers from observed coverage,
   without candidate-error-driven holdout selection. Connected group crossing a
   boundary is quarantined or wholly assigned conservatively by documented policy;
   never split it. Unknown dates require quarantine/review, not invented timestamps.
4. Source holdout only if both labels have meaningful coverage; single-label
   publishers reported as confounds, not claimed a pure generalization test.
   No actual cutoff/source selection made on the current one-source proposals.
5. Keep train, validation, development, calibration and final holdout distinct.
   Calibration/threshold tuning only on declared partitions; blind final holdout
   locked from developers. Human reviewer selection independent of candidate errors.
6. Audit legacy challenge exact/near/group overlap on ALL real partitions. Exclude
   or quarantine overlapping event groups from new blind holdout with traceable
   rationale; do not relabel/tune against challenge or silently remove to improve scores.
7. Verify no known cross-partition edges, sufficient coverage, immutable row counts,
   SHA-256 of each partition and input/policy/code identity; record actual freeze UTC
   and responsible reviewer. Freshness/semantic review is additional to lexical gate.
   No hardcoded synthetic approval or retrospective historical hash rewrite.

## Commands / validation

- Read Q-011/Q-010 instructions, prior verification, existing splitter/dedup APIs.
- Added synthetic regression tests before module; initial missing module collection
  error explicitly not a successful assertion test. Near fixture corrected from
  contained suffix to one-token substitution to exercise near Jaccard branch.
- Targeted `bash reports/ai-quality/20261029-q002/run-tests.sh tests/test_benchmark_partitions.py tests/test_split.py tests/test_dedup.py tests/test_curation.py`:
  **24 passed in 0.56s**.
- Full Python **163 passed, 5 failed, 2 warnings, 26.91s**. Same baseline
  test_api.py Windows-path failures: test_readiness_200_when_ready,
  test_version_exposes_labels_and_threshold, test_predict_happy_path_hoax_and_valid,
  test_predict_threshold_meragukan, test_request_id_propagation. Not a green suite.
- Preflight Docker first attempt failed PermissionError on private staging (0700).
  Reran with host UID/GID, no permission weakening: success. Read-only mounts,
  network none, pinned Q-002 image, 2 CPU/2GiB/tmpfs64MiB. No model download or
  training; Python stdlib + existing numpy. No PHP/UI changes or PHP/build/browser checks.
- Reproduce preflight using Q-010 run-staging.sh Docker flags, read-only mount
  `/tmp/opencode/q010:/output:ro`, host UID/GID, command
  `/workspace/reports/ai-quality/20261029-q011/preflight.py`.
- Owner files/CSV, Q-008–Q-010 tracked evidence, private staging checksums preserved;
  staged whitespace checked. No push/merge/deploy, freeze or automatic Q-012.

Remaining blockers: reviewed data, source coverage, semantic groups, temporal/source
signoff and real-partition overlap audit. Q-011 stays BLOCKED, not DONE tooling-only.
