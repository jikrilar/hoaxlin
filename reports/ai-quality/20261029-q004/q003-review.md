# Q-003 review and owner provenance update

Review date: 29 October 2026. Reviewed source HEAD:
`702b4e10d079e083ccd3cfb365835bd596958501`.
This addendum does **not** overwrite Q-003 measurements, historical manifests,
or statuses in the owner's untracked audit task document.

## Evidence checked

All **11** hashes in Q-003 `verification.json:artifacts` match local files.
Read the execution log and structured BERT/RAG/adapter results:

- Strict packaged release validator succeeded before measured BERT inference.
  120 historical challenge rows: matrix `[[6,54],[5,55]]`, 85 covered, 36 correct
  among covered; no binary/served label changes against historical predictions.
- Direct real HTTP and bound Laravel adapter with cold/warm array cache succeeded
  for six fixed inputs. This is not Redis, queue/persistence, or full E2E coverage.
- Real pinned embedding evaluation succeeded on both corpus versions. Active KB
  results rely on only 20 legacy queries and old-corpus judgments; preliminary.
- TF-IDF figures are **saved-prediction recalculation**, not a historical refit.
- The legacy BERT entrypoint failed: metadata expects `model_name` whereas
  packaged manifest supplies `model`; checksum parser expects string hashes,
  not `{size,sha256}` entries. Full runner is not qualified. Separate strict
  serving path measurements are not evidence this entrypoint was fixed.
- Q-002 full BERT suite's five Windows-path API test failures remain known;
  Q-003 did not claim the full suite passed. They reappear in Q-004 validation.

## Owner update: historical data deleted

Owner states original IndoBERT v1.0.0 training dataset was deleted. Historical
`valid_antara.csv` and all processed partitions (`train.jsonl`, `all.jsonl`, etc.)
are unavailable. The Kaggle re-download named `komdigi_hoaks.csv` has been updated
by its author; it is **not** an identical historical artifact.

Observed locally at review: `datasets/komdigi_hoaks.csv` is absent from this
checkout. This does not contradict availability elsewhere to the owner. Do not
search outside authorized paths or invent a current-source checksum. Future
acquisition inventory in Q-008–Q-011 must identify its actual location/version/hash.

| Open blocker | Status and allowed action |
| --- | --- |
| Historical TF-IDF refit | BLOCKED, original fit data lost. New corpus fit must get a new dataset/run identity, not impersonate old results |
| Historical train/challenge overlap revalidation | BLOCKED, original corpus lost. Never replace old manifest/checksum to match updated CSV; no claim of proven historical independence |
| Legacy packaged-release runner compatibility | BLOCKED, repair still needed with strict manifest tests and corruption rejection. Do not disable strict release validation |
| Corpus-aligned RAG/no-answer judgments | BLOCKED pending independent human review in Q-018; never label unjudged results automatically |

**Q-003 remains BLOCKED with useful partial evidence.** Missing historical data
is a provenance limitation, not a reason to erase the frozen model/report. New
corpus audit/annotation/split work cannot retroactively complete this reproduction.

## Why Q-004 may proceed

Task Q-004 explicitly depends on Q-002 plus **available** Q-003 baseline evidence,
not full Q-003 completion. That evidence is adequate to preregister protocol/gates
without training or opening a new holdout. Actual release qualification still
requires sufficient new reviewed data, credible independence, strict artifact
checks, and mandatory real-service/integration evidence. No future promotion
decision is made by this review.
