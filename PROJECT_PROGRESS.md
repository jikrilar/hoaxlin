# Project Progress Report

**Project:** hoaxlin.id, Sistem Deteksi Berita Hoax Berbasis BERT  
**Reference:** `PRD-Sistem-Deteksi-Hoax-BERT.md`  
**Audit date:** 20 August 2026 (re-audit; original audit 31 July 2026)  
**Scope:** Laravel application, database, queue pipeline, Filament admin, frontend, FastAPI service, and AI integration scaffolding  
**Code changes during audit:** None. Verified unchanged since the 31 July 2026 audit.

## Executive Summary

The project has a strong Laravel application foundation. Authentication, submission intake, history, feedback, database models, seed data, Filament resources, and a FastAPI service skeleton are implemented and covered by automated tests.

The central research and product outcome is not complete yet. There is no prepared Indonesian hoax-news corpus, no fine-tuned IndoBERT model artifact, no validated model evaluation report, and no verified live Laravel-to-FastAPI-to-IndoBERT inference path. OpenAI adapters and queue orchestration exist as integration scaffolding, but they are not production-ready and the current queue chain has a critical handoff gap.

The application can currently demonstrate the web workflow and a fake/test pipeline, but it cannot yet provide a real, trustworthy hoax classification in a deployed environment.

## Re-Audit Verification (20 August 2026)

This re-audit confirms the project is **functionally unchanged** since the original audit, with one exception: the critical queue handoff (checklist task C1) was fixed on 20 August 2026. No application source files were modified after 31 July 2026 apart from `app/Jobs/ExtractSubmissionText.php` and `tests/Feature/AiPipelineTest.php`. The overall completion estimate has been revised from **58%** to **60%** to reflect the now-connected pipeline chain.

Verification performed on 20 August 2026:

- **Queue handoff gap persists (critical).** *Update 20 Aug 2026: FIXED.* `ExtractSubmissionText` now dispatches `ClassifySubmission` on the `inference` queue after successful extraction (and on the idempotent already-extracted recovery path) via `continueToClassification()` (`app/Jobs/ExtractSubmissionText.php:40,63,71-74`). A new full-flow feature test (`test_process_submission_drives_full_pipeline_to_completion`) dispatches only `ProcessSubmission` and asserts the pipeline completes. The complete suite passes 30/30 tests with MySQL running.
- **Test suite currently cannot run.** PHPUnit depends on the real MySQL database (`phpunit.xml` does not define a separate test database; the SQLite `:memory:` configuration remains commented out). MySQL was not running during this re-audit, so every feature test failed with `SQLSTATE[HY000] [2002] ... actively refused` (host 127.0.0.1, port 3306). The test suite is only runnable when the XAMPP MySQL service is started. The previous claim that the suite is "covered by automated tests" is therefore conditional on a live database.
- **Git repository is not valid.** A `.git` directory exists but `git status`/`git log` report "not a git repository", so the codebase is currently **not under version control**. Any code change since the original audit would not be tracked.
- **Frontend legacy wording persists.** `resources/views/hasil.blade.php:24` still tells the user "Analisis AI belum dijalankan pada tahap implementasi ini" even though pipeline scaffolding now exists.
- **Public result route still lacks ownership enforcement.** `app/Http/Controllers/DeteksiController.php:13` loads any submission by ID with `findOrFail($id)` and no user scoping, so `/hasil/{id}` can expose another user's submission.
- **No Livewire components exist.** `app/Livewire/` is absent; the frontend remains Blade + JavaScript with no polling or reactive stage updates.
- **FastAPI service unchanged.** No model artifact, no FastAPI test suite, no deployment configuration; readiness remains gated on a `BERT_MODEL_PATH` that does not exist.
- **`opencode/` directory** at the project root is a local opencode CLI installation (node modules), not application code.

No new modules, features, migrations, or tests were added since the original audit. The Recommended Next Tasks and Risk sections below remain fully applicable.

## Overall Completion

**Estimated overall completion: 60%**

This estimate is outcome-weighted against the PRD, not based on the number of files present.

| Area | Weight | Completion | Weighted result |
|---|---:|---:|---:|
| Product foundation and backend | 20% | 90% | 18.0% |
| Authentication and user features | 10% | 95% | 9.5% |
| Submission and history workflow | 15% | 85% | 12.75% |
| Database and admin foundation | 15% | 75% | 11.25% |
| AI orchestration scaffolding | 15% | 70% | 10.5% |
| FastAPI and IndoBERT serving | 10% | 35% | 3.5% |
| Dataset preparation and model training | 10% | 5% | 0.5% |
| Production operations and non-functional requirements | 5% | 20% | 1.0% |
| **Total** | **100%** |  | **67.0% before critical-path adjustment** |

The score is conservatively adjusted to **60%** because the core classifier model and verified end-to-end inference are still absent. The pipeline queue chain itself is now fully connected (verified by tests) since the 20 August 2026 fix.

## Completed Modules

### Laravel application foundation

- Laravel 12 application structure is present.
- Laravel authentication uses native guards, events, signed verification URLs, and the password broker.
- Form Requests are used for most authentication, profile, submission, history, and feedback validation.
- Controllers are generally thin and delegate to actions, requests, or services.
- Vite/Tailwind frontend build is configured.

### Authentication

Implemented:

- Registration
- Login
- Remember-me support
- Logout
- Email verification
- Verification email resend
- Forgot-password email
- Password reset completion
- Profile update
- Email change requiring re-verification
- Password update with current-password validation
- Account deletion with password confirmation

Feature tests cover these paths.

### Submission intake

Implemented validation and storage for:

- Text
- Image upload
- Video upload
- Video URL input
- Article URL input

Media is stored on the private default filesystem disk. Submission creation occurs inside a transaction and dispatches work after commit.

### Submission history

Implemented:

- Authenticated user history
- Pagination
- Search across raw text, extracted text, and URLs
- Label filters
- Input-type filters
- Status filters
- Newest/oldest/confidence sorting
- Query-string preservation across pagination
- Owner-scoped detail page
- Cross-user detail access protection
- Full-history summary counts

### Feedback

Implemented:

- Correct/incorrect prediction feedback
- Optional comment
- Automatic timestamps
- Submission ownership checks
- Requirement for an existing detection result
- Duplicate feedback prevention
- Feedback display on the result page

### Domain models and database foundation

Implemented models and relationships for:

- Users
- Submissions
- Detection results
- Feedback
- Datasets
- Admin logs
- Submission processing events

Database constraints include:

- Foreign keys
- Cascade/null/restrict delete rules
- Unique detection result per submission
- Unique feedback per submission/user
- Indexed user/status/label/time query paths

### Seed data and factories

Implemented:

- Indonesian sample users
- Administrator
- Valid, hoax, and `meragukan` dataset records
- Text/image/video/URL submissions
- Detection results with multiple model-version examples
- Correct and incorrect feedback
- Admin log examples
- Factories for domain models
- Idempotent `DatabaseSeeder` orchestration

Seeder relationship and repeat-run tests pass.

### Filament resources

Resources exist for:

- User
- Dataset
- Submission
- Feedback
- Detection result

Users and datasets have management forms. Submissions, feedback, and detection results are intentionally read-only resources, which is appropriate for preserving operational and audit integrity.

## Partially Completed Modules

### AI pipeline orchestration

Implemented scaffolding includes:

- `TextExtractor` contract
- `Classifier` contract
- `Explainer` contract
- Extracted-text, classification, and explanation DTOs
- Processing-stage and outcome enums
- Processing event table/model
- Extraction, classification, and explanation jobs
- Idempotency locks
- Processing-state persistence
- Retry counts and failure metadata
- BERT caching decorator
- BERT circuit-breaker decorator
- OpenAI explanation fallback behavior

However, this was not yet a complete working asynchronous pipeline.

**Critical issue (fixed 20 August 2026):** `ProcessSubmission` dispatches `ExtractSubmissionText`, but the extraction job previously did not dispatch `ClassifySubmission` after successful extraction, so the classifier and explanation jobs were not reached by the normal submission flow. `ExtractSubmissionText` now dispatches `ClassifySubmission` on the `inference` queue after success, including an idempotent recovery dispatch when the job is re-run against an already-extracted submission. Existing AI tests were also extended with a full-flow test that dispatches only `ProcessSubmission` and asserts the entire chain completes (`test_process_submission_drives_full_pipeline_to_completion`).

Remaining issues:

- `ProcessSubmission` does not define explicit retry/timeout policy.
- Explanation failures are converted directly to degraded results by `OpenAiExplainer`; the explanation job retry configuration is therefore not meaningfully used for transient OpenAI failures.
- The pipeline does not notify the frontend or user after completion.
- There is no scheduled retry/recovery command for failed submissions.
- Processing events are persisted but not exposed in Filament or the frontend.

### Extraction services

Implemented:

- Text normalization
- Stored extracted-text reuse
- Basic article fetching
- OpenAI image OCR adapter
- OpenAI video transcription adapter

Still incomplete:

- Video URL extraction is not implemented. The current video extractor supports a stored media path, not a remote video URL.
- Article extraction uses `strip_tags()` rather than a robust readability/content extractor.
- Redirect handling and DNS rebinding protection require stronger implementation and testing.
- Media duration, decoded size, and resource limits are not enforced.
- Upload malware scanning is absent.
- OpenAI OCR/transcription adapters do not share the circuit breaker used by the explanation service.

### OpenAI integration

The project has direct HTTP adapters for chat explanation, image OCR, and audio transcription.

Partial status:

- Uses configured model names and timeouts.
- Has cache key design for OCR, transcription, and explanation.
- Keeps BERT as the classification authority.
- Provides degraded explanation fallback.

Missing or incomplete:

- No real OpenAI contract tests with `Http::fake()`.
- No structured-output enforcement for explanation responses.
- No token/cost calculation; stored estimated cost is always zero.
- Configured rate limits and monthly quota are not enforced.
- No OpenAI usage ledger or budget accounting exists.
- OCR/transcription request size and media limits are incomplete.
- OpenAI credentials and request payload handling need a security/privacy review.

### Queue infrastructure

Implemented:

- Database queue migration
- Queue dispatch after database commit for initial submission
- Named extraction, inference, and explanation queues in job dispatches
- Job retry/backoff properties
- Redis queue configuration placeholder
- Media queue retry window configuration

Incomplete:

- The default local environment still uses the database queue.
- Redis/Horizon is not installed or configured as the production queue system.
- Worker supervisors are not defined.
- Queue-specific worker commands and deployment configuration are not documented comprehensively.
- Queue chain handoff is currently broken after extraction, as noted above.
- No queue monitoring, alerting, oldest-job-age tracking, or failed-job operations are implemented.
- The queue timeout/retry hierarchy is not consistently enforced for all connections.

### FastAPI service

Implemented:

- FastAPI application factory
- Environment configuration
- Pydantic request/response contracts
- Bearer-token authentication
- Request IDs
- Liveness endpoint
- Readiness endpoint
- Version endpoint
- Startup model loading
- Bounded inference concurrency
- CPU/GPU-compatible Hugging Face runtime structure
- Structured service errors
- `requirements.txt`
- Python virtual environment setup documentation

Incomplete:

- No fine-tuned model is configured.
- Readiness intentionally returns `503` with no `BERT_MODEL_PATH`.
- No FastAPI test suite exists.
- No container image or production process manager configuration exists.
- No metrics endpoint or Prometheus integration exists.
- No model artifact checksum or model registry process exists.
- No explicit label-map validation guarantees `valid`, `hoax`, and `meragukan` compatibility.
- No load or concurrency test has been run.

## Missing Modules

The following PRD-relevant modules are missing or substantially incomplete:

### Dataset preparation

- No raw dataset ingestion workflow
- No source adapters for Indonesian fact-check datasets
- No canonical dataset schema
- No duplicate/near-duplicate detection
- No event-group split protection
- No label reconciliation workflow
- No train/validation/test artifact generation
- No dataset manifest or provenance tracking
- No license/provenance audit records
- No balanced topic/source statistics

### IndoBERT training

- No training scripts
- No tokenizer preparation workflow
- No fine-tuning configuration
- No checkpoint selection process
- No validation threshold calibration
- No probability calibration
- No evaluation report
- No confusion matrix artifact
- No per-class precision/recall/F1 report
- No model export/versioning workflow

### Production model deployment

- No actual model artifact under deployment control
- No staging model environment
- No model rollback procedure
- No FastAPI deployment container
- No GPU/CPU resource profile
- No load test
- No canary model release process

### Live progress updates

The PRD describes Blade/Livewire progress updates, but the current frontend is primarily Blade plus JavaScript. There are no application Livewire components for:

- Submission polling
- Stage progress
- Completion notifications
- Retry/failure status updates

### Admin monitoring

Missing or incomplete:

- Admin-log Filament resource
- Processing-event Filament resource
- Dashboard statistics widgets
- Trend charts
- Label distribution monitoring
- Model-version monitoring
- Failed-job monitoring
- Cost/quota dashboards

### Security and abuse controls

- No CAPTCHA integration
- No explicit per-IP/per-account quota for submissions beyond a basic route throttle
- No SSRF test suite
- No malware scanning
- No media retention scheduler
- No audit trail implementation for all Filament mutations
- No production secret-management integration

### Export and reporting

- No PDF export implementation
- No CSV export implementation
- No dataset export for model training
- No evaluation report export

## Backend Architecture Status

**Status: Partially implemented, approximately 75%.**

The backend has a clean Laravel structure with Form Requests, Actions, models, contracts, DTOs, services, and jobs. The architecture separates public request handling from processing concerns better than a typical prototype.

Strengths:

- Thin controllers
- Dedicated validation
- Explicit domain DTOs
- Contract-based AI ports
- Separate FastAPI service
- Transactional submission creation
- Private media storage
- Durable processing metadata

Weaknesses:

- Service bindings are manually instantiated rather than fully container-driven/tagged.
- The extraction resolver is manually assembled in `AppServiceProvider`.
- There is no complete application-level pipeline/orchestrator service.
- Domain state transitions are represented by strings in the model/database path even though enums exist.
- Public `/hasil/{id}` still loads any submission by ID without ownership restriction.
- Failure/notification behavior is not end-to-end complete.

## AI Pipeline Status

**Status: Scaffolding implemented; production inference not operational.**

Current intended flow:

```text
Submission
  -> ProcessSubmission
  -> ExtractSubmissionText
  -> ClassifySubmission
  -> GenerateSubmissionExplanation
  -> completed
```

Actual normal flow now runs the full chain end-to-end: extraction dispatches classification, which dispatches explanation, and the submission is marked completed. The handoff was verified with a full-flow feature test on 20 August 2026.

The data model can now record:

- Processing stage
- Content hash
- Attempts
- Last error service/code
- Model scores
- Inference duration
- Cache flags
- Explanation status/model/cache state
- Token counts
- Estimated cost
- Processing events

The pipeline is therefore structurally prepared but not operationally verified.

## FastAPI Status

**Status: Service runtime ready; model not configured.**

Verified behavior:

- Process starts successfully with the project virtual environment.
- `/health/live` returns `200`.
- `/version` returns `200`.
- `/health/ready` returns `503` when no model is configured, which is correct.
- Dependencies pass `pip check`.
- Python modules compile successfully.

The service does not yet deliver a real prediction because no trained model is present. That is expected at this stage, but it blocks functional completion.

## IndoBERT Integration Status

**Status: Adapter and serving contract implemented; trained model absent.**

Implemented:

- Hugging Face model loading path
- Tokenizer/model startup loading
- Softmax scores
- Label and confidence response
- Model-version response
- Laravel HTTP adapter
- Response validation
- Confidence threshold mapping to `meragukan`
- BERT result caching
- BERT circuit breaker

Missing:

- Prepared Indonesian training corpus
- Fine-tuned IndoBERT artifact
- Evaluation results
- Label-map verification against FastAPI configuration
- Real Laravel/FastAPI contract test
- Real inference integration test with a model artifact
- Production model deployment configuration

## OpenAI Integration Status

**Status: Partial adapters, not production-ready.**

Implemented adapters:

- Vision OCR
- Audio transcription
- Chat explanation

OpenAI is correctly positioned as a supporting service rather than the classifier, matching the PRD.

Critical gaps:

- Cost fields are persisted but never calculated.
- Quota settings exist but are not enforced.
- No usage tracking table exists.
- No structured JSON response schema is enforced for explanations.
- OpenAI extraction failures need stronger retry/circuit-breaker handling.
- Video URL handling is missing.
- No provider integration tests exist.

## Queue Status

**Status: Partially implemented.**

Database queues work for local development. Job classes include retries, backoff, locks, and stage metadata. Production scalability is not ready because:

- Redis/Horizon is not installed.
- The default `.env.example` still uses database queues.
- Queue workers are not separated into deployable supervisor groups.
- The extraction-to-classification handoff was missing; fixed on 20 August 2026 (see AI Pipeline Status).
- Long-running media jobs need dedicated worker and process limits.
- No failed-job recovery runbook exists.

## Database Status

**Status: Strong foundation, approximately 85%.**

Implemented:

- Core PRD tables
- Foreign keys and delete policies
- Unique constraints
- Query indexes
- AI pipeline metadata migration
- Processing event ledger
- Models and relationships
- Factories and realistic seeders
- Idempotency/relationship seeding tests

Remaining database work:

- Add processing-event and admin-log Filament visibility.
- Add explicit cost/usage ledger if OpenAI budget control is required.
- Add retention/deletion metadata for uploaded media.
- Consider soft deletion or explicit user data-erasure records.
- Add database-level enum/enum-cast consistency checks.

## Frontend Status

**Status: Functional prototype, approximately 70%.**

Implemented:

- Responsive landing page
- Four input tabs
- Text input
- Image upload UI
- Video upload/video URL UI
- Article URL input
- Loading overlay and progress-step visual treatment
- Authentication pages
- Profile page
- History list with filters/search/pagination
- Submission detail view
- Feedback form
- Privacy and informational pages

Limitations:

- Progress steps are currently visual JavaScript behavior, not live backend stage state.
- No Livewire component performs polling or reactive status updates.
- Pending result text still contains legacy wording that says AI analysis has not run, even though the pipeline scaffolding now exists.
- Result presentation has legacy fallback/demo branches and hard-coded confidence fallback values.
- Public result route does not enforce submission ownership.
- No user-facing retry action.
- No clear differentiated UI for `failed`, `processing`, `explanation unavailable`, and `completed with classification`.
- No media preview/download flow for private stored media.

## Production Readiness

**Status: Not production-ready.**

The project is suitable for local development and architectural demonstration, but not public deployment.

### Blocking production issues

- No trained IndoBERT model
- No verified real inference path
- Broken queue stage handoff
- No Redis/Horizon production setup
- No OpenAI quota/cost enforcement
- No CAPTCHA or robust abuse prevention
- No robust SSRF protection tests and redirect handling
- No media malware scanning or retention scheduler
- No observability/metrics/alerts
- No deployment manifests or health-based process supervision
- No production object storage configuration
- No full integration/end-to-end test against real dependencies

### Operational status

Current local drivers are:

- MySQL database
- Database cache
- Database queue
- Database sessions
- Log mailer

These are acceptable for development but database cache/queue/session drivers will limit throughput and operational visibility under production load.

## Technical Debt

### High priority

1. ~~Fix the queue chain so successful extraction dispatches classification and successful classification dispatches explanation exactly once.~~ **Done 20 August 2026** — extraction→classification handoff added; classification→explanation already existed. Verified by `test_process_submission_drives_full_pipeline_to_completion`.
2. Replace or remove legacy demo UI branches in `hasil.blade.php`.
3. Add real integration tests for FastAPI, BERT responses, OpenAI failures, retries, and cache hits.
4. Implement the dataset preparation and model-training workflow.
5. Add production-safe URL fetching and media processing limits.
6. Enforce OpenAI quota, rate limits, and cost accounting.
7. Add ownership protection to the public result endpoint.

### Medium priority

1. Use tagged container bindings for extractor implementations.
2. Centralize state transitions instead of updating status strings in multiple jobs.
3. Add a processing-event admin resource.
4. Add admin-log resource and automated audit logging for Filament mutations.
5. Add Livewire polling for stage progress.
6. Move private media to S3-compatible object storage for multi-instance deployment.
7. Add model and prompt version metadata to all relevant screens.

### Lower priority

1. PDF and CSV export.
2. Dashboard charts and trend visualizations.
3. Richer dataset topic metadata.
4. Challenge-set evaluation display.
5. User notification preferences.

## Recommended Next Tasks

### Priority 0: Make the existing pipeline actually run

1. ~~Add the missing extraction-success dispatch to `ClassifySubmission`~~ **Done 20 August 2026** — `ExtractSubmissionText` dispatches `ClassifySubmission` on the `inference` queue after successful extraction, with idempotent recovery on re-runs.
2. Verify the complete flow by submitting text through the HTTP endpoint, running a real queue worker, and checking the database result. (Full-flow feature test added; real queue-worker verification still pending.)
3. ~~Add a feature test that dispatches only `ProcessSubmission` and asserts the full pipeline completes~~ **Done 20 August 2026** — `test_process_submission_drives_full_pipeline_to_completion` passes.
4. Ensure failed attempts are not left in `processing` forever after the final retry. (Still open — checklist task C7.)

### Priority 1: Prepare the research dataset

1. Select licensed Indonesian fact-check and valid-news sources.
2. Build the canonical dataset schema.
3. Implement label mapping to binary `valid`/`hoax` initially.
4. Remove exact and near duplicates.
5. Prevent event leakage across train/validation/test.
6. Produce versioned train, validation, test, challenge, rejected, and manifest artifacts.

### Priority 2: Fine-tune and evaluate IndoBERT

1. Select a compatible Indonesian pretrained checkpoint.
2. Fine-tune binary classification.
3. Evaluate accuracy, precision, recall, macro F1, confusion matrix, and calibration.
4. Tune the `meragukan` confidence threshold on validation data only.
5. Export a versioned model with `id2label` and `label2id` mappings.

### Priority 3: Prove the real service integration

1. Configure the FastAPI model path.
2. Add a contract test for `/predict`.
3. Add Laravel HTTP fake tests for success, timeout, `429`, `503`, malformed JSON, and invalid labels.
4. Run a real local Laravel-to-FastAPI inference test using the exported model.
5. Verify model version and confidence values are persisted correctly.

### Priority 4: Complete multimodal processing

1. Implement video URL download/extraction safely.
2. Add robust article readability extraction.
3. Add media duration and decoded-size limits.
4. Add malware scanning and retention cleanup.
5. Add OpenAI quota/cost tracking and provider circuit breakers.

### Priority 5: Production operations

1. Install Redis and Laravel Horizon.
2. Define queue supervisors for text, media, inference, explanation, and notifications.
3. Move private media to S3-compatible storage.
4. Add structured logs, metrics, traces, and alerts.
5. Add deployment health checks, queue restart/drain procedures, and rollback documentation.

### Priority 6: Product completeness

1. Replace JavaScript-only progress with Livewire polling or a broadcast/event-driven status view.
2. Add failed-submission retry UX.
3. Add processing-event and admin-log Filament resources.
4. Add statistics widgets and model-version visibility.
5. Implement CAPTCHA and stronger per-user/IP rate limiting.
6. Add PDF/CSV exports if still required by the final scope.

## Project Risks

### Model and dataset risk

- Dataset quality and label consistency may dominate system accuracy.
- Source or topic leakage could produce misleadingly high test scores.
- A binary model plus confidence-derived `meragukan` may not represent genuinely ambiguous claims well.
- IndoBERT may perform poorly on OCR noise, transcription errors, slang, and short social-media messages.

### Integration risk

- FastAPI readiness remains unavailable until a compatible model artifact exists.
- A malformed or version-incompatible model output can block all classification jobs.
- The current queue handoff gap can leave submissions permanently pending/processing.
- OpenAI rate limits, outages, or budget exhaustion can delay multimodal inputs.

### Security risk

- Public article URL fetching can expose SSRF vulnerabilities if redirect and DNS handling are incomplete.
- Uploaded media can contain malware or resource-exhaustion payloads.
- Public result IDs may expose another user’s submission through `/hasil/{id}`.
- Default local secrets and production-like environment examples need operational hardening.

### Privacy and compliance risk

- Uploaded images and videos may contain personal or sensitive information.
- OpenAI processing requires a documented data-retention and consent policy.
- No automated media deletion/retention workflow is implemented.
- Logging must avoid storing raw user content and provider payloads.

### Operational risk

- Database queues and cache will not scale adequately for public multimodal usage.
- No monitoring currently detects queue backlog, model unavailability, or OpenAI cost growth.
- A GPU model deployment may have memory and concurrency constraints not covered by load testing.

### Academic/reproducibility risk

- No training dataset manifest, model artifact, evaluation report, or experiment configuration exists yet.
- Without fixed dataset and model versions, final accuracy claims will not be reproducible.
- The PRD’s required accuracy, precision, recall, F1, and confusion-matrix evidence is not yet available.

## Final Assessment

The project has progressed beyond a basic prototype on the Laravel side and now contains a credible application and integration skeleton. The authentication, submission, history, feedback, database, seed data, and admin foundations are demonstrable.

The project should not yet be presented as a completed AI system. The highest-value work is now the research pipeline: prepare a defensible Indonesian dataset, train and evaluate IndoBERT, configure FastAPI with the exported artifact, fix the queue handoff, and validate the real end-to-end behavior under failure conditions.

Once those items are complete, the remaining work is primarily production hardening, observability, abuse prevention, and UI refinement.
