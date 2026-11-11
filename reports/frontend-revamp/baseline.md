# Baseline Test & Build — Frontend Revamp (T0.1)

Tanggal pemeriksaan: 2026-11-11
Branch: `docs/frontend-revamp-s0`
Base commit: `07d9f9f`

## Ringkasan Hasil Pemeriksaan (`AGENTS.md` §6)

| Area | Perintah / Lingkungan | Hasil Aktual | Gagal | Skip / Deselected | Warning |
| --- | --- | --- | --- | --- | --- |
| Docker target `test` build | `docker build --target test -t hoaxlin-app:test .` | PASS | 0 | 0 | 0 |
| Laravel PHPUnit suite | `docker run --rm --network none -e APP_KEY=base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA= hoaxlin-app:test` | **834 passed** (5.976 assertions, 46.68s) | 0 | **4 skipped** | **25 warnings** |
| Laravel Pint | `docker run --rm --network none hoaxlin-app:test vendor/bin/pint --test` | **PASS** (308 files) | 0 | 0 | 0 |
| Blade view cache/clear | `php artisan view:cache && php artisan view:clear` (di `hoaxlin-app:test`) | **PASS** | 0 | 0 | 0 |
| BERT service unit tests | `pytest -m 'not release'` (offline di `hoaxlin-c06-final:bert-test`, repo root di-mount ke `/app`) | **161 passed** (28.55s) | 0 | **22 deselected** (`release` marker) | **1 warning** |
| RAG service unit tests | `pytest tests` (offline di `hoaxlin-c06-final:rag-test`) | **44 passed** (1.69s) | 0 | 0 | **1 warning** |
| RAG KB validator | `python scripts/validate_rag_knowledge_base.py` | **PASS** (`Knowledge base valid: 61 documents`) | 0 | 0 | 0 |
| RAG tooling tests (`tests/rag`) | `python -m unittest discover -s tests/rag -p 'test_*.py'` | **26 passed** (0.066s) | 0 | 0 | 0 |
| Frontend unit tests | `node --test tests/Frontend/*.test.mjs` (Node v25.9.0 di host) | **69 passed** (1.27s) | 0 | 0 | 0 |
| Frontend Vite build (`npm run build`) | `docker build --target frontend-build` + `npm run build` (Node 22.20.0 + `vendor/` dari stage `composer-production`) | **PASS** (22 modules transformed, 3.15s) | 0 | 0 | 0 |
| Docker Compose config & setup script | `docker compose --env-file /dev/null config --quiet` (dummy env), `bash -n scripts/docker-setup.sh`, `bash scripts/docker-setup.sh --help` | **PASS** | 0 | 0 | 0 |

## Rincian Test Gagal Sebelum Perubahan

- **0 test gagal** pada seluruh suite (Laravel, Pint, BERT, RAG, Frontend, dan build Vite).

## Rincian Skip / Deselected Sebelum Perubahan

1. **Laravel — `Tests\Feature\RealBertInferenceTest` (4 skipped)**:
   - `real bert service is reachable and ready`
   - `real classify persists label confidence and model version`
   - `real classify applies meragukan threshold on low confidence`
   - `real pipeline persists via queue worker`
   - *Alasan*: kontainer test dijalankan terisolasi (`--network none`) tanpa service runtime `bert` aktif (`BERT service not reachable`).
2. **Python `bert-service` — marker `release` (22 deselected)**:
   - Dipilih keluar dengan `-m 'not release'` sesuai `AGENTS.md` §6 karena menguji artefak rilis privat `indobert-hoax v1.0.0`.

## Rincian Warning Sebelum Perubahan

1. **Laravel — `Tests\Unit\SubmissionProgressPresentationTest` (25 warnings)**:
   - 25 data set/kasus memunculkan `DOMDocument::loadHTML(): Tag section invalid in Entity, line: 4` karena parser HTML4 bawaan `DOMDocument::loadHTML` pada PHP `ext-dom` memperingatkan elemen HTML5 `<section>`. Test tetap lulus assertion.
2. **Python `bert-service` & `rag-service` (masing-masing 1 warning)**:
   - `StarletteDeprecationWarning: Using httpx with starlette.testclient is deprecated; install httpx2 instead` dari `fastapi/testclient.py`.

## Catatan Lingkungan Host

- Host tidak memiliki direktori `vendor/` Composer maupun `.venv` lokal di `bert-service/` dan `rag-service/`.
- Menjalankan `npm run build` langsung di host tanpa `vendor/` gagal meresolusi `vendor/filament/filament/resources/css/theme.css` dari `resources/css/filament/admin/theme.css` (sesuai catatan `README.md` §Testing dan CI). Build Vite diverifikasi melalui stage Docker `frontend-build` (`Dockerfile`) yang menyertakan `vendor/` dari `composer-production`.
- `bert-service/tests/test_komdigi_audit.py` memuat `/app/reports/ai-quality/20261029-q008/komdigi-latest/audit.py` relatif terhadap root repo (`/app`), sehingga kontainer test Python memerlukan mount root repo (`-v "$PWD:/app:ro" -w /app/bert-service`).
