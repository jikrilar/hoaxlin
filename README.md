# hoaxlin.id — Sistem Deteksi Hoax BERT

Platform web untuk memeriksa indikasi hoax pada berita berbahasa Indonesia. **Model BERT** yang di-*fine-tune* (IndoBERT) menentukan label `valid` / `hoax` / `meragukan` (threshold 0.99); **OpenAI** hanya untuk OCR gambar, transkripsi video, dan penjelasan naratif.

> **Status:** Pipeline `text` end-to-end telah proven (C5): `ProcessSubmission → Extract → Classify (BERT 1.0)` → `DetectionResult` via `queue:work`. Lihat `CHECKLIST_TASK.md` untuk progress 95%.

---

## Daftar Isi
- [Stack](#stack)
- [Prasyarat](#prasyarat)
- [Quick Start — 5 Menit](#quick-start--5-menit)
- [Setup Developer Lengkap](#setup-developer-lengkap)
- [Layanan BERT (FastAPI)](#layanan-bert-fastapi)
- [Queue & Pipeline](#queue--pipeline)
- [Testing](#testing)
- [Panduan Administrator](#panduan-administrator)
- [Struktur Proyek](#struktur-proyek)
- [Troubleshooting](#troubleshooting)
- [Deployment Singkat](#deployment-singkat)

---

## Stack

| Lapisan | Teknologi |
|---|---|
| **Backend** | Laravel 12, PHP 8.2+, MySQL 8+, Laravel Queue (database) |
| **Frontend** | Livewire 4.3, Filament 5, Tailwind 4, Vite |
| **AI** | FastAPI + Transformers 5.14 + PyTorch 2.13 (IndoBERT `indobenchmark/indobert-base-p1`) |
| **Layanan Pendukung** | OpenAI API (Vision `gpt-4o-mini`, Whisper `whisper-1`, Chat `gpt-4o-mini`) |

---

## Prasyarat

- **PHP** 8.2+ + Composer 2.x + Node 18+ + npm
- **MySQL** 8+ (atau gunakan SQLite untuk test)
- **Python** 3.10+ (disarankan 3.12, terverifikasi di 3.14) + pip
- **Git**, **XAMPP** (atau MySQL standalone) di Windows
- **FFprobe** opsional (untuk cek durasi video, `StoreSubmissionRequest` akan skip jika tidak ada)
- **ClamAV** opsional (`clamdscan` untuk malware scan)

Cek versi:

```powershell
php -v; composer -v; node -v; npm -v; python --version; mysql --version
```

---

## Quick Start — 5 Menit

```powershell
# 1. Clone & install
git clone <repo> hoax-detector; cd hoax-detector
composer install; npm install

# 2. Env & DB
Copy-Item .env.example .env
# → isi DB_DATABASE=hoax_detector, OPENAI_API_KEY, BERT_SERVICE_TOKEN (lihat di bawah)
php artisan key:generate
# Buat DB di MySQL: CREATE DATABASE hoax_detector CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
php artisan migrate --seed
php artisan storage:link

# 3. Admin
php artisan make:filament-user  # isi email & password, lalu di DB: UPDATE users SET is_admin=1 WHERE email='...';

# 4. BERT (wajib untuk klasifikasi — bukan opsional)
cd bert-service
py -m venv .venv; .\.venv\Scripts\Activate.ps1
pip install -r requirements.txt
Copy-Item .env.example .env
# Pastikan .env berisi:
#   BERT_MODEL_PATH=C:/xampp/htdocs/hoax-detector/models/indobert-hoax/v1.0.0
#   BERT_MODEL_VERSION=v1.0.0
#   BERT_SERVICE_TOKEN=local-dev-token-change-me  (samakan dengan Laravel .env)
cd ..

# 5. Jalankan SEMUA layanan (4 proses sekaligus)
composer run dev
# → http://localhost:8000 (Laravel)
# → http://127.0.0.1:8001/health/live (BERT liveness) — harus 200

# 6. Cek di browser: buka http://localhost:8000, kirim teks "Beredar unggahan..." → /hasil/{id} akan polling 2s dan menampilkan label
```

**Kenapa `composer run dev` wajib (bukan `php artisan serve` saja)?** Karena ekstraksi & klasifikasi berjalan **async via queue** di `extract-text,extract-media,inference,explanation`. `php artisan serve` saja tidak menjalankan `queue:listen`, sehingga submission akan **stuck 60% (classifying)** selamanya. `composer run dev` menjalankan `serve + queue:listen --queue=extract-text,extract-media,inference,explanation,default + pail + vite` sekaligus.

---

## Setup Developer Lengkap

### 1. Konfigurasi `.env` (Laravel)

Salin dan isi:

```powershell
Copy-Item .env.example .env
php artisan key:generate
```

Wajib isi di `.env`:

```ini
DB_DATABASE=hoax_detector
DB_USERNAME=root
DB_PASSWORD=

# BERT — harus sama dengan bert-service/.env
BERT_SERVICE_URL=http://127.0.0.1:8001
BERT_SERVICE_TOKEN=local-dev-token-change-me
BERT_SERVICE_TIMEOUT=30
BERT_SERVICE_CONNECT_TIMEOUT=3
BERT_CONFIDENCE_THRESHOLD=0.99   # samakan dengan threshold.json (0.99)

# OpenAI — untuk OCR / transkripsi / penjelasan (jika kosong, pipeline tetap jalan untuk teks, tapi gambar/video & penjelasan akan degraded)
OPENAI_API_KEY=sk-proj-...
OPENAI_CHAT_MODEL=gpt-4o-mini
OPENAI_VISION_MODEL=gpt-4o-mini
OPENAI_TRANSCRIBE_MODEL=whisper-1

# Queue & cache (dev)
QUEUE_CONNECTION=database
CACHE_STORE=database
```

> **Token:** `BERT_SERVICE_TOKEN` **harus identik** di `.env` dan `bert-service/.env`. Jika kosong atau beda, klasifikasi gagal `401` dan submission akan `failed` (bukan stuck) berkat `failed()` hook C7.

### 2. Database

```powershell
# Buat DB
mysql -u root -e "CREATE DATABASE hoax_detector CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

php artisan migrate
php artisan db:seed              # data demo: user, admin, dataset
php artisan storage:link
```

Untuk test tanpa MySQL, `phpunit.xml` sudah `DB_CONNECTION=sqlite` `:memory:` → `php artisan test` langsung jalan (46 tests).

### 3. Filament Admin

```powershell
php artisan make:filament-user
# → email: admin@hoaxlin.id, password: ****
# lalu:
# mysql -u root -e "UPDATE hoax_detector.users SET is_admin=1 WHERE email='admin@hoaxlin.id';"
```

Buka `http://localhost:8000/admin` → login.

### 4. Frontend

```powershell
npm install
npm run dev     # dev dengan HMR
# atau
npm run build   # production build ke public/build
```

---

## Layanan BERT (FastAPI)

Model IndoBERT v1.0.0 sudah ada di `models/indobert-hoax/v1.0.0/` (498 MB, `model.safetensors` + `tokenizer.json`, `config.json` `id2label {0:valid,1:hoax}`, `threshold.json` 0.99, `calibration.json` T=0.87). Jika belum ada, build via pipeline C3:

```powershell
cd bert-service
# ... venv & pip install seperti di atas ...
# Dataset sudah ada: datasets/processed/komdigi-antara-v1/ (7816 rows)
# Jika ingin retrain:
.\.venv\Scripts\python.exe -m train.pipeline
```

**Start manual (tanpa `composer run dev`):**

```powershell
cd bert-service
# PowerShell: load .env ke process env
Get-Content .env | ForEach-Object {
    if ($_ -match '^\s*([^#][^=]*)=(.*)$') {
        [Environment]::SetEnvironmentVariable($matches[1].Trim(), $matches[2].Trim().Trim('"'), 'Process')
    }
}
.\.venv\Scripts\python.exe -m uvicorn app.main:app --host 127.0.0.1 --port 8001 --log-level info
# Tunggu "Application startup complete." + "Uvicorn running on http://127.0.0.1:8001"
```

**Health check (wajib 200 sebelum kirim submission):**

```powershell
Invoke-RestMethod http://127.0.0.1:8001/health/live              # -> {"status":"ok"}
Invoke-RestMethod http://127.0.0.1:8001/health/ready             # -> 200 {"status":"ok","model_status":"ready"} atau 503 jika BERT_MODEL_PATH salah
Invoke-RestMethod http://127.0.0.1:8001/version                  # -> labels [valid,hoax], threshold 0.99
# Predict (butuh Bearer token):
$h = @{Authorization="Bearer local-dev-token-change-me"}
Invoke-RestMethod -Method Post -Uri http://127.0.0.1:8001/predict -Headers $h -Body '{"text":"Beredar unggahan..."}' -ContentType application/json
```

Detail lengkap: `bert-service/README.md` (Model Serving, Dataset, Fine-tuning).

---

## Queue & Pipeline

**Alur:** `ProcessSubmission` → `ExtractSubmissionText` (`extract-text` / `extract-media`) → `ClassifySubmission` (`inference`) → `GenerateSubmissionExplanation` (`explanation`) → `completed`. Setiap job punya `tries`/`backoff`/`timeout` dan `failed()` hook (C7) yang menandai `submissions.status=failed` + `processing_events` agar tidak stuck `processing` selamanya.

**Queue yang harus didengar (penting!):**

```ini
# composer.json dev sudah benar:
# "php artisan queue:listen --queue=extract-text,extract-media,inference,explanation,default --tries=3"
```

Manual per-queue:

```powershell
php artisan queue:work --queue=extract-text,extract-media,inference,explanation,default --stop-when-empty --tries=3
# atau per-queue untuk debug:
php artisan queue:work --queue=inference --stop-when-empty -v
```

**Cek antrean:**

```powershell
php artisan tinker --execute="echo DB::table('jobs')->count().' jobs pending, '.DB::table('failed_jobs')->count().' failed';"
# atau pakai helper:
php check_jobs.php   # (jika ada)
```

**Hapus stuck jobs setelah perbaiki token/BERT:**

```powershell
php artisan tinker --execute="DB::table('jobs')->delete(); DB::table('failed_jobs')->delete();"
# lalu kirim submission baru dan jalankan queue:work lagi
```

**Progress bar:** `ProcessingStage` enum (`queued 0% → extracting 25% → classifying 60% → explaining 85% → done 100%`). Halaman `/hasil/{id}` polling `GET /hasil/{id}/status` via Livewire `wire:poll.2s.visible` + JS fallback, auto-reload saat `completed`/`failed`.

---

## Testing

### Laravel (46 tests, SQLite in-memory, no MySQL needed)

```powershell
php artisan test
php artisan test --filter=BertClassifierTest
php artisan test --filter=RealBertInferenceTest  # butuh BERT hidup, else skipped
php artisan test --filter=SubmissionProgressTest
```

`phpunit.xml` sudah `DB_CONNECTION=sqlite` `:memory:`, `QUEUE_CONNECTION=sync`, `CACHE_STORE=array`.

### BERT Service (64 tests)

```powershell
cd bert-service
.\.venv\Scripts\python.exe -m pytest -q          # 64 passed
.\.venv\Scripts\python.exe -m pytest tests/test_api.py -q  # 12 contract tests
```

### Verifikasi manual end-to-end

```powershell
# 1. Pastikan BERT ready
Invoke-RestMethod http://127.0.0.1:8001/health/ready
# 2. Buat submission via tinker
php artisan tinker --execute="\$s=App\Models\Submission::create(['input_type'=>'text','raw_input'=>str_repeat('Beredar unggahan... ',3),'status'=>'pending']); App\Jobs\ProcessSubmission::dispatch(\$s); echo 'id='.\$s->id;"
# 3. Jalankan queue
php artisan queue:work --queue=extract-text,extract-media,inference,explanation,default --stop-when-empty
# 4. Cek hasil
php artisan tinker --execute="\$s=App\Models\Submission::latest()->first(); echo \$s->status.' '.\$s->processing_stage.' label='.\$s->detectionResult?->label;"
```

---

## Panduan Administrator

### Menjalankan Lokal (untuk demo / sidang)

1. **Siapkan dua terminal:**
   - Terminal 1: `composer run dev` (menjalankan `serve` + `queue:listen` semua queue + `pail` + `vite`)
   - Terminal 2: `cd bert-service` → `uvicorn` (jika tidak pakai `composer run dev`, jalankan manual seperti di atas)

2. **Buat admin & atur:** `http://localhost:8000/admin` → cek **Users**, **Submissions**, **Detection Results**, **Feedback**.

3. **Kirim uji:** buka `/` → tab **Teks** → tempel `Beredar unggahan di media sosial yang mengklaim bansos Rp 50 juta untuk semua warga` → **Cek Sekarang** → akan redirect ke `/hasil/{id}` dengan progress 0% → 100% (2s polling). Jika `failed`, cek `storage/logs/laravel.log` dan `failed_jobs`.

### Kelola Pengguna & Dataset

- **Jadikan admin:** `UPDATE users SET is_admin=1 WHERE email='...';`
- **Dataset:** `/admin` → **Datasets** → tambah data valid/hoax untuk retrain (format `text`, `label`).
- **Submission:** read-only di Filament untuk audit; ubah status manual via `php artisan tinker` jika perlu.

### Monitoring & Pemeliharaan

```powershell
# Lihat job tertunda / gagal
php artisan queue:monitor
php artisan queue:failed              # list
php artisan queue:retry all           # retry
php artisan queue:flush               # hapus failed

# Media retention (C12) — hapus file >30 hari
php artisan media:prune --dry-run
php artisan media:prune --days=30

# Cache & view
php artisan config:clear; php artisan cache:clear; php artisan view:clear

# Log
Get-Content storage\logs\laravel.log -Tail 50 -Wait  # atau php artisan pail
Get-Content bert-service\uvicorn.log -Tail 50
```

**Jika klasifikasi stuck 60% / `failed` 401:**
- Cek `BERT_SERVICE_TOKEN` sama di `.env` dan `bert-service/.env` → `php artisan config:clear` → restart BERT.
- Cek BERT hidup: `Invoke-RestMethod http://127.0.0.1:8001/health/ready`.
- Cek queue: `DB::table('jobs')->count()` — jika >0, jalankan `queue:work` dengan semua queue (bukan hanya `default`).

---

## Struktur Proyek

```
hoax-detector/
├── app/
│   ├── Actions/Submissions/CreateSubmission.php
│   ├── Console/Commands/PruneOldMedia.php  # media:prune (C12)
│   ├── Enums/ProcessingStage.php            # progressPercentage() untuk Livewire bar
│   ├── Http/Controllers/{SubmissionController,DeteksiController,RiwayatController}
│   ├── Jobs/{ProcessSubmission,ExtractSubmissionText,ClassifySubmission,GenerateSubmissionExplanation}
│   ├── Livewire/SubmissionProgress.php      # wire:poll.2s.visible (D4)
│   └── Services/{Bert/*,Extraction/*,OpenAI/*,Media/MalwareScanner.php}
├── bert-service/
│   ├── app/{main.py,config.py,inference.py} # ModelRuntime + label-map + threshold
│   ├── dataset/ + train/                    # C2 + C3 pipelines
│   ├── tests/test_api.py                   # 12 contract tests
│   └── Dockerfile                          # C18
├── datasets/processed/komdigi-antara-v1/    # 7816 rows, manifest.json
├── models/indobert-hoax/v1.0.0/             # 498 MB, config.json, threshold.json
├── resources/views/{hasil.blade.php,livewire/submission-progress.blade.php}
├── routes/{web.php,console.php}            # console.php: media:prune schedule
└── CHECKLIST_TASK.md + PROJECT_PROGRESS.md  # progress 95%
```

---

## Troubleshooting

| Gejala | Penyebab Umum | Solusi |
|---|---|---|
| `hasil` stuck `pending` 0% | `queue:listen` hanya `default` | Gunakan `composer run dev` atau `queue:work --queue=extract-text,extract-media,inference,explanation,default` |
| `failed` `401 Permintaan ke layanan BERT ditolak` | Token beda | Samakan `BERT_SERVICE_TOKEN` di `.env` & `bert-service/.env`, lalu `config:clear` + restart BERT |
| `503 Model is not ready` / `CircuitBreaker open` | BERT mati atau `BERT_MODEL_PATH` salah | `Test-NetConnection 127.0.0.1 -Port 8001`, cek `uvicorn.log`, `Cache::get('breaker:bert:failures')` |
| `SQLSTATE [2002]` di test | MySQL mati | Test sudah sqlite `:memory:`, cukup `php artisan test`; untuk manual, `mysql -u root -e "CREATE DATABASE hoax_detector;"` |
| `git status fatal` | `.git` kosong | Sudah diperbaiki `git init` — `git log` harus ada 3 commit |
| Gambar/video `failed` | `OPENAI_API_KEY` kosong atau kuota habis | Isi `OPENAI_API_KEY` di `.env`; cek `openai:rate` di cache; lihat `detection_results.estimated_cost_usd` |

---

## Deployment Singkat

- **Env:** `APP_ENV=production`, `APP_DEBUG=false`, `QUEUE_CONNECTION=redis` + `CACHE_STORE=redis` + `config/horizon.php`, `FILESYSTEM_DISK=s3` untuk media.
- **Build:** `composer install --no-dev --optimize-autoloader`, `npm run build`, `php artisan migrate --force`, `php artisan storage:link`.
- **Proses:** `php artisan horizon` (supervisor), `uvicorn app.main:app --host 0.0.0.0 --port 8001` via systemd/supervisord, `caddy`/`nginx` reverse proxy, `HEALTHCHECK` di `bert-service/Dockerfile` sudah ada.
- **Backup:** `mysqldump hoax_detector`, `storage/app/private/submissions/*`.

---

## Referensi

- PRD: `PRD-Sistem-Deteksi-Hoax-BERT.md`
- Progress: `CHECKLIST_TASK.md` (95%) + `PROJECT_PROGRESS.md` (perlu re-audit)
- BERT: `bert-service/README.md`
- API: `POST /predict` → `bert-service/app/contracts.py`, auth `Bearer`, `X-Request-ID`

