# Runbook — hoaxlin.id

## Health dan diagnosis

Pada Docker, gunakan service name internal untuk BERT:

```console
docker compose ps
docker compose exec app php artisan hoaxlin:doctor
docker compose exec app php artisan queue:failed
docker compose logs --tail=100 app queue scheduler bert rag
```

Endpoint aplikasi adalah `GET /up`. BERT menyediakan `/health/live`, `/health/ready`, `/version`, dan `/metrics`. Metadata model aktif dan threshold berasal dari `/version`; ketidaktersediaan metadata ditampilkan sebagai `n/a`, bukan ditebak dari histori database.

## Queue

Named queue yang harus dikonsumsi:

```text
default, extract-text, extract-media, inference, retrieval, explanation
```

Worker manual:

```console
php artisan queue:work --queue=default,extract-text,extract-media,inference,retrieval,explanation --tries=3
```

Queue `retrieval` menjalankan `RetrieveSubmissionEvidence` setelah classification. Worker Docker juga mengonsumsi queue ini. Worker tidak bergantung pada readiness RAG untuk mulai; kegagalan retrieval dicatat sebagai degradation dan explanation tetap dicoba.

Operasi failed job:

```console
php artisan queue:failed
php artisan queue:retry <job-id>
php artisan queue:restart
```

Jangan me-retry seluruh failed job tanpa diagnosis. Error permanen tidak seharusnya diulang; error transient mengikuti retry policy job.

## Recovery submission

Watchdog stale processing dijalankan scheduler setiap lima menit. Pemeriksaan manual:

```console
php artisan submissions:recover-stale --dry-run
php artisan submissions:recover-stale
php artisan submissions:recover-stale --id=<submission-id>
```

Command tersebut memulihkan stage aman yang relevan dan tidak mengulang hasil klasifikasi valid secara duplikat. Untuk submission yang sudah terminal `failed` dan telah didiagnosis:

```console
php artisan submissions:recover --dry-run
php artisan submissions:recover --id=<submission-id>
```

## Media retention

Default retensi berasal dari `MEDIA_RETENTION_HOURS=24`. Media eligible hanya bila submission terminal `completed` atau `failed` dan `processing_completed_at` sudah melewati cutoff. Submission processing, extracted text, translation, result, dan history tidak dihapus.

```console
php artisan media:prune --hours=24 --dry-run
php artisan media:prune --hours=24
```

`routes/console.php` menjadwalkan `media:prune` setiap menit dengan `onOneServer()` dan `withoutOverlapping()`. Docker service `scheduler` menjalankan `php artisan schedule:work` dan tidak mengekspos port host.

## Dependency media opsional

- `ffprobe`: bila tersedia, durasi diverifikasi terhadap batas konfigurasi. Bila tidak tersedia, duration check lokal dilewati; size, extension, MIME, dan signature tetap divalidasi.
- `clamdscan`: optional defense-in-depth. Bila tersedia, ClamAV dipakai; bila tidak, pemeriksaan pola berbahaya dasar dan validasi upload lain tetap berjalan.

## Explanation unavailable

Klasifikasi IndoBERT adalah hasil inti. Gangguan RAG tidak mengubah atau menghapus label dan confidence classifier; retrieval dapat berakhir tanpa evidence dan pipeline tetap meneruskan explanation. Gangguan OpenAI non-kritis pada stage explanation menghasilkan `explanation_status=unavailable` dan submission tetap `completed`. Jangan me-retry atau mengubah hasil classifier hanya untuk memaksa evidence atau narasi tersedia. Kegagalan persistence/invariant internal tetap diperlakukan sebagai failure.

## RAG dan knowledge base

Di Docker, Laravel memanggil `http://rag:8002` melalui network internal `hoaxlin`. Service `rag` tidak memiliki host port; healthcheck container menggunakan `/health/live`. Periksa kondisi service dengan:

```console
docker compose ps rag queue
docker compose logs --tail=100 rag queue
docker compose exec rag python -c "import urllib.request; print(urllib.request.urlopen('http://127.0.0.1:8002/health/live', timeout=3).read().decode())"
docker compose exec rag python -c "import urllib.request; print(urllib.request.urlopen('http://127.0.0.1:8002/version', timeout=3).read().decode())"
```

Production knowledge base berada di `datasets/rag/knowledge-base-v1/` dan saat ini memiliki 0 dokumen. Karena itu `/health/ready` mengembalikan 503 `knowledge_base_empty`; ini bukan kegagalan liveness. Request retrieval yang valid mengembalikan daftar kosong dan tidak membuat sumber. `RAG_MIN_SCORE` production tetap kosong karena evaluasi kualitas belum dapat dilakukan.

Validasi knowledge base dan jalankan regression evaluasi dari root repository:

```powershell
python scripts\validate_rag_knowledge_base.py
python -m unittest discover -s tests\rag -p 'test_*.py'
Set-Location rag-service
.\.venv\Scripts\python.exe -m pytest tests
Set-Location ..
```

Framework retrieval evaluation dijalankan dari root dengan `.\rag-service\.venv\Scripts\python.exe scripts\evaluate_rag_retrieval.py`. Report deterministik berada di `reports/rag-retrieval-evaluation-v1.json`. Saat KB masih kosong, report berstatus `blocked`, tidak berisi metric production, dan tidak menetapkan threshold. Rincian schema dan metric ada di [datasets/rag/README.md](datasets/rag/README.md).

## Docker lifecycle

```console
docker compose up -d
docker compose ps
docker compose down
```

Service normal adalah `app`, `queue`, `scheduler`, `mysql`, `redis`, `bert`, dan `rag`. `app` dan `queue` tidak menunggu RAG readiness; `queue` menunggu BERT healthy, lalu retrieval menangani RAG failure secara non-kritis. `docker compose down` mempertahankan named volume. Jangan gunakan `docker compose down -v` sebagai operasi normal.

Setelah source Laravel berubah:

```powershell
.\scripts\docker-setup.ps1 -RebuildApp
```

atau:

```bash
bash scripts/docker-setup.sh --rebuild-app
```

## Model release

Training menggunakan dataset filesystem offline/versioned dan bukan tabel `datasets` Laravel. Mengubah katalog admin tidak menjalankan training atau mengubah model aktif. Rollback/promotion model dilakukan dengan memilih artifact release yang telah dievaluasi, memperbarui konfigurasi/image BERT, lalu me-recreate service `bert`; tidak ada auto-promotion.

## Log aman

Diagnosis pipeline dicatat dengan submission ID, stage, service, error code, attempt, exception class, dan correlation reference yang tersanitasi. Jangan menyalin API key, bearer token, URL credential, provider payload, raw exception, atau connection string ke tiket maupun laporan publik.
