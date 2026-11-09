# Hoaxlin — pemeriksaan informasi berbasis IndoBERT dan RAG

Hoaxlin membantu pengguna membaca **prediksi model dan referensi terkait**, bukan membuktikan kebenaran berita secara otomatis. Laravel mengelola aplikasi; FastAPI IndoBERT menjadi classifier utama; layanan RAG mencari rujukan pada knowledge base lokal; OpenAI mendukung ekstraksi dan penjelasan.

**Hasil merupakan prediksi model, bukan verifikasi kebenaran yang definitif.** Skor keyakinan model bukan persentase kebenaran berita. Similarity retrieval bukan confidence classifier.

## Daftar isi

- [Fitur](#fitur)
- [Tech stack](#tech-stack)
- [Arsitektur sistem](#arsitektur-sistem)
- [Requirements](#requirements)
- [Instalasi Docker — utama](#instalasi-docker)
- [Konfigurasi environment](#konfigurasi-environment)
- [Development manual Linux dan Windows](#development-manual)
- [Menjalankan dan mengoperasikan aplikasi](#operasi-aplikasi)
- [Testing dan CI](#testing-dan-ci)
- [IndoBERT v1.0.0](#indobert-v100)
- [Incremental Fine-Tuning & Model Versioning](#incremental-fine-tuning-dan-model-versioning)
- [RAG knowledge base dan retrieval](#rag-knowledge-base-dan-retrieval)
- [Troubleshooting](#troubleshooting)
- [Security dan privacy](#security-dan-privacy)
- [Status proyek saat ini](#status-proyek-saat-ini)

## Fitur

- Tamu: pemeriksaan teks 50–50.000 karakter; akses hasil memakai capability dalam session, bukan ID publik.
- Pengguna login: teks, gambar, video unggahan, URL langsung media, dan URL artikel sesuai validasi backend.
- Pipeline asynchronous dengan progress, hasil, provenance terjemahan, rujukan, dan penjelasan bila tersedia.
- Riwayat, ekspor CSV riwayat, dan feedback memerlukan akun **terverifikasi**. Hasil/PDF/media tetap memerlukan hak akses submission.
- Panel admin Filament untuk akun, katalog dataset, versi model IndoBERT (incremental fine-tuning, perbandingan metrik vs baseline, dan aktivasi *hot-swap*), monitoring, dan audit. Katalog dataset bukan corpus training otomatis.
- PDF hasil, statistik dengan konteks versi, CAPTCHA one-time, kuota, rate limiting, dan recovery submission.

URL media harus menunjuk langsung ke file, bukan halaman YouTube/TikTok/Instagram/Facebook atau player HTML/JSON. Video unggahan mendukung MP4/MPEG/WEBM; direct media mendukung FLAC, MP3, MP4, MPEG/MPGA, M4A, OGG, WAV, WEBM dengan extension, MIME, dan signature yang sesuai. Batas default media transkripsi 24 MiB dan 300 detik; gambar memiliki kontrak tersendiri di `StoreSubmissionRequest`. Jangan menyamakan batas request web dengan batas teks inference BERT.

## Tech stack

| Area | Implementasi saat ini |
| --- | --- |
| Web | Laravel 12 (`v12.64.0` terkunci), PHP `^8.2`; Docker PHP 8.4.11 |
| UI | Blade, JavaScript biasa, Tailwind CSS 4, Vite 6, Livewire 4.3, Filament 5.7 |
| Build | Node 22.20.0 di Docker, npm lockfile, Composer lockfile |
| Data/queue | MySQL 8.0.44, Redis 7.4; worker dan scheduler Laravel |
| Classifier | FastAPI, PyTorch CPU, Transformers; frozen `indobert-hoax v1.0.0` |
| Retrieval | FastAPI, sentence-transformers multilingual, NumPy cosine index |
| Pendukung | OpenAI OCR/transkripsi/EN→ID/explanation; DomPDF dan exporter CSV |
| Test | PHPUnit 11 + SQLite in-memory, pytest Python, Node built-in test runner |

Versi tepat ada di lockfile dan requirements tiap service. Jangan menjalankan update dependency sebagai langkah setup.

## Arsitektur sistem

```text
Browser → Laravel (app) → MySQL / Redis
                         ↓ queue worker
Input → Extraction → Translation bila perlu → IndoBERT → Retrieval RAG → Explanation → Hasil
                                               ↑             ↑             ↑
                                         release privat   KB lokal       OpenAI
```

Orkestrasi aktual: `ProcessSubmission` → `ExtractSubmissionText` → `TranslateSubmissionText` → `ClassifySubmission` → `RetrieveSubmissionEvidence` → `GenerateSubmissionExplanation`. Stage progress: `queued → extracting → translating → classifying → retrieving → explaining → done`.

Teks Indonesia tidak memanggil provider terjemahan; Inggris diterjemahkan ke Indonesia. RAG tidak mengubah label/confidence classifier. RAG gagal/evidence kosong dan explanation `unavailable` tidak otomatis membatalkan klasifikasi yang selesai. Hasil final hanya ditampilkan saat submission `completed`, bukan pending/processing/failed.

Compose menjalankan `app`, `queue`, `scheduler`, `mysql`, `redis`, `bert`, dan `rag`. Hanya `app` memublikasikan port host, default `127.0.0.1:8000`. BERT/RAG/DB/Redis tetap internal; komunikasi Laravel memakai `http://bert:8001` dan `http://rag:8002`. Source dibundel ke image, **bukan bind mount**.

## Requirements

### Untuk Docker

- Git, Docker Engine/Desktop dengan Linux containers, Compose modern dan BuildKit/buildx.
- Koneksi awal untuk image/dependency dan embedding RAG; GPU tidak diperlukan.
- Akses resmi ke image privat `hoaxlin-bert:v1.0.0` atau packaged release lengkap di `artifacts/indobert-hoax/v1.0.0/`. **Clone publik saja tidak cukup untuk inference BERT.** Jangan mengunduh model pengganti atau membuat bobot dummy.
- Ruang disk untuk beberapa image/model dan memori untuk build + dua inference service. Siapkan sekitar 8 GiB RAM atau lebih sebagai titik awal praktis, bukan minimum yang dijamin; sesuaikan resource Docker Desktop dan pantau penggunaan.
- Bash + coreutils untuk bootstrap Linux; PowerShell untuk Windows. Host tidak memerlukan PHP/Node/Python bila seluruhnya memakai Docker.

### Untuk development manual

PHP 8.2+ (8.4 mendekati Docker), Composer 2, Node 22/npm, MySQL, serta Python 3.12 yang tersedia terpisah untuk dua virtual environment. Redis dibutuhkan bila driver diubah ke Redis; template manual memakai database untuk queue/cache/session. Periksa `composer check-platform-reqs`: PHP perlu extension Laravel dan paket terkunci, termasuk PDO MySQL, SQLite untuk test, mbstring, XML/DOM, curl, fileinfo, intl, GD, zip, dan Redis jika dipakai. `pcntl` berguna pada Linux; Windows tidak memilikinya.

Arch/CachyOS bersifat rolling release: versi PHP/Python terbaru belum tentu sama dengan runtime terkunci. Jika wheel Python atau extension PHP tidak kompatibel, **gunakan Docker**, bukan mengubah requirements/lockfile.

<a id="instalasi-docker"></a>
## Instalasi Docker — metode utama

### 1. Siapkan Docker

Pada CachyOS/Arch Linux, contoh perintah berikut dapat dijalankan dari Bash maupun Fish:

```sh
sudo pacman -Syu --needed git docker docker-compose docker-buildx
sudo systemctl enable --now docker.service
```

Pastikan akun memiliki akses Docker sesuai kebijakan host. Grup `docker` setara akses root; jangan mengubah permission socket menjadi world-writable. Lihat [ArchWiki Docker](https://wiki.archlinux.org/title/Docker) untuk pilihan akses/rootless. Jangan mengganti Docker context mesin yang sudah dipakai tanpa memahami dampaknya.

Windows: gunakan Docker Desktop dengan backend WSL2/Linux containers, lalu buka PowerShell di root repo. Linux/Fish: jalankan script Bash dengan `bash`, **jangan `source` script Bash ke Fish**.

```sh
docker version
docker compose version
docker buildx version
git clone https://github.com/jikrilar/hoaxlin.git
cd hoaxlin
```

Jika checkout sudah ada, gunakan checkout itu; tidak perlu clone ulang atau reset branch.

### 2. Sediakan release BERT yang disetujui

Jika menerima archive image melalui saluran privat, jalankan `docker load --input` dengan path archive tersebut, kemudian periksa tag:

```sh
docker image inspect hoaxlin-bert:v1.0.0 --format '{{.Id}}'
```

Alternatif: sediakan packaged release lengkap beserta manifest internal pada path artefak di atas. Script bootstrap membangun image hanya jika tag belum tersedia. Kontrak/checksum dan ketentuan distribusi ada di [MODEL_RELEASE.md](bert-service/MODEL_RELEASE.md). Jangan commit archive, bobot, dataset privat, atau credentials; izin redistribusi model belum terverifikasi.

### 3. Validasi environment dan bootstrap

**Linux, termasuk Fish** (dari root repo):

```sh
bash scripts/docker-setup.sh --validate-only
```

**Windows PowerShell:**

```powershell
.\scripts\docker-setup.ps1 -ValidateOnly
```

Script membuat `.env` dari `.env.docker.example` bila belum ada, lalu mengisi **hanya secret kosong**: `APP_KEY`, `DB_PASSWORD`, `MYSQL_ROOT_PASSWORD`, `BERT_SERVICE_TOKEN`. `--validate-only`/`-ValidateOnly` tidak memulai service atau migration, tetapi **dapat membuat/memperbarui file environment**. Ia memeriksa daemon/konfigurasi, bukan ketersediaan artefak BERT. Jangan jalankan pada `.env` manual dengan host DB berbeda tanpa penyesuaian.

Edit `.env` secara privat bila perlu: `APP_URL`, `APP_HOST_PORT`, mail, dan `OPENAI_API_KEY`. Infrastruktur bisa boot tanpa OpenAI, tetapi OCR/transkripsi/terjemahan yang diperlukan tidak berfungsi dan explanation dapat unavailable. Lalu bootstrap:

```sh
bash scripts/docker-setup.sh
```

```powershell
.\scripts\docker-setup.ps1
```

Pilih salah satu sesuai OS. Script memvalidasi kontrak, menyiapkan BERT/app, memulai MySQL/Redis/BERT/app, menunggu health, menjalankan **`migrate --force`**, lalu memulai queue/scheduler. Tidak melakukan seeding. Pada database yang sudah berisi data, tinjau migration dan backup lebih dahulu. Script memakai ulang `hoaxlin-app:local` bila tersedia; gunakan `--rebuild-app` / `-RebuildApp` untuk membangun source checkout terkini.

Script **belum memulai RAG**. Setelah bootstrap, jalankan:

```sh
docker compose up -d --build rag
docker compose ps
docker compose exec app php artisan hoaxlin:doctor
docker compose exec rag python -c "import urllib.request; print(urllib.request.urlopen('http://127.0.0.1:8002/health/ready', timeout=10).read().decode())"
```

Build pertama RAG mengunduh embedding revision yang dipatok; startup/request sesudahnya memakai cache offline. Healthcheck Compose RAG hanya `/health/live`; container sehat belum tentu index siap. Buka `http://localhost:8000` atau `APP_URL` yang dikonfigurasi.

Script mendukung env/project terpisah: Linux `--env-file PATH --project-name NAME`, Windows `-EnvFile PATH -ProjectName NAME`. Jika dipakai, sertakan `--env-file PATH -p NAME` pada **setiap** perintah Compose berikutnya agar tidak mengoperasikan stack lain. Tidak ada pembuatan akun admin otomatis; registrasi biasa bukan akses admin dan seeder demo bukan langkah setup production.

## Konfigurasi environment

Sumber konfigurasi: [template Docker](.env.docker.example), [template manual](.env.example), [template BERT](bert-service/.env.example), `config/`, dan `docker-compose.yml`. Jangan menampilkan `.env` atau output Compose lengkap di issue publik; gunakan `config --quiet`.

| Kelompok | Docker | Manual / catatan |
| --- | --- | --- |
| Aplikasi | `APP_URL=http://localhost:8000`, `APP_HOST_PORT=8000`, `APP_DEBUG=false` | Set URL sesuai server; `APP_KEY` unik dan stabil, jangan regenerasi pada deployment berdata |
| DB | host `mysql`, user `MYSQL_APP_USER`/`DB_USERNAME` harus sama dan bukan root | host `127.0.0.1`, DB/user lokal berizin minimum; template root bukan rekomendasi deployment |
| Redis/driver | host `redis`, queue/cache/session Redis | template manual memakai database; driver Redis perlu service + phpredis |
| BERT | URL internal, `BERT_MODEL_VERSION=v1.0.0`, secret bootstrap | URL loopback; token Laravel harus cocok dengan `BERT_INTERNAL_API_TOKEN` atau fallback `BERT_SERVICE_TOKEN` service |
| Policy BERT | threshold release `0.99`, legacy response false | `BERT_POLICY_FINGERPRINT` kosong menonaktifkan prediction cache; pin hanya fingerprint release yang diverifikasi |
| RAG | URL `http://rag:8002`, top-k 3, min-score kosong | URL loopback; `RAG_SERVICE_TOKEN` hanya menambah header client—service **belum menegakkan bearer auth** |
| OpenAI | key privat, model sesuai template/Compose | biaya mengikuti kuota/reservation; tidak diperlukan untuk unit test |
| Mail | `MAIL_MAILER=log` | email verifikasi/reset dicatat di log; SMTP nyata memerlukan konfigurasi privat dan recreate container |
| Media | disk privat lokal, `MEDIA_RETENTION_HOURS=24` | direct URL/upload default 24 MiB, durasi 300 detik; S3 hanya bila konfigurasi disk mendukung |

Compose memetakan environment secara eksplisit: menambah key ke `.env` saja tidak selalu meneruskannya ke container. Contoh: retry-after Redis Docker berasal dari `MEDIA_QUEUE_RETRY_AFTER` (default 1800), bukan nilai `REDIS_QUEUE_RETRY_AFTER=240` lama pada template. Audit `docker-compose.yml`/`config/queue.php` sebelum override. Perubahan secret DB pada `.env` tidak mengganti password akun pada volume MySQL yang sudah diinisialisasi; koordinasikan rotasi, jangan hapus volume.

<a id="development-manual"></a>
## Development manual — Linux dan Windows

Docker tetap direkomendasikan. Gunakan database development terpisah, jangan memakai data pengguna untuk test. Jangan menimpa `.env` yang sudah ada; salin template hanya pada checkout baru. Tidak perlu `storage:link` untuk media privat.

### Linux / CachyOS / Arch (Bash atau Fish)

Siapkan runtime pada bagian Requirements dan database lokal terlebih dahulu. Perintah berikut tidak membutuhkan aktivasi virtualenv dan tidak memakai `export` atau sintaks Bash-only:

```sh
cp -n .env.example .env
composer install
npm ci
php artisan key:generate
```

`key:generate` hanya untuk environment baru dengan key kosong. Edit `.env`: DB lokal, `APP_URL=http://localhost:8000`, `BERT_MODEL_VERSION=v1.0.0`, token acak privat yang sama untuk Laravel/BERT, RAG loopback, dan OpenAI/mail bila dipakai. Buat token dengan password manager atau `openssl rand -hex 32`; jangan pakai token contoh bersama. Setelah memeriksa target DB:

```sh
composer check-platform-reqs
php artisan migrate
npm run build
python3.12 -m venv bert-service/.venv
bert-service/.venv/bin/python -m pip install --index-url https://download.pytorch.org/whl/cpu torch==2.13.0
bert-service/.venv/bin/python -m pip install -r bert-service/requirements-runtime.txt
python3.12 -m venv rag-service/.venv
rag-service/.venv/bin/python -m pip install --index-url https://download.pytorch.org/whl/cpu torch==2.13.0
rag-service/.venv/bin/python -m pip install -r rag-service/requirements.txt
```

Jika `python3.12` tidak tersedia pada Arch, sediakan interpreter 3.12 terpisah atau gunakan Docker; jangan menganggap `python` rolling release identik. Di `bert-service/.env`, atur `BERT_MODEL_PATH` ke **path absolut** packaged release yang disetujui, `BERT_MODEL_VERSION=v1.0.0`, `BERT_LOCAL_FILES_ONLY=true`, `BERT_REQUIRE_RELEASE_MANIFEST=true`, dan token yang sama. Konfigurasi service BERT dimuat sebelum root `.env`; environment proses mengalahkan keduanya. Jangan memakai path Windows lama dari template tanpa menggantinya.

Siapkan embedding sekali (dari root repo):

```sh
cd rag-service
.venv/bin/python -m app.prepare_model
cd ..
```

### Windows PowerShell

Gunakan PHP/Composer/Node/Python yang memenuhi Requirements serta DB lokal. Pada checkout baru dengan `.env` belum ada:

```powershell
Copy-Item .env.example .env
composer install
npm ci
php artisan key:generate
```

Edit environment dengan aturan yang sama seperti Linux, lalu:

```powershell
composer check-platform-reqs
php artisan migrate
npm run build
py -3.12 -m venv bert-service/.venv
.\bert-service\.venv\Scripts\python.exe -m pip install --index-url https://download.pytorch.org/whl/cpu torch==2.13.0
.\bert-service\.venv\Scripts\python.exe -m pip install -r bert-service/requirements-runtime.txt
py -3.12 -m venv rag-service/.venv
.\rag-service\.venv\Scripts\python.exe -m pip install --index-url https://download.pytorch.org/whl/cpu torch==2.13.0
.\rag-service\.venv\Scripts\python.exe -m pip install -r rag-service/requirements.txt
Set-Location rag-service
.\.venv\Scripts\python.exe -m app.prepare_model
Set-Location ..
```

Konfigurasikan `bert-service/.env` dengan path absolut release lokal dan strict manifest seperti Linux. Tidak perlu `Activate.ps1` atau mengubah execution policy permanen. Detail inference tersedia di [BERT README](bert-service/README.md).

### Jalankan proses development

Masing-masing baris berikut di **terminal terpisah**, dari root repo (perintah PHP/Node sama di Linux dan PowerShell):

```sh
php artisan serve --host=127.0.0.1 --port=8000
php artisan queue:work --queue=extract-text,extract-media,inference,retrieval,explanation,default --tries=3 --timeout=180
php artisan schedule:work
npm run dev
```

Linux/Fish BERT dan RAG, masing-masing terminal terpisah:

```sh
bert-service/.venv/bin/python -m uvicorn app.main:app --app-dir bert-service --host 127.0.0.1 --port 8001
env HF_HUB_OFFLINE=1 TRANSFORMERS_OFFLINE=1 rag-service/.venv/bin/python -m uvicorn app.main:app --app-dir rag-service --host 127.0.0.1 --port 8002
```

PowerShell:

```powershell
.\bert-service\.venv\Scripts\python.exe -m uvicorn app.main:app --app-dir bert-service --host 127.0.0.1 --port 8001
```

```powershell
$env:HF_HUB_OFFLINE = '1'
$env:TRANSFORMERS_OFFLINE = '1'
.\rag-service\.venv\Scripts\python.exe -m uvicorn app.main:app --app-dir rag-service --host 127.0.0.1 --port 8002
```

`composer run dev` tetap tersedia sebagai convenience **Windows-path**: lima proses (server, queue listener, Pail, Vite, BERT). Bukan all-in-one lintas OS; tidak memulai RAG/scheduler, dan jangan menjalankan BERT/queue/server dua kali. Gunakan proses eksplisit di atas pada Linux. Worker long-running perlu dimulai ulang setelah perubahan code. Hierarki timeout default: provider 90 < job 150 < worker 180 < retry-after 1800 detik. Sesuaikan bersama bila konfigurasi media berubah.

<a id="operasi-aplikasi"></a>
## Menjalankan dan mengoperasikan aplikasi

Perintah Docker berikut memakai default `.env`/project. Setelah setup pertama dan image tersedia:

```sh
docker compose up -d
docker compose ps
docker compose exec app php artisan hoaxlin:doctor
docker compose logs --tail 50 app queue scheduler bert rag
```

Log dapat mengandung detail internal; periksa lokal dan redaksi sebelum dibagikan. `doctor` adalah diagnosis konektivitas/kontrak, bukan bukti akurasi model.

Untuk menerapkan perubahan Laravel/frontend atau `.env` pada stack development yang memang boleh direcreate:

```sh
docker compose build app
docker compose up -d --force-recreate app queue scheduler
```

Restart saja tidak mengganti source yang dibundel atau environment container. Koordinasikan pekerjaan queue sebelum recreate pada deployment berdata. Rebuild RAG hanya bila source/config yang relevan berubah; bootstrap bukan perintah pengecekan CSS karena menjalankan migration.

Recovery dan retensi (mulai dengan dry-run, tinjau sebelum tindakan yang mengubah data):

```sh
docker compose exec app php artisan submissions:recover-stale --dry-run
docker compose exec app php artisan submissions:recover --dry-run
docker compose exec app php artisan media:prune --hours=24 --dry-run
```

Setelah persetujuan, hilangkan `--dry-run` untuk menjalankan tindakan. `submissions:recover-stale` menangani processing yang tersangkut; `submissions:recover` untuk failed submission sesuai opsi command. Scheduler menjalankan `media:prune` setiap menit dan stale recovery setiap lima menit dengan overlap protection. `MEDIA_RETENTION_HOURS` default 24: media completed/failed menjadi **layak dipruning** setelah cutoff; penghapusan bergantung scheduler/storage, bukan jaminan tepat 24 jam. Submission yang masih diproses tidak dipruning; teks, hasil, dan riwayat tidak dihapus oleh pruning media.

Berhenti tanpa menghapus volume:

```sh
docker compose stop
```

`docker compose down` juga tidak menghapus named volume secara default, tetapi menghentikan/menghapus container dan network. Jangan memakai opsi penghapusan volume, wipe DB, fresh migration, atau seeder demo untuk pemulihan rutin. Backup MySQL dan volume media dengan prosedur terkontrol sebelum maintenance; jangan commit hasil backup.

## Testing dan CI

### Laravel dan frontend

Target test Docker terpisah dari production, mencakup dependency dev dan SQLite in-memory. Key berikut **hanya fixture test publik**, bukan credential deployment:

```sh
docker build --target test -t hoaxlin-app:test .
docker run --rm --network none -e APP_KEY=base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA= hoaxlin-app:test
docker run --rm --network none hoaxlin-app:test vendor/bin/pint --test
docker build --target application -t hoaxlin-app:check .
```

Dengan dependency host development yang sesuai:

```sh
php artisan test
node --test tests/Frontend/*.test.mjs
npm run build
php artisan view:cache
php artisan view:clear
```

View cache/clear hanya pada environment development/test milik sendiri. Tidak ada script `npm test`/`npm run lint` di proyek. Build Vite memerlukan vendor Composer karena theme Filament.

### Unit Python tanpa model privat

Gunakan dua venv terpisah karena kedua service memiliki package `app`. Pada Linux, setelah membuat venv seperti development manual:

```sh
bert-service/.venv/bin/python -m pip install -r bert-service/requirements-test.txt
rag-service/.venv/bin/python -m pip install -r rag-service/requirements-test.txt
cd bert-service
env HF_HUB_OFFLINE=1 TRANSFORMERS_OFFLINE=1 .venv/bin/python -m pytest -m 'not release'
cd ../rag-service
env HF_HUB_OFFLINE=1 TRANSFORMERS_OFFLINE=1 .venv/bin/python -m pytest tests
cd ..
rag-service/.venv/bin/python scripts/validate_rag_knowledge_base.py
rag-service/.venv/bin/python -m unittest discover -s tests/rag -p 'test_*.py'
```

BERT test dependencies tetap memerlukan torch CPU yang dipatok (perintah instalasi di atas), **bukan bobot model**. RAG test-only cukup `requirements-test.txt`, tanpa torch/embedding download; venv test baru tidak perlu langkah instalasi runtime RAG. Padanan PowerShell setelah membuat dua venv dan menginstal torch CPU BERT:

```powershell
.\bert-service\.venv\Scripts\python.exe -m pip install -r bert-service/requirements-test.txt
.\rag-service\.venv\Scripts\python.exe -m pip install -r rag-service/requirements-test.txt
$env:HF_HUB_OFFLINE = '1'
$env:TRANSFORMERS_OFFLINE = '1'
Set-Location bert-service
.\.venv\Scripts\python.exe -m pytest -m 'not release'
Set-Location ../rag-service
.\.venv\Scripts\python.exe -m pytest tests
Set-Location ..
.\rag-service\.venv\Scripts\python.exe scripts/validate_rag_knowledge_base.py
.\rag-service\.venv\Scripts\python.exe -m unittest discover -s tests/rag -p 'test_*.py'
```

PHP real-BERT test skip jika service tidak tersedia. Python marker `release` dikeluarkan dari CI publik dan memerlukan frozen artefact; bila dipilih tanpa artefak, test gagal secara eksplisit. Lihat [validasi release](bert-service/MODEL_RELEASE.md).

### CI dan validasi konfigurasi

[Public CI](.github/workflows/ci.yml) menguji Laravel, build frontend/application, JavaScript, unit BERT CPU/offline, RAG fixture/tooling, Compose, Pint, actionlint, dan whitespace. Tidak ada training, API berbayar, kebutuhan bobot BERT privat, push image, atau deployment otomatis.

```sh
docker compose config --quiet
git diff --check
```

Compose membutuhkan required secret pada `.env`; alternatif validasi lokal tanpa secret menggunakan nilai dummy dengan `env` pada Linux/Fish. Jangan memakai nilai dummy pada deployment.

[Workflow checksum privat](.github/workflows/checksum.yml) hanya manual pada default branch. Maintainer perlu environment `bert-release` dengan required reviewers/default-branch protection, variable `BERT_REGISTRY` dan `BERT_RELEASE_IMAGE` (`registry/path@sha256:...`), secret `BERT_REGISTRY_USERNAME`/`BERT_REGISTRY_PASSWORD` pull-only. Workflow mengecek manifest/checksum v1.0.0 dan smoke inference offline pada image privat; tidak mengunggah artefak. Ini konfigurasi maintainer, bukan syarat CI publik.

<a id="indobert-v100"></a>
## IndoBERT v1.0.0

`indobert-hoax v1.0.0` **tetap classifier acuan utama dan artefak baseline-nya frozen (tidak pernah ditimpa)**. Kelas training `valid`/`hoax`; `meragukan` adalah abstention ketika calibrated confidence di bawah threshold release `0.99`. UI menampilkan **“Informasi belum terverifikasi oleh sumber terpercaya”**; ini bukan bukti benar/palsu atau hasil pemeriksaan semua sumber. Release memuat calibration temperature `0.8706620666110391` dan kontrak tokenizer/preprocessing yang harus dipertahankan.

Kurasi dataset skala penuh dan benchmark classifier eksternal lanjutan **ditunda**. Tooling historis di `bert-service/dataset/` dan `train/` disimpan untuk provenance, bukan langkah instalasi. Dataset asli training historis tidak tersedia dari clone; jangan merekonstruksinya dari prediction report atau menganggap file baru sebagai corpus lama. Untuk pelatihan bertahap (*incremental fine-tuning*) yang dikelola admin tanpa mengubah artefak `v1.0.0`, lihat bagian di bawah.

Metrik internal dan external challenge historis tidak menjamin kualitas runtime. External challenge menunjukkan generalization gap; jangan mengiklankan akurasi internal sebagai akurasi produk. Metadata aktif berasal dari `/version`; data tanpa provenance cocok ditampilkan `n/a`. Detail: [BERT teknis](bert-service/README.md), [kontrak release](bert-service/MODEL_RELEASE.md), dan [PRD](PRD.md).

<a id="incremental-fine-tuning-dan-model-versioning"></a>
## Incremental Fine-Tuning & Model Versioning

Administrator dapat melatih versi baru model IndoBERT secara bertahap (*incremental fine-tuning*) dan mengelola versi model aktif dari panel `/admin/model-versions` (**Versi Model**) tanpa me-restart container dan tanpa menimpa artefak baseline `v1.0.0`:

- **Alur pelatihan bertahap & aktivasi (*hot-swap*)**:
  - Admin mengunggah file CSV berkolom `text` dan `label` (`valid` atau `hoax`) dan menentukan nama versi baru (misalnya `v1.1.0`).
  - `StartBertTrainingJob` mengirimkan dataset ke `POST /train` pada `bert-service` (`202 Accepted`, berjalan di background). Pelatihan membekukan embedding serta lapisan encoder bawah (`0..10`) dan hanya memperbarui lapisan teratas (`encoder.layer.11`, pooler, dan classifier) dengan *replay buffer* dari dataset yang telah terverifikasi guna menekan *catastrophic forgetting*.
  - Artefak versi baru disimpan secara terpisah di `/app/models/indobert-hoax/<version_name>/` (mewarisi `threshold.json` dan `calibration.json` dari model dasar), lalu `bert-service` mengirimkan hasil evaluasi ke callback internal `/api/internal/bert/callback`.
  - Selesainya pelatihan (`completed`) **tidak** langsung mengubah model yang melayani trafik publik. Admin meninjau kolom **Perbandingan vs Baseline** (selisih *Accuracy* dan *Macro F1* terhadap `v1.0.0` pada split validasi yang sama) sebelum menekan **Aktifkan Model** (`POST /activate-model`) atau mengembalikan runtime ke acuan lewat **Aktifkan Baseline v1.0.0**.
- **File CSV sampel siap pakai**:
  - Untuk menguji alur pelatihan dan perbandingan metrik di `/admin/model-versions`, gunakan file sampel `datasets/samples/incremental-training-sample.csv` (24 baris berbahasa Indonesia yang seimbang: 12 `valid` dan 12 `hoax`).
- **Mekanisme auto-seed & fallback pada Docker volume `ai_models`**:
  - Container `bert` memasang named volume `ai_models` ke `/app/models`. Karena Docker tidak otomatis menyalin isi image ke volume yang sudah ada sebelumnya, image `hoaxlin-bert:v1.0.0` menyimpan salinan baseline di `/opt/hoaxlin/baseline/indobert-hoax/v1.0.0` (`BERT_BASELINE_FALLBACK_PATH`).
  - Saat startup atau aktivasi baseline, `ModelRuntime.ensure_baseline_available()` otomatis menyalin (*auto-seed*) artefak `v1.0.0` secara atomik ke `/app/models/indobert-hoax/v1.0.0` apabila direktori tersebut belum ada atau kosong pada volume `ai_models` (serta menggunakan path fallback bila volume tidak dapat ditulisi). Direktori versi yang tidak kosong namun korup tetap gagal tertutup (*fail-closed*).
- **Memuat data awal baseline `v1.0.0` di `/admin`**:
  - Script bootstrap Docker hanya menjalankan migrasi (`migrate --force`) dan tidak menjalankan seeder secara otomatis. Agar model baseline `v1.0.0` tampil sebagai baris awal berstatus `completed` dan `Aktif` di tabel `/admin/model-versions`, jalankan `DatabaseSeeder` (yang memanggil `ModelVersionSeeder` secara idempoten):

```sh
docker compose exec app php artisan db:seed --class=DatabaseSeeder --force
```

  - Pada environment manual/lokal: `php artisan db:seed --class=DatabaseSeeder`. Jika hanya ingin memuat rekaman baseline `v1.0.0` tanpa data contoh lainnya, jalankan `php artisan db:seed --class=ModelVersionSeeder --force`.

## RAG knowledge base dan retrieval

Baseline runtime: **KB v1.1.1, 61 dokumen**. Embedding CPU `paraphrase-multilingual-MiniLM-L12-v2` pada revision terkunci; cosine index in-memory, chunk 90 kata/overlap 15, title disertakan, satu hasil terbaik per dokumen, tie-break deterministik. Tidak ada FAISS, live web search, atau training embedding. Default `top_k=3`, `RAG_MIN_SCORE` kosong. RAG tidak memperbaiki label classifier secara otomatis.

24 dokumen berasal dari snapshot v1.0.0 yang sebelumnya ditinjau; 37 tambahan lolos automated checks, **belum human approval independen**. Candidate v1.1.0 dan seluruh manifest/report historis dipertahankan. Evidence menyimpan sumber/judul/URL/tanggal/snippet/rank; `knowledge_base_version` record evidence saat ini dapat null, sehingga provenance runtime belum sepenuhnya dipersist per record.

| Evaluasi historis | Cakupan | Batas interpretasi |
| --- | --- | --- |
| [R11 v1.0.0](reports/rag-retrieval-evaluation-v1.json) | 24 dokumen, 20 query owner-approved | Hanya mengukur snapshot tersebut |
| [R14 v1.1.1](reports/rag-retrieval-evaluation-v1.1.1.json) | 61 dokumen, query/judgment lama yang sama | Tambahan dokumen belum dinilai independen; precision dapat terpengaruh judgment yang belum lengkap |

Perbedaan antarcorpus bukan bukti peningkatan absolut. Threshold sweep historis eksploratif; belum menetapkan threshold production. Service dengan KB kosong tetap live, readiness 503, dan retrieval kosong; jangan mengganti KB production dengan fixture untuk mencobanya.

### Rencana evaluasi berikutnya — baseline vs improvement

Workflow ini **arah kerja berikutnya, belum eksperimen baru yang selesai**:

1. Tetapkan query/relevance judgment independen yang sesuai KB v1.1.1; jangan mengambil classifier challenge/training sebagai KB atau bocorkan holdout untuk tuning.
2. Bekukan corpus, query/judgment, embedding revision, parameter retrieval, code revision, dan batas resource. Jalankan baseline sebelum perubahan.
3. Ubah satu hal yang disetujui, lalu bandingkan pada query dan kondisi yang sama. Jika KB berubah, versioning baru dan penilaian relevansi yang sesuai diperlukan—jangan edit snapshot v1.1.1 diam-diam.
4. Laporkan Hit Rate/Precision/Recall@k, MRR (dan graded metrics jika dataset mendukung), latency/resource, serta contoh kegagalan. Simpan baseline dan improvement pada output berbeda; jangan menimpa laporan historis.
5. Promosi hanya setelah review hasil; jangan mengklaim kenaikan “akurasi” dari sekadar similarity, tambahan dokumen, atau build sukses.

Evaluator yang **sudah tersedia**: `scripts/evaluate_rag_retrieval.py` dengan `--evaluation-directory`, `--corpus-directory`, `--output`. Default-nya masih R11 KB v1.0.0, **bukan baseline runtime v1.1.1**. Untuk reproduksi historis yang disetujui dengan cache embedding tersedia, dari root Linux:

```sh
rag-service/.venv/bin/python scripts/evaluate_rag_retrieval.py --evaluation-directory datasets/rag/evaluation-v1 --corpus-directory datasets/rag/knowledge-base-v1 --output /tmp/hoaxlin-rag-r11-reproduction.json
```

PowerShell gunakan interpreter RAG Windows dan `--output "$env:TEMP/hoaxlin-rag-r11-reproduction.json"`. Pilih nama baru jika output sudah ada. Mengganti `--corpus-directory` ke v1.1.1 saja **tidak cukup**: manifest evaluasi mengikat checksum corpus dan akan menolak ketidakcocokan. Dataset/judgment baru perlu tugas tersendiri; C-04 tidak membuatnya. Detail teknis: [RAG service](rag-service/README.md) dan [kontrak KB/evaluasi](datasets/rag/README.md).

## Troubleshooting

| Gejala | Pemeriksaan/pemulihan aman |
| --- | --- |
| Docker daemon/permission ditolak | `docker version`, context dan akses akun; jangan `chmod 666` socket |
| Bootstrap menolak environment | Jangan campur template manual dan Docker; cek hostname/driver, duplikasi key, user non-root; gunakan env/project terpisah |
| Model BERT tidak tersedia / readiness 503 | Sediakan private image atau packaged release; cek manifest/path/token dan log lokal. Liveness saja bukan readiness; jangan retrain atau download substitute |
| BERT 401 | Cocokkan token Laravel dengan service; recreate container setelah perubahan env |
| RAG sehat tetapi evidence tidak muncul | Cek `/health/ready`, cache embedding/build, KB manifest, queue `retrieval`, serta `RAG_SERVICE_URL`; log detail hanya untuk operator |
| Submission tertahan | `docker compose ps`, queue log, semua named queue, koneksi Redis/BERT; dry-run stale recovery sebelum tindakan |
| Hasil/penjelasan tidak lengkap | Periksa completed vs processing; explanation unavailable/evidence kosong dapat merupakan degradation yang sah |
| Email verifikasi tidak diterima | Mail default log, bukan SMTP; review log secara privat atau konfigurasi SMTP, URL aplikasi dan recreate |
| UI lama setelah edit | Rebuild app dan recreate app/queue/scheduler; restart saja tidak menyalin source |
| Port 8000 terpakai | Selaraskan `APP_HOST_PORT`/`APP_URL`, recreate app; jangan membuka internal service |
| Extension/wheel gagal pada Arch | Cek versi host; pilih Docker/Python 3.12, jangan upgrade dependency terkunci untuk bypass |
| Media ditolak | Cocokkan format/signature/ukuran/durasi dan direct URL; halaman sosial bukan file media |

`ffprobe` opsional untuk verifikasi durasi; bila tidak tersedia pemeriksaan durasi lokal dilewati, validasi ukuran/MIME/signature tetap ada. `clamdscan` opsional sebagai tambahan scanner; bukan alasan menganggap file pasti aman. Image PHP saat ini tidak memasang keduanya. Bila mengubah PHP manual, periksa konfigurasi upload CLI/web—`docker/php/uploads.ini` adalah acuan image, bukan otomatis konfigurasi host.

## Security dan privacy

- Pertahankan CSRF, honeypot, CAPTCHA server-side, validasi URL/redirect SSRF, quota, dan throttle. Default daily quota 30/IP dan 100/akun; OpenAI memakai ledger/reservation bulanan, limiter, serta cache.
- Hasil/status/PDF/media bukan link publik: pemilik/admin sesuai otorisasi atau capability tamu. Media privat tidak memerlukan public symlink. Jangan memublikasikan MySQL/Redis/BERT/RAG.
- RAG saat ini mengandalkan isolasi jaringan, bukan bearer auth yang ditegakkan service. Jangan menganggap `RAG_SERVICE_TOKEN` membuat endpoint publik aman.
- UI meng-escape input/evidence/explanation; rujukan bukan otomatis dukungan terhadap kesimpulan. Jangan merender payload provider mentah ke pengguna.
- OpenAI dapat memproses konten untuk OCR/transkripsi/translation/explanation. Submission/feedback/katalog tidak otomatis menjadi data training.
- Penghapusan akun mencakup data turunan sesuai service deletion, tetapi bukan jaminan pembersihan cache/log/backup/provider eksternal. Retensi media adalah eligibility untuk scheduler, bukan deadline mutlak.
- Writer audit baru meredaksi field sensitif sebelum persistence; **log lama/backup mungkin masih memuat data sensitif**. Jangan ekspor payload untuk audit publik atau menghapus log tanpa rencana yang diotorisasi.
- Simpan `.env`, credentials, model, dataset privat dan backup di luar Git. Lima vulnerability npm dev-toolchain telah diaudit, belum dipatch; jangan mengekspos dev server atau menjalankan command/source map tak tepercaya. Lihat [laporan keamanan C-02A](reports/repository-cleanup/audit-observer-security.md).

## Status proyek saat ini

| Status | Pekerjaan |
| --- | --- |
| Tersedia | Pipeline multi-input, IndoBERT baseline `v1.0.0` (dengan dukungan incremental fine-tuning, perbandingan metrik vs baseline, dan aktivasi *hot-swap* di `/admin`), retrieval lokal KB v1.1.1, explanation grounded, hasil/riwayat/export/admin, CI publik tanpa bobot privat |
| Selesai pada cleanup | C-01 konfigurasi tak terpakai; C-02 Docker/CI; C-02A writer audit; C-03 bytecode/cache; C-04 konsolidasi dokumentasi pada commit ini |
| Prioritas berikutnya | Evaluasi dan peningkatan kualitas RAG dengan baseline vs improvement yang sederhana dan terukur |
| Ditunda | Kurasi dataset skala penuh dan benchmark BERT eksternal lanjutan |
| Tidak dalam roadmap aktif | Penimpaan artefak baseline IndoBERT `v1.0.0` atau kalibrasi ulang threshold/temperature tanpa persetujuan eksplisit |
| Belum tuntas | Judgment representatif KB v1.1.1, pemilihan threshold retrieval berbasis bukti, usability, patch npm terpisah, penilaian log historis, konfigurasi workflow privat oleh maintainer |

Build/test sukses tidak membuktikan kualitas prediksi atau peningkatan akurasi. Laporan historis dipertahankan sebagai bukti dengan cakupan/waktu aslinya, bukan instruksi roadmap aktif. Lihat [PRD](PRD.md) untuk kontrak produk, [AGENTS.md](AGENTS.md) untuk aturan kontribusi agent, dan [laporan cleanup](reports/repository-cleanup/c03.md) untuk batas verifikasi tahap sebelumnya. C-05 tidak dijalankan otomatis.
