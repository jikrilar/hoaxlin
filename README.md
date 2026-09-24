# hoaxlin.id — Deteksi Hoaks Berbasis IndoBERT

Hoaxlin adalah aplikasi Laravel 12 dengan layanan inferensi FastAPI. IndoBERT menentukan label inti `valid` atau `hoax`; `meragukan` adalah abstention ketika confidence tidak melewati threshold runtime. OpenAI mendukung OCR, transkripsi, terjemahan Inggris ke Indonesia, dan penjelasan naratif—bukan classifier.

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
| OpenAI | OCR, transkripsi, terjemahan EN→ID, dan penjelasan naratif |
| MySQL | Data aplikasi dan ledger kuota OpenAI bulanan |
| Redis | Queue, cache, session, lock, dan limiter sementara |

```text
ProcessSubmission
→ ExtractSubmissionText
→ TranslateSubmissionText
→ ClassifySubmission
→ RetrieveSubmissionEvidence
→ GenerateSubmissionExplanation
→ completed | failed
```

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

docker build --target test -t hoaxlin-app:test .
docker run --rm -e APP_KEY="base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=" hoaxlin-app:test
docker compose config --quiet
```

Test real-BERT membutuhkan service dan artifact yang tersedia; test tersebut skip secara eksplisit jika dependency tidak tersedia.

## Docker

Docker adalah referensi deployment lokal utama. Lihat [DOCKER-SETUP.md](DOCKER-SETUP.md) untuk bootstrap dan [RUNBOOK.md](RUNBOOK.md) untuk operasi.

```console
docker compose up -d
docker compose ps
docker compose exec app php artisan hoaxlin:doctor
docker compose down
```

Compose menjalankan `app`, `queue`, `scheduler`, `mysql`, `redis`, dan `bert`. Hanya `app` mengekspos port host. `docker compose down` tidak menghapus named volume; jangan gunakan `down -v` sebagai operasi normal.

## Privasi

- Media asli dipruning maksimal 24 jam setelah processing terminal.
- Delete account menghapus permanen akun, submission/history, result, feedback, event turunan, dan media terkait milik user.
- Konten yang memerlukan OCR, transkripsi, terjemahan, atau explanation dapat diproses OpenAI.
- Submission, feedback, dan katalog production tidak otomatis menjadi data training.

## Referensi

- [PRD.md](PRD.md) — ruang lingkup produk dan akademik
- [DOCKER-SETUP.md](DOCKER-SETUP.md) — bootstrap Docker
- [RUNBOOK.md](RUNBOOK.md) — operasi, recovery, dan retensi
- [bert-service/README.md](bert-service/README.md) — serving, dataset, training, dan release BERT
- [TASK.md](TASK.md) — backlog dan hasil verifikasi historis
