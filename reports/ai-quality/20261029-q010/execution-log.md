# Q-010 — Private candidate curation staging

**Status: BLOCKED — multi-source reviewed corpus NOT completed.**
Tooling and a new versioned, private Komdigi proposal set prepared. No approved
ground truth, training corpus, split or benchmark release produced.

## Scope and dependency state

Branch quality/03-bert-data, base Q-009 `1c28496`.
Owner supplied updated Kaggle Komdigi snapshot, explicitly not training v1.0.0.
Source SHA-256 `32fc99f809a39ab6528430fb2966577ffdfbc0f6c2f6533bb420add0fb9face7`.
Antara/historical processed corpus, additional authorized source, two reviewers
and adjudicator remain unavailable. Q-009 is protocol only, not actual reviews.
No crawling, paid API, training, inference, release modification or Q-011 run.

## Implementation / private outputs

- New `bert-service/dataset/curation.py` reuses unchanged Komdigi adapter and
  normalizer. New output directory only, refuses overwrite, source read-only,
  output directory 0700/text and JSONL files 0600. Proposal text kept privately,
  not copied into Git/logs. Original raw CSV remains unchanged and ignored.
- `candidates.jsonl`: 3,480 annotation-v1-shaped proposals, null proposed/final
  labels, empty reviews/evidence, benchmark_eligible=false, quarantined. Adapter
  extraction is candidate material, not guaranteed one atomic verified claim.
- `source-texts/`: adapter full text (already cleaned/link-counter-pretrimmed),
  individually hashed. This is NOT raw source. Original CSV parent hash plus
  source ID in provenance preserves the route back to original row. No raw source
  or historical checksum is rewritten. Source span is exact in hashed cleaned
  parent, not falsely claimed to be offsets in raw HTML/CSV.
- `provenance.jsonl`: parent CSV hash, source ID/adapter ordinal, exact selected
  hash, cleaned parent hash, source code hashes and transform chain. Source-implied
  hoax is retained only as provenance, never assigned as reviewed claim label.
- `quarantine.jsonl`: all 3,480 records pending human review, rights and claim/event
  group review; 87 full fallbacks specifically flagged, 8 normalized duplicate
  groups flagged without deleting members. No source-based relabeling.
- `manifest.json`: NEW staging manifest with input/artifact hashes, proposal version,
  counts, limits and local staging timestamp. Not a training manifest or historical
  reconstruction; retrieved_at is local access time, not invented Kaggle download time.
- `benchmark.jsonl`: always empty by design. No reviewed exporter enabled. Readiness
  helper checks both labels, evidence direction, two distinct human reviewer IDs,
  confidence, rights, group/rationale/freshness. It cannot authenticate identities,
  validate evidence semantics or adjudication; disagreement fails closed even if
  an adjudication pointer exists. A real authenticated review/export workflow,
  schema engine validation, semantic preservation and source rights are still needed.
- Normalized conflict helper retains both IDs and flags opposing final labels,
  not first-wins. Cannot measure real cross-label conflict without reviewed labels.
  Group IDs are explicitly provisional unreviewed keys, not verified event groups.

Actual private final location:
`/tmp/opencode/q010/komdigi-proposals-v0.1.1/`.
Earlier v0.1.0 exploratory staging (without final provenance sidecar) is left
untouched, not the final artifact. Temp storage may be ephemeral: steward must
move approved private artefacts to authorized persistent storage before relying
on them; source CSV + committed script can regenerate new-version proposals.
No copy of private candidate texts is committed. Artifact hashes in verification.

## Measurements and limitations

Final run: 3,480 proposals, 3,393 claim / 87 full; 3,480 quarantined; approved=0;
benchmark=0. All 3,480 source hashes and spans verified against private artifacts.
8 normalized duplicate groups. Counts agree with Q-008 snapshot audit.
Q-010 quarantine covers ALL unreviewed records; not equivalent to Q-008's 116
specific text-quality candidates. No claim of improved source/topic/length balance:
still one source, zero human-approved binary labels. Cross-source comparisons,
claim fidelity, human label agreement, evidence correctness and event overlap
remain BLOCKED. Candidate volume is not evidence of quality improvement.

## Tests and verification

1. Git status initial: only owner untracked task list/audit test; preserved.
2. Read Q-010/Q-009 guidance and current source adapter. Wrote tests first.
   Initial module import absent (collection error, not an assertion regression).
   Provenance consumer test later failed missing provenance.jsonl, then implemented.
3. Targeted:
   `bash reports/ai-quality/20261029-q002/run-tests.sh tests/test_curation.py tests/test_komdigi_audit.py tests/test_normalize.py tests/test_prepare.py tests/test_reconcile.py`
   **33 passed in 0.83s**. Synthetic human-like test records are explicitly fixtures,
   never written to real corpus or claimed actual human approvals.
4. Full Python: **158 passed, 5 failed, 2 warnings in 26.62s**, unchanged baseline
   test_api.py Windows-path assumptions: test_readiness_200_when_ready,
   test_version_exposes_labels_and_threshold, test_predict_happy_path_hoax_and_valid,
   test_predict_threshold_meragukan, test_request_id_propagation. Full suite not green.
5. All private spans/hashes, 0 benchmark, manifest artifact checksums, output dir
   permissions, source CSV hash, owner hashes and all Q-008/Q-009 evidence checked.
6. No PHP/UI changes: no Pint/PHP suite/build/browser run. Whitespace checked
   before commit. No push, merge, deploy or automatic Q-011.

Reproduce a new private staging version from repo root:
`bash reports/ai-quality/20261029-q010/run-staging.sh NEW-VERSION`.
Offline pinned Q-002 image 046ca71ead3af0bbb8a6be04e109f41b86dc29e1314eb19f899f46c53cafd688,
Python 3.12, existing pydantic/numpy; 2 CPUs/2 GiB, tmpfs128 MiB, source read-only,
network none, no secrets/DB mounts. No model/tokenizer loading or dependency install.
Timestamp makes manifests different on regeneration; claim hashes/selection fixed
for same CSV/code. No stochastic sampling/training here, seed N/A, RSS not measured.

## Unblock checklist

1. Owner confirms Kaggle version/provenance and permissible use/redistribution.
2. Obtain second authorized source with observed valid/hoax claims; no automatic
   Antara collection. Preserve full original source and evidence privately.
3. Assign two independent human annotators and adjudicator; atomic claim extraction,
   context/evidence/rights review, disagreement resolution per Q-009.
4. Merge Q-008 specific quality review reasons; resolve duplicate conflicts and
   claim/event groups before a reviewed export or group-safe split.
5. Validate annotation schema with actual schema engine + authenticated semantic
   gate and versioned audit trail; compare distributions before declaring improvement.

Commit: `feat(dataset): prepare reviewed multi-source claim corpus` (task-prescribed
message; this log and manifest explicitly state the reviewed corpus is BLOCKED).
