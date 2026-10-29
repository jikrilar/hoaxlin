# Q-005 — Safe stage-input trace

**Status: DONE (instrumentation and fixture validation only).**
Base commit `88a10cfa873dddb3f6139d9225f677d41c77d752` (Q-004 protocol), branch
`quality/02-input-contract`. Q-006 not started. Owner's two untracked files remain
unchanged and outside this commit. Historical dataset loss, Q-003 partial baseline
and legacy runner blockers remain open; no training or inference was run here.

## Trace contract and boundaries

Uses existing `submission_processing_events.metadata.input_trace`, no new schema,
public endpoint, logger text, media URL or preview. Records exact-byte SHA-256,
Unicode character and byte lengths, presence, language and language basis,
transform version, parent fingerprint, changed-from-parent, local truncation and
service-observability status. These are **not** normalized cache hashes.

| Boundary | Recorded trace | Important meaning |
| --- | --- | --- |
| Extraction started | `raw_input` | May be absent for uploads; no media bytes/path/URL copied. Hash not proof of external page content |
| Extraction succeeded | raw, `extractor_output`, normalized `extracted_text` | Extractor output before normalizer fingerprinted; normalized parent linkage reports casing/whitespace changes |
| Translation started/succeeded | `translation_input`, optional `translated_text`, chosen `analysis_text` | Nontranslated output is absent, not fabricated; input language from translation metadata, translated target `id` |
| Classification started/succeeded | `classifier_input` from existing analysis accessor | Translated-first / extracted-fallback policy unchanged; exact text entering Classifier interface, not proof of cache/HTTP/tokenizer internals |
| Retrieval started/succeeded | `analysis_text`, `retrieval_query` with parent hash | Existing trim + first 4,000 Unicode chars; truncation measured against trimmed input; failed request retains started fingerprint |

`truncated=false` on identity analysis selection means no local character cut;
it does **not** mean BERT consumed all tokens. Normalization, extraction and
translation loss cannot be inferred from lengths: their local truncation flag
is null and content changes remain visible via parent hash/change flag.
`service_truncated=null`, tokens=null and explicit unavailable status mean actual
BERT/embedding tokenizer was not available in the Laravel boundary. No character/
word approximation is presented as tokens. Q-006/Q-022 must measure service token
budgets with actual tokenizer; Q-007 must address normalized-cache identity. The
trace does not claim cache warm response was inferred on identical raw bytes.

Transform versions describe current local behavior, not newly enforced release
contracts: `raw-input:identity-v1`, provider-unversioned extractor output,
`text-normalizer:<app.ai_normalizer_version>`, translation prompt config version,
analysis translated-first/extracted-fallback v1, retrieval trim-head-4000 v1.
Provider implementation and translation semantic fidelity are not verified by a
version string. Existing provider/model/cached metadata remains available.

Hashes and lengths are linkable sensitive metadata, **not anonymization**. They
stay in access-controlled DB processing events, subject to existing submission
authorization/deletion, never added to public status/export or general operational
logs. No preview mode was added, no PII duplicated. SHA-256 is unkeyed as existing
artifact tooling; low-entropy text can be guessed, so traces must not be published
as user datasets. No retention extension or direct media storage access introduced.

## Implementation and fixture report

- `InputTrace` builds text-free metadata; does not normalize/translate/truncate.
- Jobs pass fingerprints into existing started/succeeded events. `markProcessing`
  gains optional metadata, `markExtracted` optional trace, retaining all callers.
- `ProcessingEventRecorder` and `Submission::analysis_text` did not need changes:
  the former already persists metadata; the latter's selected string is fingerprinted.
- No enum, label, threshold, pipeline state, queue, persistence authorization,
  classifier/retriever signature, evidence or cache behavior changed.
- `InputTraceTest` invokes actual jobs/state machine/event persistence with only
  external extraction/translation/classifier/retrieval outputs replaced by explicit
  unit fixtures; no fake evidence records, approval or quality result is generated.
  Typed text, article, image OCR, video transcript, video-link transcript boundaries
  are covered; `video_url` extractor DTO correctly uses existing `video` enum.
- Fictional fixture contains name, negation, date, nominal and unit. Checks show
  normalized facts survive and classification/retrieval receive exact expected text;
  casing change is flagged. Translation fixture preserves these facts in supplied
  output, with distinct hashes/language; this is **not testing real LLM translation**.
- Long multibyte text query is exactly 4,000 chars with truncated=true, full classifier
  input untouched. Labels hoax/valid/meragukan remain unchanged after instrumentation.
- Failed retrieval then retry keeps same query fingerprints on both started events;
  no text/PII in event metadata, no placeholder evidence. Provider error uses existing
  safe failure reporting. Existing tests cover real adapter serialization and fakes.

## Commands and actual results

1. `git status --short --branch`: two original untracked owner files only.
   `git switch -c quality/02-input-contract`: new phase B branch from Q-004.
2. Read scoped jobs, accessor, recorder, state machine, extraction/translation DTOs,
   normalizer and direct pipeline tests. No raw dataset/model loaded.
3. `bash reports/ai-quality/20261029-q005/run-tests.sh --filter=InputTraceTest`:
   initial RED 2 failures (missing trace); initial test invocation with `Queue::fake`
   accidentally prevented dispatchSync execution. Corrected tests to call actual job
   handle via container, keeping only follow-up dispatch fake. Corrected fixture DTO
   enum for video_url. To ensure regression evidence is real, reran final tests
   with five modified app files read-only overlaid from **pre-Q-005 HEAD**:
   `/tmp/opencode/q005-baseline-tests.sh --filter=InputTraceTest` -> **3 failed**
   (missing trace on real executed stages), exit 2. No user changes reverted.
4. GREEN new trace tests: **3 passed, 167 assertions** (0.80 s). Final pipeline/
   translation/retrieval regression command:

   ```bash
   bash reports/ai-quality/20261029-q005/run-tests.sh --filter='InputTraceTest|RetrieveSubmissionEvidenceTest|PipelineIntegrationTest|OpenAiTranslatorTest|TranslationProvenanceExportTest|AiPipelineTest'
   ```

   Exit 0: **25 passed, 287 assertions, 3.15 s**. Expected safe error logs for
   simulated failures, no actual HTTP/API/provider access (network none).
5. Full `bash reports/ai-quality/20261029-q005/run-tests.sh` final run: exit 1,
   **754 passed, 4 failed, 25 warnings, 4 skipped, 5,415 assertions, 37.96 s**.
   Four failures in owner's untracked `FilamentAuditObserverTest`:
   - `writer persists only allowlisted user changes with private values redacted`
   - `writer ignores unknown ...` (unknown profile_payload retained)
   - `writer keeps safe dataset ...` (text/source redaction)
   - `writer redacts submission ...` (source_url/private content redaction)
   Full method identifiers available in supplied test; assertions at lines
   54,84,109,149. Repeated with source pre-Q-005 overlay: **same 4 failures**,
   13 assertions, 0.79 s. Not introduced by Q-005; not fixed or test weakened.
   Warnings: 25 HTML `DOMDocument::loadHTML` warnings in progress presentation.
   Skips: 4 `RealBertInferenceTest` cases, service intentionally not running.
   Suite is **not green** and skips do not prove real inference.
6. First full test run had one extra fixture-environment failure: image omitted
   root TASK.md. Added read-only checkout mount, reran; DocumentationWorkflowContract
   passed. Disabled PHPUnit result-cache writes in read-only image via existing
   `--do-not-cache-result` option (initial warning was test cache only).
7. Pint on seven changed PHP files: initial issues import order/unused fully
   qualified references; corrected via patch after formatting an isolated temp
   copy. Final `vendor/bin/pint --test` **PASS 7 files** in pinned test image.
8. Final checksum, source diff and staged whitespace checks captured in
   `verification.json`. No browser/build/Python training/service suite was run:
   no UI or Python/runtime model implementation changes.

## Resource / artifact identity

Runner saved here uses image
`sha256:7a9c88f9b9345037ef841e4366bc31abe57b7ef29bdd449b9f7f599bfd9e7eb3`,
PHP 8.4.11, Laravel 12.64.0; vendor matched composer.lock at Q-003, hash checked
again in verification. Current app/config/tests/database/views/routes mounted
read-only; DB SQLite in-memory, cache/session array, writable tmpfs only.
2 CPU/2 GiB cap, network none, no host .env or user DB/storage mounts. Peak RSS
not measured. Key in runner is fixture only. No download/model load/paid API call.
Model/tokenizer/corpus unchanged from Q-001/Q-003; no new semantic metric, seed
N/A (deterministic fixture processing). No claim quality improved from test passes.

## Files / handoff

Changed: `app/Jobs/{ExtractSubmissionText,TranslateSubmissionText,ClassifySubmission,RetrieveSubmissionEvidence}.php`,
`app/Services/Pipeline/SubmissionStateMachine.php`.
New: `app/Services/Pipeline/InputTrace.php`, `tests/Feature/InputTraceTest.php`,
this execution log, `run-tests.sh`, `verification.json`.
No schema/vendor/dependency/UI changes. Commit:
`feat(ai-quality): trace classification and retrieval inputs safely`.
No push/merge/deploy or automatic Q-006. Historical data/runner/reviewer blockers
remain; service-token observability explicitly unknown rather than fabricated.
