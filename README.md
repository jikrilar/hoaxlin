# hoaxlin.id — Deteksi Hoaks Berbasis IndoBERT

Hoaxlin adalah aplikasi Laravel 12 dengan layanan FastAPI terpisah untuk IndoBERT dan retrieval RAG. Classifier tetap menggunakan model frozen `indobert-hoax v1.0.0` untuk label training `valid` atau `hoax`; `meragukan` adalah abstention di serving layer ketika confidence di bawah threshold, bukan kelas training. Pada UI, label itu ditampilkan sebagai **Informasi belum terverifikasi oleh sumber terpercaya**. RAG mencari referensi dari knowledge base lokal dan tidak mengubah label classifier. OpenAI membuat explanation grounded dari hasil classification, excerpt, dan evidence; layanan OpenAI yang sama juga mendukung OCR, transkripsi, dan terjemahan EN→ID pada tahap input.

## Fitur dan batas akses

- Guest hanya dapat mengirim teks dan membuka hasilnya dengan capability token submission tersebut.
- User login dapat mengirim teks, URL artikel, gambar, upload video, atau URL langsung file audio/video. Riwayat dan hasil user dilindungi ownership.
- URL media harus menunjuk langsung ke file yang didukung. Halaman YouTube, TikTok, Instagram, Facebook, player, HTML, dan JSON tidak didukung.
- Hasil klasifikasi BERT adalah hasil inti. Penjelasan dapat berstatus `unavailable` tanpa membatalkan klasifikasi yang valid.
- Halaman hasil melakukan polling status; aplikasi tidak mengirim completion notification.

## Arsitektur dan pipeline

| Komponen | Tanggung jawab |
|---|---|
| Laravel | HTTP, autentikasi/otorisasi, validasi, penyimpanan, queue, admin Filament |
| FastAPI/IndoBERT | Klasifikasi teks dan metadata runtime `/version` |
| `rag-service` | Retrieval evidence lokal dengan embedding multilingual dan cosine similarity |
| OpenAI | OCR, transkripsi, terjemahan EN→ID, dan penjelasan naratif |
| MySQL | Data aplikasi dan ledger kuota OpenAI bulanan |
| Redis | Queue, cache, session, lock, dan limiter sementara |

```text
Input
  ↓
Extraction
  ↓
Translation
  ↓
IndoBERT Classification
  ↓
Evidence Retrieval
  ↓
Grounded Explanation
  ↓
Result
```

Rangkaian job aktual dimulai dengan `ProcessSubmission`, yang dispatches `ExtractSubmissionText`; extraction melanjutkan ke `TranslateSubmissionText`, lalu tahap akhir berjalan sebagai `ClassifySubmission` -> `RetrieveSubmissionEvidence` -> `GenerateSubmissionExplanation`. Retrieval hanya berjalan setelah classification berhasil. Stage retrieval terpisah dari classification dan explanation, menggunakan queue `retrieval`, serta tidak dapat mengubah `DetectionResult.label` atau confidence classifier. Jika RAG gagal atau tidak menemukan evidence, explanation tetap dapat berjalan; sumber tidak dibuat sebagai pengganti evidence yang kosong. Skor similarity adalah relevansi retrieval, bukan confidence model atau bukti kebenaran.

Stage progres adalah `queued → extracting → translating → classifying → retrieving → explaining → done`. Teks Indonesia melewati stage translation tanpa pemanggilan provider; teks Inggris diterjemahkan ke Indonesia sebelum IndoBERT. Retrieval evidence memakai queue `retrieval` dan tidak mengubah hasil classifier. `DetectionResult` parsial tidak dianggap final sebelum status submission `completed`.

## Setup pengembangan

Prasyarat manual: PHP 8.2+, Composer, Node.js/npm, MySQL, Python 3.10+, virtual environment BERT, dan artifact model release yang disetujui.

```powershell
composer install
npm install
Copy-Item .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan storage:link

Set-Location bert-service
py -m venv .venv
.\.venv\Scripts\Activate.ps1
pip install -r requirements.txt
Copy-Item .env.example .env
Set-Location ..
```

Isi konfigurasi lokal dengan nilai sendiri; jangan commit credential:

```dotenv
DB_DATABASE=hoax_detector
BERT_SERVICE_URL=http://127.0.0.1:8001
BERT_SERVICE_TOKEN=<generate-a-local-token>
RAG_SERVICE_URL=http://127.0.0.1:8002
RAG_SERVICE_CONNECT_TIMEOUT=3
RAG_SERVICE_TIMEOUT=15
RAG_SERVICE_TOKEN=
RAG_TOP_K=3
RAG_MIN_SCORE=
OPENAI_API_KEY=
```

Token Laravel dan BERT service harus identik. Konfigurasi lengkap tersedia di `.env.example` dan `bert-service/.env.example`.

### Mode A: all-in-one

```powershell
composer run dev
```

Script ini menjalankan lima proses dari `composer.json`: Laravel server, queue listener, Pail, Vite, dan uvicorn BERT. Jangan menjalankan BERT lagi di terminal kedua saat memakai mode ini.

### Mode B: manual

Jalankan masing-masing di terminal terpisah:

```powershell
php artisan serve
php artisan queue:work --queue=extract-text,extract-media,inference,retrieval,explanation,default --tries=3
php artisan pail --timeout=0
npm run dev
.\bert-service\.venv\Scripts\python.exe -m uvicorn app.main:app --app-dir bert-service --host 127.0.0.1 --port 8001
```

Named queue yang harus dikonsumsi adalah `default`, `extract-text`, `extract-media`, `inference`, `retrieval`, dan `explanation`.

### Menjalankan RAG secara lokal

`composer run dev` tidak memulai `rag-service`; jalankan service ini di terminal terpisah bila ingin menguji retrieval lokal. Default `RAG_SERVICE_URL` pada `.env.example` adalah `http://127.0.0.1:8002`.

```powershell
Set-Location rag-service
py -m venv .venv
.\.venv\Scripts\python.exe -m pip install -r requirements.txt
.\.venv\Scripts\python.exe -m uvicorn app.main:app --host 127.0.0.1 --port 8002
```

Snapshot knowledge base v1.0.0 memuat 24 dokumen dan evaluation v1.0.0 memuat 20 query dengan relevance judgment yang telah disetujui owner. Evaluasi R11 final untuk snapshot ini melaporkan Hit Rate@3/@5 1.0, Precision@3 0.4, Precision@5 0.25, Recall@3 0.975, Recall@5 1.0, dan MRR 0.95. Kandidat threshold 0.4–0.6 bersifat eksploratif; `RAG_MIN_SCORE` production tetap kosong karena 20 query belum cukup untuk memilih threshold secara robust. Untuk menjalankan service lokal, siapkan revision model embedding yang dipatok terlebih dahulu dengan `python -m app.prepare_model`; langkah ini memerlukan jaringan satu kali. Perilaku saat knowledge base kosong tetap diuji menggunakan fixture sementara, bukan corpus production.

## Kontrak media dan dependency opsional

- Upload video: MP4, MPEG, WEBM.
- Direct media URL: FLAC, MP3, MP4, MPEG/MPGA, M4A, OGG, WAV, WEBM dengan pasangan extension dan Content-Type yang kompatibel.
- Ukuran maksimum upload maupun download: 24 MiB.
- Bahasa transkripsi dideteksi provider. Audio Indonesia tidak diterjemahkan; audio Inggris masuk stage EN→ID sebelum klasifikasi.
- Hierarki timeout default: provider 90 detik < job 150 detik < worker 180 detik < queue `retry_after` 1800 detik.
- Fetch artikel dan media memvalidasi target awal dan setiap redirect terhadap SSRF.

`ffprobe` opsional. Jika tersedia, durasi diverifikasi terhadap `MEDIA_TRANSCRIPTION_MAX_DURATION` (default 300 detik). Tanpanya, pemeriksaan durasi lokal dilewati, tetapi validasi ukuran, extension, MIME, dan signature tetap berjalan.

`clamdscan` opsional sebagai defense-in-depth. Jika tersedia, upload dipindai ClamAV. Tanpanya, scanner tetap menjalankan pemeriksaan pola berbahaya dasar dan validasi upload lainnya tetap berlaku.

## Queue, recovery, dan retensi

```powershell
php artisan submissions:recover-stale --dry-run
php artisan submissions:recover-stale
php artisan submissions:recover --dry-run
php artisan media:prune --hours=24 --dry-run
php artisan media:prune --hours=24
php artisan hoaxlin:doctor
```

Scheduler mendaftarkan `media:prune` setiap menit dan `submissions:recover-stale` setiap lima menit dengan overlap protection. Media asli lokal dipertahankan maksimal `MEDIA_RETENTION_HOURS` (default 24 jam) setelah terminal `completed` atau `failed`; submission yang masih diproses tidak disentuh. Teks, hasil, dan history tidak dihapus oleh pruning media.

## CAPTCHA dan kuota

Submission memakai CAPTCHA one-time yang di-reserve/consume secara atomik. Daily quota default adalah 30 submission per IP dan 100 per akun, terpisah dari burst throttle route. Limiter OpenAI per menit memakai counter dengan TTL; budget bulanan memakai ledger database durable dengan reservation dan reconciliation. Cache hit tidak dihitung sebagai request provider baru.

## Admin, dataset, dan statistik model

Panel `/admin` hanya dapat diakses admin. **Katalog Dataset** adalah katalog/kurasi production; hanya admin dapat menjadi verifier. Record katalog bukan corpus training aktif dan tidak memicu export, sinkronisasi, retraining, reload, atau promosi model.

Training BERT tetap offline dan versioned melalui `bert-service/dataset/` dan `bert-service/train/`, memakai file di `datasets/processed/`. Model baru hanya aktif melalui evaluasi, export artifact, release, dan deployment eksplisit.

Model aktif dan threshold pada UI berasal dari runtime BERT `/version`, bukan histori hasil. Metrik evaluasi hanya tampil jika artifact aktif memberi provenance yang cocok. Latency adalah median `inference_ms` untuk completed classifications pada versi aktif, bukan total waktu proses. Data yang tidak dapat diverifikasi ditampilkan sebagai `n/a`.

## Testing

```powershell
php artisan test

Set-Location bert-service
.\.venv\Scripts\python.exe -m pytest -q
Set-Location ..

Set-Location rag-service
.\.venv\Scripts\python.exe -m pip install -r requirements-dev.txt
.\.venv\Scripts\python.exe -m pytest tests
Set-Location ..

python scripts\validate_rag_knowledge_base.py
python -m unittest discover -s tests\rag -p 'test_*.py'
.\rag-service\.venv\Scripts\python.exe scripts\evaluate_rag_retrieval.py

docker build --target test -t hoaxlin-app:test .
docker run --rm -e APP_KEY="base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=" hoaxlin-app:test
docker compose config --quiet
```

Test real-BERT membutuhkan service dan artifact yang tersedia; test tersebut skip secara eksplisit jika dependency tidak tersedia.
Artifact evaluasi R11 untuk snapshot yang disetujui tersedia di `reports/rag-retrieval-evaluation-v1.json`. Ia terikat pada checksum corpus dan query yang tercatat pada manifest. Jalankan ulang hanya ketika snapshot yang dievaluasi berubah dan telah melalui review.

## Docker

Docker adalah referensi deployment lokal utama. Lihat [DOCKER-SETUP.md](DOCKER-SETUP.md) untuk bootstrap dan [RUNBOOK.md](RUNBOOK.md) untuk operasi.

```console
docker compose up -d
docker compose ps
docker compose exec app php artisan hoaxlin:doctor
docker compose down
```

Compose menjalankan `app`, `queue`, `scheduler`, `mysql`, `redis`, `bert`, dan `rag`. Laravel mengakses RAG melalui `http://rag:8002` pada network internal `hoaxlin`; port RAG tidak dipublikasikan ke host. Healthcheck container RAG memakai `/health/live`, karena readiness memang 503 selama KB kosong. Hanya `app` mengekspos port host. `docker compose down` tidak menghapus named volume; jangan gunakan `down -v` sebagai operasi normal.

## Privasi

- Media asli dipruning maksimal 24 jam setelah processing terminal.
- Delete account menghapus permanen akun, submission/history, result, feedback, event turunan, dan media terkait milik user.
- Konten yang memerlukan OCR, transkripsi, terjemahan, atau explanation dapat diproses OpenAI.
- Submission, feedback, dan katalog production tidak otomatis menjadi data training.

## Referensi

- [PRD.md](PRD.md) — ruang lingkup produk dan akademik
- [DOCKER-SETUP.md](DOCKER-SETUP.md) — bootstrap Docker
- [RUNBOOK.md](RUNBOOK.md) — operasi, recovery, dan retensi
- [datasets/rag/README.md](datasets/rag/README.md) — schema KB dan evaluasi retrieval
- [rag-service/README.md](rag-service/README.md) — service retrieval lokal
- [TASK-RAG-HOAXLIN.md](TASK-RAG-HOAXLIN.md) — status implementasi R1–R12
- [bert-service/README.md](bert-service/README.md) — serving, dataset, training, dan release BERT
- [TASK.md](TASK.md) — backlog dan hasil verifikasi historis
