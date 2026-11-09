# Incremental Fine-Tuning & Model Versioning IndoBERT — Laporan Tahap 1–3

## Ringkasan

Fitur: admin mengunggah dataset CSV, `bert-service` melatih versi baru secara
bertahap di latar belakang, hasilnya tercatat sebagai `ModelVersion`, dan admin
mengaktifkan atau mengembalikan model secara manual lewat hot-swap.

Branch `feat/incremental-bert-training`, turunan `c1c44bd` (`main`). Tiga commit lokal,
belum di-push, working tree bersih.

| Tahap | Commit | Cakupan | Berkas diubah |
| --- | --- | --- | --- |
| 1 | `5fee699` | Skema, model, disk, volume Docker | 10 (+471) |
| 2 | `f640675` | Trainer, `/train`, `/activate-model` di `bert-service` | 12 (+2.874 / −182) |
| 3 | `51d4ae9` | Orkestrasi Laravel, callback, audit, UI Filament | 30 (+2.741 / −10) |

Baseline `indobert-hoax v1.0.0` tidak diubah: bobot, threshold, kalibrasi, tokenizer,
dan hash frozen `KNOWN_MODEL_HASHES["v1.0.0"]` tetap sama. Versi baru bersifat
tambahan dan tidak pernah aktif otomatis. `DecisionPolicy`,
`CachedBertClassifier`, `ModelVersionWidget`, dan `public/` tidak disentuh.

## Tahap 1 — Fondasi data dan infrastruktur (`5fee699`)

- Migrasi `2026_11_08_000001_create_model_versions_table`: `version_name` unik,
  `dataset_path`, `total_rows`, `status` enum (`pending`, `training`, `completed`,
  `failed`; terindeks), `accuracy`, `f1_score`, `training_duration`,
  `error_message`, `is_active` (terindeks), timestamps.
- `App\Models\ModelVersion`: konstanta status, helper `isPending/isTraining/isCompleted/isFailed`,
  scope, dan `activate()` yang menjamin hanya satu versi aktif.
- `ModelVersionFactory` dan `tests/Feature/ModelVersionTest.php`.
- `config/filesystems.php`: disk `datasets` (root `storage/app/datasets`).
- `docker-compose.yml`: volume `ai_models` (`/app/models`, baca-tulis di `bert`)
  dan `training_datasets` (dipasang ke `app`/`queue`/`scheduler` di
  `storage/app/datasets`, dan `/app/datasets:ro` di `bert`).
- `Dockerfile` membuat `storage/app/datasets`; `.gitignore` dan
  `storage/app/.gitignore` mengecualikan data dari Git (`/storage/ai_models/`,
  isi `datasets/`).

## Tahap 2 — Trainer dan API `bert-service` (`f640675`)

- `trainer.py`: pembacaan CSV UTF-8 (BOM, header `text`/`label`), label biner
  (`valid`/`hoax` dan alias); `meragukan`/`uncertain` ditolak karena merupakan
  abstain turunan threshold, bukan kelas training.
- Replay buffer (`replay_buffer.csv`, maks 500 sampel, seimbang per kelas, teks
  yang sama dengan data baru dikecualikan); split validasi 20% bila ≥10 sampel
  dan kedua kelas ≥2, selain itu fallback evaluasi pada data latih (ditandai
  `train-fallback`).
- Layer freezing: hanya `bert.encoder.layer[-1]` dan `classifier` yang dilatih;
  2–3 epoch, AdamW, scheduler linear dengan warmup, gradient clipping.
- Ekspor atomik: tulis ke direktori staging `.staging-*`, buat sidecar
  (`threshold.json`, `calibration.json` diwarisi dari model dasar),
  `evaluation.json`, `MODEL_CARD.md`, `manifest.json` (`kind: incremental-finetune`,
  hash per berkas), validasi `validate_incremental_release`, lalu `rename`
  ke `/app/models/{version_name}`. Staging dibersihkan bila gagal.
- `POST /train` → `202 Accepted` dengan `BackgroundTasks`; menerima multipart
  (`csv_file`) atau JSON; satu job pada satu waktu (`409 training_in_progress`),
  `409 version_already_exists`, `422` validasi, callback dengan bearer token dan
  3 percobaan ulang.
- `POST /activate-model`: hot-swap thread-safe (state di-build penuh lebih dulu,
  lalu di-commit di bawah lock; request `/predict` memakai snapshot state).
  `404` bila versi tidak ada, `422` bila artefak tidak valid. Baseline `v1.0.0`
  boleh diaktifkan kembali, tetapi `version_name` pelatihan tidak boleh menimpanya.
- `validate_version_name` menolak `..`, nama `indobert-hoax`, dan baseline
  pada pelatihan. Kontrak `POST /predict` dipertahankan.
- Tes: `bert-service/tests/test_incremental_training.py`.

## Tahap 3 — Orkestrasi Laravel dan UI admin (`51d4ae9`)

**Orkestrasi**
- `BertCsvValidator`: validasi CSV (UTF-8, BOM, header, label biner, batas ukuran
  `BERT_MAX_TRAIN_CSV_BYTES`) selaras dengan `trainer.py` sebelum disimpan/diantre.
- `BertTrainingService`: `startTraining()` (multipart `POST /train`),
  `activateModelVersion()`, `activateBaseline()`. Status `is_active` di database
  baru diubah setelah `POST /activate-model` membalas sukses dan sesuai kontrak.
- `StartBertTrainingJob`: `ShouldBeUnique`, antrean `default` dengan
  `afterCommit()`, `Cache::lock`; mengubah `pending → training` sebelum HTTP
  `/train`, dikembalikan ke `pending` pada kegagalan sementara dengan backoff,
  `failed` pada kegagalan permanen.

**Callback internal**
- `POST /api/internal/bert/callback` (`routes/api.php`, di-wire di `bootstrap/app.php`).
- Middleware `VerifyBertInternalToken`: `503` bila token tidak dikonfigurasi,
  `401` + `WWW-Authenticate: Bearer` bila token salah, perbandingan `hash_equals`.
- `BertCallbackController` transaksional (`lockForUpdate`): `404` bila versi
  tidak ada, `200` idempoten untuk status akhir yang sama, `409` untuk status
  akhir yang berbeda; `failed` mengosongkan metrik; tidak pernah mengaktifkan
  model otomatis.

**Audit**
- `FilamentAuditObserver` dan `MonitoringPresentation` memiliki allowlist
  `ModelVersion`: `dataset_path` dan `error_message` selalu diganti
  `[nilai disembunyikan]`; status/versi/angka divalidasi sebelum disimpan.

**Filament 5 `ModelVersionResource`**
- Halaman `index`, `create`, `view`; tidak ada edit/hapus (`ModelVersionPolicy`,
  hanya admin; `activate` hanya untuk versi `completed` yang belum aktif).
- Form: nama versi (regex, unik, bukan `v1.0.0`/`indobert-hoax`) dan unggah CSV
  yang divalidasi `BertCsvValidator`; CSV yatim dihapus bila pembuatan record gagal.
- Tabel: polling 5 detik hanya saat ada versi `pending`/`training`; aksi
  `Aktifkan Model` dan `Aktifkan Baseline v1.0.0` dengan konfirmasi dan notifikasi galat.
- Infolist menyatakan bahwa metrik adalah hasil validasi pelatihan bertahap,
  bukan jaminan akurasi pada informasi baru.
- Konfigurasi: `config/services.php` (`train_timeout`, `activate_timeout`,
  `max_train_csv_bytes`, `callback_url`), `.env.example`, `.env.docker.example`,
  `docker-compose.yml` (`BERT_CALLBACK_URL`, `BERT_TRAIN_TIMEOUT`, dst.).

## Verifikasi

Laravel dijalankan di image Docker `hoaxlin-app:test` dengan `--network none`
(host tidak memiliki `vendor/` maupun `pytest`).

| Pemeriksaan | Hasil |
| --- | --- |
| Tes tertarget Tahap 3 | 60 passed, 470 assertions |
| PHPUnit penuh (termasuk `ModelVersionTest` Tahap 1) | 832 passed, 5.955 assertions, 4 skipped (`RealBertInferenceTest`), 25 warnings |
| `vendor/bin/pint --test` | PASS, 306 file |
| `node --test tests/Frontend/*.test.mjs` | 69 passed |
| `docker compose config` | Valid |
| `php artisan route:list --path=api` | Rute callback terdaftar |
| CSS/HTML `.fi-resource-model-versions` | Dirender sesuai tema |
| `git diff --cached --check` | Lulus |
| `bert-service` pytest (`-m 'not release'`), dijalankan pada `f640675` | 157 passed, 22 deselected |

`bert-service/` tidak berubah sejak `f640675`, sehingga hasil pytest tersebut
masih berlaku untuk `51d4ae9`.

## Batas dan risiko tersisa

- Belum ada pelatihan nyata end-to-end terhadap artefak IndoBERT privat
  v1.0.0. Tes Python memakai model BERT kecil sintetis; empat tes real-BERT
  Laravel di-skip dan 22 tes `release` tidak dipilih. Hasilnya bukan validasi
  inference atau kualitas model sebenarnya.
- Metrik akurasi/F1 dihitung pada split validasi 20% dari dataset unggahan
  (atau fallback ke data latih untuk dataset kecil), tanpa benchmark independen.
  Tidak ada klaim kenaikan akurasi; versi baru perlu evaluasi terpisah sebelum
  diaktifkan di produksi. Tidak ada kalibrasi ulang: threshold dan temperature
  diwarisi dari model dasar.
- Stack Compose belum dijalankan (`up`) dan image `bert` belum di-build ulang;
  alur callback, volume bersama, dan izin berkas lintas kontainer hanya diuji
  melalui tes dan `docker compose config`.
- Volume `ai_models` dipasang di `/app/models`. Docker hanya mengisi volume
  bernama dari isi image saat pertama dibuat; pada deployment dengan volume lama,
  artefak baseline di image tidak otomatis disalin. Perlu dicek saat rollout.
- `/train` pada `bert-service` hanya menolak satu job paralel per proses; kontrol
  per-proses ini tidak berlaku bila service di-scale ke banyak replika.
- RAG tidak menegakkan bearer auth (kondisi yang sudah ada); callback dan
  `/train` bergantung pada isolasi jaringan Compose dan `BERT_SERVICE_TOKEN`.
- README/PRD belum diperbarui untuk fitur ini; tidak ada push, merge, atau deploy.
- Warning Laravel berasal dari parser `DOMDocument` pada tes progress yang sudah
  ada sebelumnya.

## Langkah berikutnya (belum dikerjakan pada komit `51d4ae9`)

1. Tinjauan manual UI `/admin` di browser desktop/mobile.
2. Uji alur penuh di staging dengan artefak v1.0.0 asli dan dataset kecil.
3. Evaluasi baseline vs versi baru pada set uji independen sebelum aktivasi.
4. Pembaruan README/PRD, lalu push/PR atas persetujuan.

## Pengamanan Staging & Verifikasi Alur Penuh Kontainer

Menindaklanjuti poin pengamanan staging sebelum pembaruan README/PRD:

1. **Fallback dan Auto-Seed Baseline `v1.0.0` pada Volume `ai_models` + Seeder Laravel**:
   - `bert-service/Dockerfile` dan `docker-compose.yml` menyertakan salinan fallback baseline di `/opt/hoaxlin/baseline/indobert-hoax/v1.0.0` (`BERT_BASELINE_FALLBACK_PATH`).
   - `ModelRuntime.ensure_baseline_available()` (`bert-service/app/inference.py`) menyalin baseline secara atomik ke `/app/models/indobert-hoax/v1.0.0` bila direktori target belum ada atau kosong pada volume `ai_models`, serta menggunakan fallback bila volume tidak dapat ditulisi. Direktori versi yang sudah berisi berkas korup/tidak lengkap tetap gagal tertutup (`fail-closed`).
   - `Database\Seeders\ModelVersionSeeder` (`database/seeders/ModelVersionSeeder.php`, dipanggil dari `DatabaseSeeder`) mengisi baris awal `v1.0.0` (`status=completed`, `total_rows=7816`, `accuracy=1.0`, `f1_score=1.0`, `baseline_accuracy=1.0`, `baseline_f1_score=1.0`, `is_active=true` bila belum ada versi aktif) secara idempoten agar langsung tampil di `/admin/model-versions`.
2. **Evaluasi Kandidat vs Baseline `v1.0.0` & Sampel CSV Siap Pakai**:
   - `bert-service/app/trainer.py` mengevaluasi model baseline `v1.0.0` dan model kandidat hasil fine-tuning pada split validasi yang sama dan deterministik, lalu mengirimkan `baseline_accuracy` dan `baseline_f1_score` melalui callback `/api/internal/bert/callback` dan menyimpannya di `evaluation.json`.
   - Migrasi `2026_11_08_000002_add_baseline_metrics_to_model_versions_table.php` menambahkan kolom `baseline_accuracy` dan `baseline_f1_score` pada `model_versions`.
   - Tabel dan halaman detail `/admin/model-versions` menampilkan kolom `Perbandingan vs Baseline` serta selisih (`delta`) akurasi dan Macro F1 terhadap baseline `v1.0.0` sebelum admin menekan `Aktifkan Model`.
   - Berkas sampel siap pakai disediakan di `datasets/samples/incremental-training-sample.csv` (24 baris seimbang: 12 `valid`, 12 `hoax`).
3. **Build Ulang Image & Verifikasi Kontainer End-to-End**:
   - Image `hoaxlin-app:test`, `hoaxlin-app:local`, dan `hoaxlin-bert:v1.0.0` telah dibangun ulang.
   - `bert-service` pytest (`-m "not release"`): **161 passed, 22 deselected**.
   - Laravel Pint (`vendor/bin/pint --test`): **PASS (308 files)**.
   - PHPUnit penuh (`hoaxlin-app:test`): **834 passed (5.978 assertions), 4 skipped**.
   - Verifikasi multi-kontainer (`mysql`, `redis`, `bert`, `app`, `queue` dengan volume `ai_models` yang sudah ada namun tanpa `v1.0.0`) membuktikan:
     - Auto-seed baseline `v1.0.0` ke volume `ai_models` dan kelulusan `runtime_smoke_test.py` (D1 equivalence `PASS`).
     - `DatabaseSeeder` membuat rekaman `v1.0.0` aktif (`Acuan baseline v1.0.0`).
     - Pelatihan asinkron `v1.1.0-staging` dengan `datasets/samples/incremental-training-sample.csv` selesai (`status=completed`, durasi ~10 detik di CPU) dengan metrik validasi: kandidat `accuracy=1.0000`, `f1_score=1.0000` vs baseline `baseline_accuracy=0.5000`, `baseline_f1_score=0.3333` (`Akurasi +50.00% (baseline 50.00%) · F1 +66.67% (baseline 33.33%)`), tanpa mengubah model aktif (`v1.0.0` tetap aktif).
     - Aktivasi eksplisit (`activateModelVersion`) memindahkan `/health/ready` dan `/predict` ke `v1.1.0-staging`, lalu rollback (`activateBaseline`) mengembalikan runtime dan status database ke `v1.0.0`.
