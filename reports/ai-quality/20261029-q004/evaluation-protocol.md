# Hoaxlin evaluation protocol — q004-v1

**Status: preregistered protocol, not a quality pass or deployment approval.**
Locked by the atomic Q-004 commit on `quality/01-baseline`; base source
`702b4e10d079e083ccd3cfb365835bd596958501`. No new final holdout exists yet.
Numeric gates below adopt audit Section 8 unchanged. This document, input
schema, statistical procedure and resource caps must be hashed into each run.
No reviewer approval, data freeze or candidate performance is implied by this lock.

## 1. Scope and non-negotiable boundaries

- Indonesian verifiable factual claims, with health, disasters/weather, public
  policy/social assistance, economy/finance and other represented topics reported
  separately. Primary benchmark unit: reviewed claim with sufficient event/time/
  place context, not publisher reputation or a whole article presumed true.
- Report input strata: short claim/message, article, OCR-derived text, transcript,
  translated/mixed language; single/multiple claims; negation, late-article claim,
  noisy text, same-topic/wrong-event/entities/date/amount. Modalities without enough
  real reviewed examples are explicitly **unvalidated**, never pooled into a claim
  that all input types passed. Clean-text evaluation is not an extraction benchmark.
- BERT training remains binary `0=valid,1=hoax`. `meragukan` is selective abstention.
  RAG/explanation cannot change this label. Confidence is not factual truth;
  similarity/relevance and support/refutation are separate concepts.
- No synthetic/AI-approved ground truth. Ambiguous, satire, opinion and unresolved
  claims are quarantined or in a separately reported out-of-scope abstention set,
  not forced into binary holdout. No private submission access without authorization.
- Historical challenge v1 is a known **regression/diagnostic benchmark**, never
  a new blind holdout or tuning/calibration/model-selection set. Its 120 labels,
  original text, order, hashes and old results remain immutable.

## 2. Historical provenance and usable baseline evidence

Owner update received during Q-004: the original v1.0.0 training CSVs and processed
splits were deleted. Current re-downloaded Kaggle `komdigi_hoaks.csv` was updated by
its author and **is not the historical training dataset**. `valid_antara.csv`,
`train.jsonl`, `all.jsonl` and historical partitions are unavailable. Never change
old checksums/manifests to match new data, infer the old split from new records,
or call a new TF-IDF fit a historical reproduction. No training rows may be rebuilt
from internal prediction files. Old model and reports remain preserved.

At Q-004 inspection `datasets/komdigi_hoaks.csv` was not present in this checkout;
the owner's new source can be inventoried later at an authorized location. Its
future manifest needs Kaggle dataset/version URL, download timestamp, license,
raw-byte hash, source revision, row counts and review state. No assumed hash or
upstream revision is supplied here.

Q-003 baseline evidence is sufficient for this protocol, **not complete**:

| Evidence | What it establishes | What it does not establish |
| --- | --- | --- |
| BERT real CPU, 120 historical samples | Accuracy 0.508333, macro F1 0.409951; matrix `[[6,54],[5,55]]`; 85 covered at 0.99, 36 correct | Fresh overlap audit, generalization on new blind data, current deployment performance |
| Six real HTTP/adapter cold/warm inputs | Scores/labels match at tolerance 1e-6; cold 1 call/warm 0, isolated array cache | Redis integration, all input modalities, current cache fingerprint safety |
| Real RAG, 20 legacy queries | Historical metrics reproduced for KB 1.0.0 and 1.1.1 | Corpus-aligned independent judgments for 1.1.1, no-answer accuracy |
| TF-IDF saved prediction recalculation | Old metrics internally consistent | Refit or inference with historical train data |

Q-003 stays **BLOCKED_PARTIAL**. Historical TF-IDF refit and historical overlap
verification are unresolved and cannot be repaired with the updated Kaggle CSV.
Record them as permanent provenance limitations unless authentic artifacts are
recovered and hashes verified. Future experiments must instead document a **new**
training corpus and its own overlap/split audit; this is not retroactive completion
of historical reproduction. Whether missing historical overlap prevents a particular
future comparative claim must remain explicit in the promotion evidence review;
it cannot silently be waived by passing unit tests or citing old metrics.

Legacy external runner still expects source-export `model_name` / hash-string
manifest fields, whereas available packaged release uses `model` / `{size,sha256}`.
Its non-strict loader fails checksum parsing. Required repair: tests for both
manifest formats, strict packaged validation with exact version/weights/tokenizer/
sidecars, fail-closed corruption/mismatch tests, and no fallback that skips hashes.
Q-004 does not repair that runner. Q-003's separate strict serving-path inference
is valid limited evidence; complete runner qualification remains open before promotion.

## 3. New data and partition plan (Q-008–Q-011, not executed here)

Reuse existing preparation/dedup/claim-group/time-split tooling. The new Komdigi
download is **one acquisition source for a new corpus version**, not automatic
hoax labels or a replacement for ANTARA/historical data. Acquire authorized valid
and hoax claims from multiple sources per class; preserve original text, extraction,
negation, entities, dates, amounts, units, source evidence, provenance and transforms.
Two independent human annotations plus adjudication for benchmark disagreements;
AI suggestions stay pending until humans approve. No reviewer is assigned/approved
by this document. Dataset ownership and redistribution rights must be recorded.

Partition by claim/event group before sampling; exact/near-duplicate, paraphrase,
translation, OCR/transcript and article fragments belong to one partition across
all sources. Existing 0.65 similarity is a diagnostic starting rule, not proof of
semantic non-overlap; register final dedup policy in Q-011 before candidate results.
Audit against known challenge v1; inability to audit missing historical train set
remains disclosed. Time/source holdout cuts are chosen from actual coverage and
locked before fitting, never invented dates or chosen after seeing candidate errors.

| Partition | Exclusive purpose | Minimum planned reviewed support |
| --- | --- | --- |
| Train | Parameter fitting; new TF-IDF fit and BERT fitting | Corpus-dependent, publish class/source/group counts; no benchmark quota substitutes for enough train data |
| Checkpoint validation | Early stopping/checkpoint and registered training hyperparameters | At least 100 valid + 100 hoax, disjoint groups |
| Development | Diagnosis, representation/shortcut ablations, limited model selection | At least 100 valid + 100 hoax, disjoint from checkpoint validation |
| Calibration | Fit temperature only after checkpoint lock | At least 100 valid + 100 hoax, unseen for checkpoint selection |
| Policy validation | Select threshold(s) under fixed risk/coverage constraints | At least 100 valid + 100 hoax, not calibration or checkpoint data |
| Final blind holdout | One locked candidate versus frozen baseline(s), once | At least 100 valid + 100 hoax and 30 independent event groups per class |

These are minimum plans, not current data counts or universal power guarantees.
Additional rows cannot be manufactured to fill quotas. If support is insufficient,
results are preliminary and **no promotion**. Cross-fitting in lieu of separate
calibration requires a prospectively locked amendment before experiments and before
holdout access; this v1 defaults to separate partitions, not an automatic fallback.

Each split reports label × source × topic × event time × input type × length
(release-token counts: <=128,129–256,257–512,>512; separately character counts and
truncation), text_source, language/translation, and reviewed group counts. Publisher
available for only one label is a confound to disclose, not a ground-truth rule.
Strata with <30 groups or <30 samples of either class are preliminary for per-class
claims; unsupported strata are excluded from validated scope, never hidden.

## 4. RAG benchmark plan (Q-017–Q-019; no evaluator rewrite here)

- Separate development and blind holdout, initially each >=60 real reviewed queries:
  40 answerable + 20 explicit no-answer, per task minimum. Add disjoint policy
  validation queries for rejection threshold selection, initial >=40/20 as well.
  No final holdout threshold/reranker/query/chunk/corpus selection.
- For promotion, plan to expand **before any freeze/experiment** to >=100 answerable
  + 60 no-answer per evaluation partition with >=30 independent query/event groups
  per category, subject to interval precision. The 40/20 starter is diagnostic:
  a small no-answer sample cannot establish a low false-reference rate confidently.
- Include the hard negatives in Section 1; same event/claim paraphrases across
  partitions forbidden. Frozen query text is not generated from candidate mistakes.
- Grades: 0 unrelated, 1 topic only, 2 directly related claim/event, 3 direct evidence
  answering/refuting. Refutation remains relevant. Grade >=2 is direct relevance;
  report grade 3 separately, without misnaming >=2 as factual support.
- Independent reviewers judge pooled dense/lexical/comparator candidates, without
  seeing ranker score/system identity; resolve disagreement. For small KB, use
  exhaustive judgment where feasible. Unjudged != grade 0. Explicit no-answer
  requires corpus-scoped review; adding a document can change answerability and
  therefore needs new versioned judgments, not silent mutation of old results.
- Freeze holdout queries/annotation rules before retrieval experiments. Judge new
  corpus passages blind as needed, with versioned scope and adjudication; freeze
  corpus/index/judgments before final run. If this invalidates no-answer membership,
  build a new prospective holdout cycle; never filter cases post-result.
- Existing 20-query v1 remains legacy and cannot select production min_score.

## 5. Locked metrics and denominators

### BERT

Always report unthresholded binary argmax on **all** reviewed binary rows, including
served abstentions. Confusion matrix: actual rows, predicted columns, order valid,
hoax. Accuracy numerator correct / all N; per-class precision TP/(TP+FP), recall
TP/(TP+FN), F1 harmonic mean; macro F1 mean of two class F1s. Zero-division F1
follows existing evaluator (0), with actual and predicted support shown and undefined
precision/recall flagged through nullable report metrics/denominators.

Selective report at the **already locked** served policy: coverage=covered/N,
risk=covered errors/covered, covered accuracy=covered correct/covered. Per-true-class
coverage=covered true-label samples/all true-label samples. Valid-to-hoax FPR uses
binary hoax predictions/all true valid samples, including abstentions (not only
high-confidence false alarms). Empty covered set: risk/accuracy null, never 0/1.
A single operating-point risk/coverage report is mandatory; full risk-coverage curve
is diagnostic on validation/dev only and cannot choose a final-holdout policy.

Calibration: NLL=mean(-log p_true), clamp probabilities to [1e-12,1-1e-12] for
numerical log only; multiclass Brier=mean sum over two classes `(p-onehot)^2`,
range [0,2]. ECE: existing ten equal-width winning-confidence bins, [0,0.1], then
(a,b], weighted absolute accuracy-confidence gap; publish count/mean confidence/
accuracy per bin. Compare calibrated and uncalibrated **same locked checkpoint**
on same untouched rows. No refit T on final holdout. Existing train metrics reused
on Q-015, not silently implemented or claimed measured by the Q-004 reporter.

### RAG

Final display K=3; also report K=5 diagnostically, actual returned-length histogram,
and candidate recall at registered candidate_k. Collapse duplicate document IDs
deterministically; score ties by document ID. Publish the tie/dedup policy and
ranked passages. Metrics use corpus-aligned reviewed grades:

- Hit@3: answerable queries with >=1 grade>=2 in first 3 / all answerable queries.
- Returned direct precision (micro): returned grade>=2 docs / all displayed docs;
  macro: per-query precision on answerable queries with empty output=0, plus
  nonempty-only macro explicitly labeled. All-empty micro=null and fails coverage.
- Fixed-K precision: relevant returned in first K / K per query, including missing
  slots as 0, report separately from legacy returned-length precision.
- Direct evidence precision grade=3 separately; relevance is not support probability.
- Recall@K: retrieved grade>=2 / all corpus-judged grade>=2 per answerable query;
  nDCG@K uses gain `2^grade-1`, discount `log2(rank+1)`, corpus-judged ideal DCG.
- No-answer false-reference rate: no-answer queries with >=1 displayed document /
  all no-answer queries (not number of documents). Answerable empty rate: answerable
  queries with 0 display docs / all answerable. Recall excludes no-answer entirely.
- Report unjudged returned / returned and coverage of ideal-ranking judgments.
  If any gate-determining result is unjudged, gate is INSUFFICIENT_EVIDENCE rather
  than imputing relevance. Q-017 implements these future metrics; v1 evaluator
  cannot establish these gates as-is.

## 6. Preregistered quality gates (all required, not achieved claims)

| BERT gate | Locked target |
| --- | --- |
| Binary macro F1, all holdout rows | >=0.75 |
| Recall each true class | >=0.70 |
| Binary valid→hoax FPR | <=0.20 |
| Covered accuracy | >=0.85 |
| Overall coverage | >=0.60 |
| Coverage each true class | >=0.50 |
| Calibration | NLL and Brier no worse than same-checkpoint uncalibrated; ECE/bins reported |
| Baseline superiority | Positive macro-F1 delta vs frozen v1.0.0 on identical new rows; paired grouped-bootstrap 95% delta lower bound >0 |

New TF-IDF fixed baseline is fitted only on new train once, no search on holdout;
report its comparison and identities separately. Its historical refit is BLOCKED,
not a prerequisite to counterfeit with the new dataset. Main comparator is frozen
v1.0.0 actual inference on the same new data; if missing historical train prevents
credible independence, flag insufficient evidence rather than claiming proven
out-of-source generalization. No universal superiority claim from one benchmark.

| RAG gate | Locked target |
| --- | --- |
| Answerable Hit@3, grade>=2 | >=0.85 |
| Micro returned precision, grade>=2 | >=0.75; macro also reported |
| No-answer false-reference rate | <=0.10 |
| Answerable empty rate | <=0.20 |
| Hard negatives | Wrong event/entity/date explicitly error even if topic same |
| Completeness | All judgments determining gates complete; no silent unjudged imputation |
| Latency/resource | Within Section 8 caps and actual configured timeouts |

Integration mandatory at candidate qualification: actual direct/Laravel cold/warm
parity (score tolerance abs 1e-6, exact label/mapping/policy/version); immutable
model/tokenizer/preprocessing/calibration/policy/corpus/index/ranker provenance;
retry/reprocess no stale evidence; no RAG reclassification; distinguish abstention,
no-match and service failure; authorization/privacy/URL safety/escaping/queue
idempotency/export regressions absent. Required real checks cannot be skipped to
pass. Strict release validation never disabled; legacy-runner repair remains open.

## 7. Uncertainty and decision rules

Use seed 1729, 2,000 bootstrap replicates, 95% percentile intervals, linear
interpolation at 0.025/0.975. Resample **claim/event groups with replacement**,
carrying all rows/variants together, stratified by the set of true labels in each
group (valid-only/hoax-only/mixed); baseline/candidate use same group draws.
Report N, independent groups per class/stratum, bootstrap method, seed, valid and
requested replicate counts. Group assignments must be reviewed, not set to row IDs
to manufacture independence. Strata use their own group draws and support labels;
overlapping strata do not create independent tests.

Single-group strata can have degenerate CIs: retain preliminary status regardless
of interval width. Undefined denominators stay null; if <95% replicates defined,
interval evidence is insufficient. For no-answer/empty/coverage/error proportions,
also use Wilson 95% intervals at independent group level where one query/claim per
event is selected prospectively; clustered repeated events require grouped bootstrap
and report boundary limitations. At all-success/all-error boundaries bootstrap can
be misleadingly zero-width: require independent-group Wilson bound as safeguard,
not a manufactured interval from dependent rows. Report both, choose the more
conservative applicable bound. Data increases to address precision are planned
before freeze, never collected selectively after opening final results.

- **REJECT**: complete valid evaluation with any point gate failing, infeasible
  policy-validation threshold, or known security/contract violation. Save candidate.
- **INSUFFICIENT_EVIDENCE**: missing/review-pending data, incomplete judgments,
  undersupported scope/groups, unresolved mandatory real check, CI crossing a gate,
  or paired macro-F1 delta lower bound <=0 despite positive point estimate. For a
  minimum-target gate CI lower bound must meet target; maximum-target upper bound
  must meet target. Calibration paired deltas NLL/Brier must be <=0; if upper CI>0,
  evidence is insufficient (not an excuse to change T). Exact identity calibration
  with delta=0 is permitted if all other gates hold. These conservative evidence
  rules may require more data than the minimum counts; they do not lower point gates.
- **PROMOTE eligible** only if every numeric, uncertainty, data and integration gate
  passes on the one locked final candidate, with sufficient reviewed scope. This
  remains a Q-032 decision with explicit owner review, **not deployment authority**.
- Missing/inconclusive evidence never defaults to pass. Rejection after holdout
  opening consumes that holdout: future experiment needs a new prospective blind
  cycle. The opened set may only remain regression data.

## 8. Resource and experiment budget (caps, not measured SLA)

Reference measurement environment: Linux amd64, 4 host logical CPUs, ~7.6 GiB RAM
from Q-001; use serial isolated CPU jobs, offline cached models. No downloads,
training or paid API authorized by Q-004. Future execution still requires task
dependencies and resource estimate **before** allocating a run.

| Workload | Preregistered cap |
| --- | --- |
| Offline evaluation/retrieval | 2 CPUs, 3 GiB RSS per process, 20 min per run, serial |
| BERT inference, actual sequence policy | Warm p95 <=2 s/request, concurrency 1; ready <=120 s; <=2 GiB RSS |
| RAG full retrieval incl optional rerank | Warm p95 <=3 s/query, concurrency 1; ready/index build <=120 s; <=3 GiB RSS |
| Adapter/cache | No more than 0.5 s warm p95 adapter overhead; exact parity required |
| Runtime timeouts | BERT total request <30 s; RAG <15 s or stricter effective config; failed/timeouts counted, not dropped |
| Candidate BERT training budget | Q-014 smoke <=15 min/3 GiB/2 CPU; projected full run <=4 h/config, <=12 h aggregate CPU wall time, <=6 GiB RSS/2 CPU; no paid GPU without new explicit authorization |
| Search budget | One base BERT config + at most two registered directed variations; chosen config seed 42 then 43 if total budget permits; no more than 3 dev evaluations/config and one validation policy selection cycle |
| Retrieval experiments | Dense/lexical/hybrid, at most two registered pinned embedding alternatives; one CPU reranker candidate, candidate_k<=20; <=2 h cumulative CPU evaluation; no model download without task estimate/budget |

Q-014 must reject/defer runs whose estimate exceeds caps, not train indefinitely
on this host. Q-024 can reject reranker without shipping it. At Q-030 measure at
least 100 fixed dev inputs/queries, 10 warmups, three repetitions, cold startup
separately, peak RSS, p50/p95/max, hardware/threads/concurrency, cache hits/misses,
timeouts/errors. Extraction/translation/LLM latency measured separately; these
CPU component limits are not a promise about complete media processing.

## 9. Freeze schedule and reproducible run contract

Milestones, not invented calendar approvals:

1. **Q-004 commit** locks protocol/gates/budget before candidate experiments.
2. **Q-009/Q-010** lock annotation/acquisition schema and evidence access; records
   pending review cannot enter benchmark. Owner update about deleted data stays
   attached to all new dataset cards.
3. **Q-011 / Q-018 before experiments**: reviewers/adjudicator freeze IDs, texts,
   true labels, groups, time/source cuts, split membership, no-answer definitions,
   review IDs, raw/canonical hashes, row counts and freeze UTC. Holdout held by a
   reviewer/custodian outside experiment logs; developer sees only manifest/counts,
   not labels/text/per-example candidate results. Custodian not yet appointed.
4. **Q-013–Q-015 / Q-019–Q-025**: register experiment IDs/configs before each dev run;
   use checkpoint validation for checkpoint, calibration only for T, policy validation
   for threshold. Stop at budget, record negative results. Holdout never inspected.
5. **Q-016 / final RAG qualification**: hash candidate weights/tokenizer/transforms,
   T/threshold policy, adapter/cache namespace, corpus/passages/index/ranker, freeze
   baseline inference configs and evaluator/protocol code. Complete independent
   judgments for final corpus. Verify inputs against immutable manifests.
6. Custodian runs **one** locked candidate comparison on final holdout, writes sealed
   report and access log. Infrastructure retry only for documented pre-result failure
   with identical hashes, no config/seed/policy change. Record every invocation.
7. Q-032 decision preserves evidence. Any gate/protocol/data change after opening
   requires a version bump, reason, new freeze and new blind set. Never amend this
   v1 to make failed holdout pass. Deployment requires separate authorization.

Every run stores source commit and dirty-file hashes, protocol hash/version, seed,
effective config, OS/hardware/dependencies, dataset/split/review/group hashes,
model/tokenizer revision and file hashes, preprocessing/windowing contract, T/policy,
corpus/query/judgment/index/ranker identities, raw and calibrated predictions,
metric numerators/denominators per stratum, CIs, runtime/RSS/errors, and limitations.
Store text-free ID/hash reports publicly; authorized artifact location and retrieval
instructions privately as appropriate. Never stage weights/large raw corpus or PII.

Existing tooling: dataset prepare/dedup/split/manifest; train checkpoint selection,
calibrate/threshold/evaluate; strict release validator; Q-002 challenge identity;
Q-003 frozen measurements. New Q-004 reporter only summarizes supplied predictions:

```bash
PYTHONDONTWRITEBYTECODE=1 python3 bert-service/scripts/classifier_protocol_report.py \
  --predictions /authorized/run/predictions.json --baseline /authorized/run/baseline.json \
  --threshold 0.99 --bootstrap-replicates 2000 --seed 1729 \
  --output /authorized/run/protocol-report.json
```

Paths above are **schema examples, not existing artifacts**. Supply the policy's
locked threshold, not necessarily 0.99 for a new candidate. JSON array fields:
`id`, reviewed `group_id`, binary `true_label`, `predicted_binary_label`, winning
`confidence`, nonempty `strata` string map; all rows have identical stratum keys.
Candidate/baseline IDs, groups, labels and strata must match; output includes sample/
group counts, full binary metrics, selective risk/coverage, per-class denominators,
per-stratum reports, reproducible grouped CIs and paired macro-F1 delta. It does not
infer missing groups/review or issue PROMOTE; eligibility remains insufficient
until manifest/calibration/RAG/integration evidence is checked. Fixture tests verify
math/report contract, not classifier quality. Do not attach fake groups to Q-003
predictions to pretend its missing grouping/CI evidence is resolved.
