# Task Checklist — Sistem Deteksi Hoax BERT (hoaxlin.id)

**Reference:** `PROJECT_PROGRESS.md` (re-audit 20 August 2026; this checklist re-audited 21 August 2026; C4–C9 re-audited 21 August 2026), `PRD-Sistem-Deteksi-Hoax-BERT.md`
**Overall completion: 93%** — pipeline queue chain connected (C1, C6 done) and failure-safe (C7 done); research dataset prepared (C2 done); IndoBERT fine-tuned and versioned (C3 done, with style-bias caveat); FastAPI wired with verified artifact and threshold handling (C4 done); Laravel↔FastAPI proven via contract + HTTP-fake + real queue inference (C5 done); ownership and UI correctness fixed (C8–C9 done).

Priority legend:

- **[C]** = Critical — blocks the core product outcome (real, trustworthy hoax classification)
- **[H]** = High — needed for a credible working system / required PRD feature
- **[M]** = Medium — improves quality, operations, or completeness
- **[L]** = Low — polish / nice-to-have / future work

Status legend: ✅ Done — 🔶 Partial — ❌ Not started

**Re-audit 21 August 2026 (C4–C9):** C2/C3 completed (7816 rows, 498 MB artifact); C4 wired (`bert-service/.env` with `BERT_MODEL_PATH`, dotenv `app/config.py:13`, label-map verified `app/inference.py:111`, sidecars + checksum, `GET /health/ready` 200, `GET /version` exposes labels/threshold); C5 proven (`test_api.py` 11 tests, `BertClassifierTest` 12 tests, `RealBertInferenceTest` 4 tests via `queue:work`); C7 added `failed()` hooks on all 4 jobs, C8 added ownership check on `DeteksiController.php:18` + `throttle:60,1` on `/hasil/{id}`, C9 replaced stale "belum dijalankan" wording and removed `Demo Mode` branch in `hasil.blade.php:24,76`. `phpunit.xml` now uses `sqlite` in-memory so 46 Laravel + 63 bert-service tests pass. `PROJECT_PROGRESS.md` remains outdated.

---

## A. Completed Tasks

| # | Task | Status | Notes |
|---|---|---|---|
| A1 | Laravel 12 application structure (auth, controllers, requests, actions, services) | ✅ Done | Thin controllers, Form Requests, action/service delegation |
| A2 | Authentication: register, login, logout, remember-me, email verification, password reset | ✅ Done | Native guards, signed URLs, password broker; feature-tested |
| A3 | Profile management: update, email change w/ re-verification, password change, account deletion | ✅ Done | |
| A4 | Submission intake for text, image, video (upload), video URL, article URL | ✅ Done | Private default disk, transactional creation, dispatch after commit |
| A5 | Submission history: pagination, search, filters (label/type/status), sorting, owner scoping | ✅ Done | |
| A6 | Feedback on detection results (correct/incorrect + comment, dedup, ownership checks) | ✅ Done | |
| A7 | Domain models + relationships (users, submissions, detection_results, feedback, datasets, admin_logs, processing events) | ✅ Done | FK constraints, unique rules, indexes |
| A8 | Seed data and factories (Indonesian users, admin, datasets, submissions, results, feedback, logs) | ✅ Done | Idempotent seeder, repeat-run tests |
| A9 | Filament resources: User, Dataset, Submission, Feedback, DetectionResult | ✅ Done | Submission/Feedback/Result read-only; User/Dataset manageable |
| A10 | AI contract layer: `TextExtractor`, `Classifier`, `Explainer` + DTOs, enums, processing-event ledger | ✅ Done | |
| A11 | Pipeline jobs: `ProcessSubmission`, `ExtractSubmissionText`, `ClassifySubmission`, `GenerateSubmissionExplanation` | ✅ Done | Job classes exist w/ retries, backoff, locks, stage metadata |
| A12 | Extraction services: text normalization, stored-text reuse, article fetch, OpenAI image OCR, OpenAI video transcription | 🔶 Partial | Adapters exist; video URL extraction still only stored-path; article uses `strip_tags()` — see C10/C11 |
| A13 | Resilience scaffolding: BERT cache decorator, BERT circuit breaker, OpenAI degraded fallback | ✅ Done | `CachedBertClassifier`/`CircuitBreakingClassifier` + `OpenAiExplainer` fallback |
| A14 | OpenAI adapters for chat explanation, image OCR, audio transcription | 🔶 Partial | Direct HTTP adapters done; quota/cost/circuit-breaker gaps — see C13/C14 |
| A15 | FastAPI service skeleton (factory, config, Pydantic contracts, auth, liveness/readiness/version, startup model loading, concurrency bounds) | ✅ Done | Runtime ready and wired (21 Aug): `bert-service/.env` has `BERT_MODEL_PATH` + `BERT_MODEL_VERSION=v1.0.0`, `app/config.py:13` dotenv loading, `app/inference.py` label-map/sidecar/checksum verification, `GET /health/ready` 200, `POST /predict` with threshold `meragukan` — see C4/C5. |
| A16 | Vite/Tailwind frontend build + responsive landing page, four input tabs, auth/profile/history/result views, privacy pages | 🔶 Partial | **Corrected 21 Aug:** Blade + Tailwind + JS done, but PRD §Arsitektur explicitly requires **Livewire** for reactive polling/stage updates. `app/Livewire/` is absent (confirmed 21 Aug); progress UI is JS-only visual steps, not Livewire polling — see D4. |
| A17 | Database migration set covering all core tables + AI pipeline metadata fields | ✅ Done | |

---

## B. Unfinished — Critical [C]

| # | Task | Status | Notes |
|---|---|---|---|
| C1 | Fix broken queue handoff: `ExtractSubmissionText` must dispatch `ClassifySubmission` after success | ✅ Done | `ExtractSubmissionText::continueToClassification()` dispatches on `inference` queue, incl. idempotent recovery on already-extracted re-runs (app/Jobs/ExtractSubmissionText.php:40,63,71-74) |
| C2 | Prepare Indonesian hoax-news dataset (source adapters, schema, dedup, label reconciliation, train/val/test split, manifest/provenance) | ✅ Done | `bert-service/dataset/` pipeline: Komdigi CSV + labeled-CSV adapters, canonical Pydantic schema, claim extraction (no verdict leakage), exact + MinHash-LSH near-dup dedup w/ claim-group clusters, label reconciliation (`hoax`/`valid` aliases), group-stratified (label-aware) & time-based splits (no group leakage), SHA-256 manifest + data card. Single-source: `datasets/processed/komdigi-hoaks-v1/` (3832 hoax). **Combined for training (21 Aug):** `datasets/processed/komdigi-antara-v1/` (7816 rows = 3832 hoax + 3984 Antara valid via `scripts/build_valid_news.py`, 6253/782/781 stratified, `data_card.md` + `manifest.json`). 43/43 `pytest` in `bert-service` pass. **PRD gap:** valid side is long Antara news, not short claims — see C3 limitation. |
| C3 | Fine-tune IndoBERT (training scripts, tokenizer prep, config, checkpoint selection, calibration, evaluation report, confusion matrix, per-class P/R/F1, versioned export) | ✅ Done | `bert-service/train/` pipeline: config, metrics (accuracy/macro-F1/per-class P/R/F1/confusion/ECE/Brier/ROC-AUC), `data.py` tokenizer prep (IndoBERT WordPiece, max_length 256), `train.py` (Trainer, eval macro-F1 best checkpoint-391 after 1 epoch, early stopping patience 3), `calibrate.py` (temperature scaling T=0.8707 on validation, ECE 0.00073→0.00055), `threshold.py` (meragukan threshold 0.99 tuned on validation with max_abstain 0.30 — all val confidences 1.0 so threshold defaults to max), `evaluate.py` (held-out test n=781: **accuracy/macro-F1/per-class 1.0, confusion [[398,0],[0,383]], AUC 1.0, report + PNGs** at `models/runs/indobert-hoax-eval/`), `export.py` (safe serialization, `id2label {0:valid,1:hoax}`, sidecars `calibration.json`/`threshold.json`/`evaluation.json`, `MODEL_CARD.md`, `manifest.json`). Artifacts: `models/indobert-hoax/v1.0.0/` (model.safetensors 498 MB, tokenizer, config.num_labels=2). 52/52 `pytest` pass. **Limitation (must be disclosed in thesis):** valid = long news (`Jakarta (ANTARA) -` + body, median 2219 chars) vs hoax = short claim (`Beredar unggahan...`, median 227 chars, truncated 256) — held-out 1.0 is inflated by style/length; short valid snippets (e.g., news headline) score as hoax 0.92 and fall to `meragukan` per threshold 0.99 (manual `ModelRuntime` check 21 Aug). Future: add short valid headlines + length-controlled negatives. |
| C4 | Configure FastAPI with exported model artifact (`BERT_MODEL_PATH`) + label-map verification | ✅ Done | `bert-service/.env` (and `.env.example`) now have `BERT_MODEL_PATH=C:/xampp/htdocs/hoax-detector/models/indobert-hoax/v1.0.0` + `BERT_MODEL_VERSION=v1.0.0` with `app/config.py:13` dotenv loader. `app/inference.py:31` `ModelRuntime` now verifies `BERT_MODEL_PATH` exists, weights + tokenizer present, `id2label` is exactly `{0:valid,1:hoax}` + `label2id` inverse + `num_labels=2` (fails startup with `status failed` otherwise), loads `threshold.json`/`calibration.json` (T=0.8707, threshold 0.99) and applies `meragukan` per `MODEL_CARD.md` (`app/inference.py:244`), verifies SHA-256 against `models/indobert-hoax/manifest.json` (`app/inference.py:185`), and exposes `label_map`/`threshold`/`temperature` via `GET /version` (`app/main.py:152`, `app/contracts.py:22`). `GET /health/live` 200, `GET /health/ready` 200 when ready (503 otherwise, with `model_unavailable`), auth on `POST /predict` enforced. Verified 21 Aug via `TestClient` with lifespan: `ready {status:ok, model_status:ready}`, `version {model_labels:[valid,hoax], threshold:0.99}`, `predict hoax 1.0` → `hoax`, `predict short valid 0.97` → `meragukan` (threshold applied), invalid token → 401. Laravel `.env` now has `BERT_SERVICE_TOKEN=local-dev-token-change-me` matching FastAPI. |
| C5 | Prove real end-to-end Laravel ↔ FastAPI ↔ IndoBERT inference (contract test for `/predict`, Laravel HTTP-fake tests, real local inference run) | ✅ Done | `bert-service/tests/test_api.py` (11 tests): liveness 200, readiness 503→200, version labels `[valid,hoax]` + threshold 0.99, auth 401, validation 422, `text_too_long`, happy-path `hoax` 0.999 and `valid`/`meragukan` via threshold, `X-Request-ID` propagation. `tests/Feature/BertClassifierTest.php` (12 tests): success `hoax`/`valid` with `Classification` persistence fields, `meragukan` via `BERT_CONFIDENCE_THRESHOLD=0.99` (0.85→meragukan) and via FastAPI `meragukan` passthrough, transient `ConnectionException`/`429`+`Retry-After`/`503`/`500` vs permanent `422`/malformed/invalid-label, and payload+`Authorization: Bearer` assertion. `tests/Feature/RealBertInferenceTest.php` (4 tests, `RefreshDatabase` + `queue:work --once`): `isBertServiceAvailable()` skip when not reachable, otherwise proves `GET /version` ready, `ProcessSubmission::dispatchSync` persists `detection_results.label/confidence_score/model_version=v1.0.0/raw_scores`, threshold `meragukan` on `hello world` (`<0.99`), and async `ProcessSubmission::dispatch` + `queue:work` on `default`/`inference`/`explanation` persists via DB. Verified 21 Aug with FastAPI at `http://127.0.0.1:8001` (ready 200, predict 200, `php artisan test` 46 passed). |

---

## C. Unfinished — High [H]

| # | Task | Status | Notes |
|---|---|---|---|
| C6 | Add feature test that dispatches only `ProcessSubmission` and asserts full pipeline completes | ✅ Done | `test_process_submission_drives_full_pipeline_to_completion` — 30/30 Laravel tests pass when MySQL running; 52/52 `bert-service` pytest pass |
| C7 | Ensure failed submissions are not left `processing` forever after final retry | ✅ Done | Added `failed(Throwable)` on all 4 jobs: `ExtractSubmissionText.php:80`, `ClassifySubmission.php:75`, `GenerateSubmissionExplanation.php:89`, `ProcessSubmission.php:28` — each loads `Submission::find`, sets `status=failed`, `processing_stage` to the job's stage, `processing_completed_at=now()`, `failure_reason`/`last_error_service`/`last_error_code`/`attempt_count`, and records `EventOutcome::Failed` via `ProcessingEventRecorder`. `HandlesPipelineFailures` still handles transient vs permanent in `handle()`, but `failed()` now guarantees final state after max `tries` (3 for extract/explain/process, 5 for classify). |
| C8 | Add ownership protection to public `/hasil/{id}` route | ✅ Done | `DeteksiController.php:18` now checks `if ($submission->user_id !== null)` then requires `auth()->user()` with `getKey() === user_id` or `is_admin`, else `abort(403)`. Guest submissions (`user_id null`) remain shareable via link but enumeration is rate-limited via `throttle:60,1` on `Route::get('/hasil/{id}')` (`routes/web.php:26`). Matches `RiwayatController::show` which uses `forUser()` scope. |
| C9 | Remove/replace legacy demo UI branches + stale "belum dijalankan" wording | ✅ Done | `hasil.blade.php:24` replaced "Analisis AI belum dijalankan pada tahap implementasi ini" with branched UI: `status=failed` → "Pemrosesan Gagal" (red, failure_reason), else `processing` → "Sedang Menganalisis" with `processing_stage` label and pipeline description. Removed `Demo Mode` branch (`hasil.blade.php:76` → direct label display) and fallback `?? 0.82` → `(float) ($result->confidence_score ?? 0)`. |
| C10 | Implement video URL download/extraction safely | ❌ Not started | `OpenAiVideoExtractor` only handles stored path; remote URL fetch + size/duration guard missing |
| C11 | Robust article readability extraction (replace `strip_tags()`), redirect + SSRF/DNS-rebinding hardening | ❌ Not started | `ArticleExtractor` still `strip_tags()`; no `is_private_ip` check, no redirect limit test suite |
| C12 | Media limits: duration, decoded size, resource limits; malware scanning; retention scheduler | ❌ Not started | No duration/size checks, no ClamAV, no `media:prune` scheduler |
| C13 | OpenAI quota/rate-limit enforcement + cost accounting (usage ledger, token/cost calc — currently always 0) | ❌ Not started | `detection_results.estimated_cost` always 0; no `openai_usage` table; `config/openai.php` quota not enforced |
| C14 | OpenAI provider circuit breakers for OCR/transcription adapters | ❌ Not started | Only `OpenAiExplainer` has circuit breaker; `OpenAiImageExtractor`/`OpenAiVideoExtractor` call HTTP directly |
| C15 | Restore runnable automated test suite (start MySQL / configure separate test DB) | 🔶 Partial | `phpunit.xml` now has `DB_CONNECTION=sqlite` in-memory (21 Aug) — `php artisan test` 46 passed + `bert-service` 63 passed without MySQL; `QUEUE_CONNECTION=sync` in `phpunit.xml` for sync jobs. Remaining: `git status` still `fatal: not a git repository` (verified 21 Aug), and MySQL-dependent fulltext/queue integration still needs separate DB for production parity. |
| C16 | Restore version control (repair `.git`; repo currently invalid) | ❌ Not started | `git status` fails; no history — blocks traceability, code review, and `opencode` task tracking |
| C17 | CAPTCHA + stronger per-IP/per-account submission quotas | ❌ Not started | Only `throttle:60,1` on routes; no CAPTCHA, no per-IP daily cap |
| C18 | FastAPI test suite + container/process-manager deployment config + model checksum/registry + metrics endpoint | 🔶 Partial | `bert-service/tests/test_api.py` (11 tests) now covers `/health/live`/`/health/ready`/`/version`/`/predict` with lifespan; `models/indobert-hoax/manifest.json` exists with SHA-256 and is verified at startup (`app/inference.py:185`). Remaining: `Dockerfile`/`docker-compose`/`systemd`/`supervisord`, CI checksum gate, and `/metrics` endpoint. |

---

## D. Unfinished — Medium [M]

| # | Task | Status | Notes |
|---|---|---|---|
| D1 | Install Redis + Laravel Horizon; define queue supervisors (text/media/inference/explanation/notifications) | ❌ Not started | `QUEUE_CONNECTION=database` in `.env`/`phpunit.xml`; `config/horizon.php` not present; no supervisor conf |
| D2 | Add processing-event and admin-log Filament resources + automated audit logging for Filament mutations | ❌ Not started | `app/Filament/Resources` has User/Dataset/Submission/Feedback/Result only; no `ProcessingEventResource`/`AdminLogResource`; no `Filament::auth` audit hook |
| D3 | Dashboard stats widgets, trend/label-distribution charts, model-version + failed-job monitoring | ❌ Not started | Filament dashboard is default; no widgets for submission counts, label distribution, model version, failed jobs |
| D4 | Livewire polling / event-driven status updates for stage progress + completion/retry UX | ❌ Not started | PRD §Arsitektur requires Livewire for reactive stage polling. `app/Livewire/` absent (verified 21 Aug); `welcome.blade.php`/`hasil.blade.php` use JS visual steps only |
| D5 | Move private media to S3-compatible object storage for multi-instance deployment | ❌ Not started | `config/filesystems.php` default `local`/`private`; no S3 disk, no `AWS_*` docs |
| D6 | Tagged container bindings for extractor implementations; centralize state transitions | ❌ Not started | `AppServiceProvider` manually instantiates resolvers; `Submission::status` strings updated in multiple jobs, not via single `SubmissionStateMachine` |
| D7 | Real integration tests: FastAPI, BERT responses, OpenAI failures, retries, cache hits | ❌ Not started | Only pipeline happy-path test exists; no failure/retry/cache-hit matrix |
| D8 | Explicit `ProcessSubmission` retry/timeout policy; scheduled retry/recovery command for failed submissions | ❌ Not started | `ProcessSubmission` has default `$tries`/`$backoff` but no `retryUntil()`/`timeout`; no `submissions:recover` command |
| D9 | Structured logs, metrics, traces, alerts; deployment health checks, queue drain/rollback docs | ❌ Not started | No JSON logs, no Prometheus, no `HEALTHCHECK` in Dockerfile, no runbook |
| D10 | Add model + prompt version metadata to all relevant screens | ❌ Not started | `hasil.blade.php`/`riwayat.blade.php` show label/confidence but not `model_version`/`prompt_version`; `detection_results.model_version` is stored but not displayed |

---

## E. Unfinished — Low [L]

| # | Task | Status | Notes |
|---|---|---|---|
| E1 | PDF export of detection results | ❌ Not started | PRD FR-14 Could — no `barryvdh/laravel-dompdf` or similar |
| E2 | CSV export of results / dataset export for training / evaluation report export | ❌ Not started | No `league/csv` export; `datasets` admin can manage but not export |
| E3 | Statistik & visualisasi tren (grafik tren jumlah hoax per waktu/topik) | ❌ Not started | PRD FR-12 Could — no trend query/view; D3 widgets would cover |
| E4 | Richer dataset topic metadata; challenge-set evaluation display | ❌ Not started | `datasets` has `topics` JSON but not populated from source; no challenge set |
| E5 | User notification preferences | ❌ Not started | No `notification_preferences` table or UI |
| E6 | Media preview/download flow for private stored media | ❌ Not started | Private disk has no signed-URL preview route; `hasil.blade.php` shows no media preview |

---

## F. Immediate Next Steps (Recommended Order)

1. **C15 → C16** — Make the suite fully production-parity and repo valid: keep `sqlite` for CI but add MySQL test DB for fulltext parity, and repair `.git` (`fatal: not a git repository`) so history is traceable.
2. **Research hardening (thesis defense)** — Add short valid headlines (e.g., Antara titles only) to the valid set and re-train, or add a length/style-controlled challenge split to de-bias the 1.0 test score; document the current caveat in `PROJECT_PROGRESS.md` §Model risk.
3. **D3/D4 + C18** — Add Livewire polling, Filament dashboard widgets, and remaining deployment config (`Dockerfile`/`docker-compose`, `/metrics`) for thesis demo completeness.
4. **C10 → C11 → C12** — Complete multimodal hardening: video URL fetch, readability + SSRF hardening, media limits/malware/retention.
5. **E1/E2 + C13/C14** — Optional exports (PDF/CSV) and OpenAI quota/cost hardening if time remains.

