# Menjalankan Hoaxlin dengan Docker

Panduan ini menjalankan Hoaxlin di laptop baru menggunakan Docker. Aplikasi tersedia di `http://localhost:8000` secara default.

## Prasyarat host

Host hanya memerlukan:

- Git
- Docker dengan daemon aktif
- Docker Compose

PHP, Composer, Node.js/npm, Python/virtualenv, MySQL, dan Redis tidak perlu dipasang di host.

## Distribusi image BERT

Model fine-tuned tidak tersedia melalui public registry karena izin redistribusi publiknya belum terverifikasi. Dapatkan archive `hoaxlin-bert-v1.0.0.tar` melalui kanal private/controlled dari pengelola project, lalu muat ke Docker sebelum menjalankan setup:

```console
docker load --input <path-to>/hoaxlin-bert-v1.0.0.tar
docker image inspect hoaxlin-bert:v1.0.0
```

Perintah kedua harus menemukan image `hoaxlin-bert:v1.0.0`. Jangan menggantinya dengan image dari public registry.

## Setup pertama

Script setup membuat `.env` jika belum ada, menghasilkan secret lokal yang diperlukan, membangun image Laravel, menjalankan seluruh service, dan menjalankan migration. Secret tidak perlu dibuat secara manual. Jika `.env` sudah ada, nilainya dipertahankan dan divalidasi.

### Windows (PowerShell)

```powershell
git clone <repository-url> hoaxlin
Set-Location hoaxlin
docker load --input <path-to>\hoaxlin-bert-v1.0.0.tar
docker image inspect hoaxlin-bert:v1.0.0
.\scripts\docker-setup.ps1
```

### Linux/macOS

```bash
git clone <repository-url> hoaxlin
cd hoaxlin
docker load --input /path/to/hoaxlin-bert-v1.0.0.tar
docker image inspect hoaxlin-bert:v1.0.0
bash scripts/docker-setup.sh
```

Setup dapat dijalankan ulang dengan aman. File `.env`, secret, database, dan storage yang sudah ada tetap dipertahankan.

Compose menjalankan service internal `rag` pada port container `8002`; Laravel mengaksesnya melalui `http://rag:8002`. Port tersebut tidak dipetakan ke host. Image RAG menyiapkan embedding model pada build dari revision yang dipatok, sehingga build pertama memerlukan akses ke registry model. Runtime container menggunakan file model lokal/offline. Queue tidak menunggu readiness RAG untuk mulai; retrieval adalah dependency non-kritis dan pipeline menangani kegagalannya tanpa membuang hasil classifier.

Environment RAG Docker di `.env.docker.example` memakai `RAG_SERVICE_URL=http://rag:8002`, connect timeout 3 detik, timeout 15 detik, `RAG_SERVICE_TOKEN` kosong, `RAG_TOP_K=3`, dan `RAG_MIN_SCORE` kosong. RAG service saat ini tidak menegakkan bearer-token auth; batas akses Docker adalah network internal dan tidak ada port host. Jangan menganggap `RAG_SERVICE_TOKEN` sebagai access control RAG atau mengisi threshold retrieval seolah-olah itu threshold classifier. Saat ini knowledge base kosong: container tetap sehat berdasarkan `/health/live`, sementara `/health/ready` melaporkan `knowledge_base_empty` dengan HTTP 503. Ini kondisi readiness yang diharapkan sampai dokumen terkurasi tersedia.

## OpenAI

`OPENAI_API_KEY` bersifat opsional untuk bootstrap infrastruktur, tetapi diperlukan saat pipeline benar-benar memanggil OCR gambar, transkripsi video/audio, terjemahan Inggris ke Indonesia, atau explanation. Klasifikasi tetap dilakukan IndoBERT. Gangguan explanation yang non-kritis menghasilkan status explanation `unavailable` tanpa membatalkan hasil BERT yang valid.

Transkripsi menerima upload video MP4/MPEG/WEBM atau URL langsung file audio/video yang didukung, maksimal 24 MiB. URL halaman platform seperti YouTube/TikTok/Instagram/Facebook tidak didukung. Bahasa audio dideteksi provider; audio Inggris kemudian diterjemahkan oleh pipeline sebelum klasifikasi IndoBERT.

`ffprobe` bersifat opsional dan tidak dipasang sebagai dependency wajib image. Jika binary tersedia melalui `FFPROBE_BINARY`, media di atas 300 detik ditolak. Tanpa `ffprobe`, pemeriksaan durasi dilewati; validasi ukuran, extension, MIME, dan signature tetap dijalankan. Hierarki timeout default adalah provider 90 detik, job 150 detik, worker 180 detik, dan Redis `retry_after` 1800 detik.

`clamdscan` juga opsional sebagai defense-in-depth. Jika tersedia, upload dipindai dengan ClamAV. Tanpanya, pemeriksaan pola berbahaya dasar dan validasi upload lainnya tetap berjalan.

Untuk mengaktifkannya, isi nilai berikut di file `.env` yang dibuat oleh setup:

```dotenv
OPENAI_API_KEY=
```

Jangan memasukkan `.env` ke Git. Setelah memperbarui key, terapkan konfigurasi baru tanpa menampilkan nilainya:

```console
docker compose up -d --force-recreate app queue scheduler
```

## Email verifikasi melalui Gmail SMTP (opsional)

Tanpa kredensial, `MAIL_MAILER=log` tetap aman untuk development. Docker memakai `LOG_CHANNEL=stderr`, sehingga email verifikasi pada mode ini dapat dilihat dengan `docker compose logs app`, bukan di `storage/logs/laravel.log`.

Untuk mengirim ke inbox saat demo, isi `.env` pribadi dengan konfigurasi berikut dan nilai akun milik sendiri:

```dotenv
MAIL_MAILER=smtp
MAIL_SCHEME=null
MAIL_URL=null
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_EHLO_DOMAIN=null
MAIL_FROM_ADDRESS=
MAIL_FROM_NAME="${APP_NAME}"
```

Isi `MAIL_PASSWORD` dengan **Google App Password**, bukan password utama Gmail. Samakan alamat pengirim dengan akun SMTP yang digunakan. Jangan commit `.env`; gunakan `APP_DEBUG=false` saat demo agar detail kegagalan SMTP tidak tampil kepada pengguna.

Setelah mengisi `.env`, terapkan environment baru dan periksa hanya nilai non-secret:

```console
docker compose up -d --force-recreate app queue scheduler
docker compose exec app printenv MAIL_MAILER MAIL_HOST MAIL_PORT MAIL_FROM_ADDRESS
```

## Verifikasi

```console
docker compose ps
docker compose exec app php artisan hoaxlin:doctor
```

Periksa `docker compose ps`: service `app`, `mysql`, `redis`, `bert`, dan `rag` harus berstatus healthy; `queue` dan `scheduler` harus healthy/running. Healthcheck RAG memakai `/health/live`, bukan `/health/ready`, sehingga KB kosong tidak membuat container dianggap mati. `php artisan hoaxlin:doctor` memeriksa dependency Laravel termasuk database, Redis, queue, storage, dan BERT, tetapi belum memeriksa RAG; command harus selesai dengan exit code `0` untuk dependency kritis yang diperiksanya. Jika OpenAI belum dikonfigurasi, doctor menampilkan peringatan, tetapi dependency utama tetap dapat dinyatakan siap.

Uji endpoint aplikasi:

```console
curl http://localhost:8000/up
```

## Operasi harian

```console
# Menjalankan service
docker compose up -d

# Melihat status
docker compose ps

# Mengikuti seluruh log
docker compose logs -f

# Mengikuti log service tertentu, misalnya queue
docker compose logs -f queue

# Restart seluruh service
docker compose restart

# Menjalankan migration bila diperlukan
docker compose exec app php artisan migrate --force

# Menghentikan service tanpa menghapus volume/data
docker compose down
```

Setelah source aplikasi diperbarui, image Laravel dapat dibangun ulang melalui setup resmi:

```powershell
.\scripts\docker-setup.ps1 -RebuildApp
```

```bash
bash scripts/docker-setup.sh --rebuild-app
```

## Data persistence

MySQL menggunakan named volume `mysql_data`. File Laravel yang perlu dibagi antara `app`, `queue`, dan `scheduler` menggunakan named volume `laravel_storage`.

`docker compose down` menghentikan container dan network, tetapi tidak menghapus kedua volume tersebut. Karena itu database dan storage tetap tersedia saat environment dijalankan kembali.

## Troubleshooting

### Docker daemon tidak aktif

Jalankan Docker Desktop atau daemon Docker, lalu verifikasi koneksi:

```console
docker version
docker compose version
```

### Image BERT tidak tersedia

Setup tidak menarik model dari public registry. Muat kembali archive private, lalu verifikasi tag-nya:

```console
docker load --input <path-to>/hoaxlin-bert-v1.0.0.tar
docker image inspect hoaxlin-bert:v1.0.0
```

### OpenAI key kosong

Bootstrap tetap dapat selesai, tetapi OCR, transkripsi, terjemahan, dan explanation yang membutuhkan provider tidak akan bekerja. Isi `OPENAI_API_KEY` di `.env`, lalu recreate service `app`, `queue`, dan `scheduler` seperti dijelaskan pada bagian OpenAI.

### Port Laravel bentrok

Ubah kedua nilai berikut di `.env`, misalnya ke port `8080`:

```dotenv
APP_HOST_PORT=8080
APP_URL=http://localhost:8080
```

Lalu terapkan perubahan:

```console
docker compose up -d --force-recreate app
```

### Service unhealthy

Periksa status, log service terkait, lalu jalankan diagnostik aplikasi jika `app` dapat berjalan:

```console
docker compose ps
docker compose logs --tail=100 <service>
docker compose exec app php artisan hoaxlin:doctor
```

Nama service yang tersedia adalah `app`, `queue`, `scheduler`, `mysql`, `redis`, `bert`, dan `rag`. Untuk memeriksa liveness RAG dari dalam container:

```console
docker compose exec rag python -c "import urllib.request; print(urllib.request.urlopen('http://127.0.0.1:8002/health/live', timeout=3).read().decode())"
docker compose exec rag python -c "import urllib.request; print(urllib.request.urlopen('http://127.0.0.1:8002/version', timeout=3).read().decode())"
```

Dengan KB kosong, `/health/ready` yang mengembalikan 503 `knowledge_base_empty` adalah kondisi yang diharapkan; periksa `docker compose logs rag` untuk error startup yang lain. Setelah penyebabnya diperbaiki, service tertentu dapat dimulai ulang dengan `docker compose restart <service>`.

## Batasan

- Redis, MySQL, BERT, dan RAG hanya tersedia di network internal Docker; hanya Laravel yang dipublikasikan ke localhost secara default.
- Redistribusi publik model fine-tuned belum terverifikasi. Archive dan image BERT harus dipindahkan melalui kanal private/controlled.
- Fitur OpenAI memerlukan koneksi internet dan API key yang valid.
- Bootstrap/build pertama mungkin memerlukan internet untuk mengambil base image dan dependency yang belum tersedia di cache Docker.

## Scheduler dan retensi media

Service `scheduler` menjalankan `php artisan schedule:work` tanpa port host. Schedule Laravel menjalankan stale-processing recovery setiap lima menit dan `media:prune` setiap menit, masing-masing dengan overlap protection.

Retensi default media asli adalah 24 jam setelah `processing_completed_at` untuk submission terminal `completed` atau `failed`. Submission processing tidak disentuh dan data teks/result/history tetap dipertahankan. Pemeriksaan manual:

```console
docker compose exec app php artisan media:prune --hours=24 --dry-run
docker compose exec app php artisan submissions:recover-stale --dry-run
```
