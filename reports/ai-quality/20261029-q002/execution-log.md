# Q-002 — Integritas challenge lintas platform

**Status: DONE.** Dependency Q-001 DONE, commit `6979509`.
Branch `quality/01-baseline`. Tidak ada eksperimen kualitas, training, threshold
search, inference release, tuning atau final holdout yang dijalankan.

## Kebijakan dan migrasi provenance

Policy baru `csv-utf8-bom-lf-v1`: UTF-8 BOM, delimiter koma, quote `"`, escaping
double-quote, `QUOTE_MINIMAL`, record separator LF, final LF, kolom dan urutan
record tetap. Nilai field dipertahankan tepat: tanpa trim, casefold, normalisasi
Unicode, penghapusan negasi, atau normalisasi newline di dalam field.
Builder candidate baru menulis format tersebut secara eksplisit, termasuk di
Windows (`newline=''`, `lineterminator='\n'`). Kandidat tetap pending sesuai
mekanisme review existing; test fixture bukan data benchmark baru.

Manifest frozen v1 diperluas secara additive melalui `file.integrity`:

- `file.sha256`, `file.encoding`, dan `file.newline` lama **tetap metadata snapshot
  historis CRLF**, bukan klaim bytes fresh checkout sekarang.
- `raw_sha256`: bytes canonical LF yang dilacak Git;
  `d9237395948dc42cbcfec328a4f2a74bbff73d66537bced27ef4746b76c32f7b`.
- `canonical_sha256`: hash CSV yang diparse lalu diserialisasi dengan policy di
  atas; sama dengan hash LF karena checkout sudah canonical. Ini hash serialisasi
  records, bukan hash yang mengabaikan perubahan nilai/urutan.
- `historical_raw_sha256`: bytes snapshot CRLF;
  `8ff83e37e7db10b0ab231618c6834d45325a9aacb2676ae0fcd2069b30e51708`.

Validator hanya menerima dua bentuk bytes eksak yang direkonstruksi dari records:
canonical LF atau historis CRLF. **Kedua hash hasil rekonstruksi harus cocok dengan
manifest**, termasuk hash historis top-level. Unknown/malformed policy ditolak.
BOM hilang, mixed separators, blank line tambahan, field/ID/label/order/count
berubah ditolak. Manifest legacy tanpa `integrity` tetap strict raw-byte checksum;
tidak ada fallback yang menerima sembarang hash atau checksum yang dinonaktifkan.

`FrozenDataset.sha256` sengaja menjadi legacy manifest identity untuk consumer
historis error-analysis/TF-IDF comparison. `raw_sha256` mencatat bytes yang benar-
benar dibaca dan `canonical_sha256` mencatat canonical identity. Output baru
evaluator BERT dan baseline memberi kedua field tersebut plus `sha256_role` agar
hash legacy tidak disalahartikan sebagai checksum bytes aktual. Laporan historis
tidak ditulis ulang, timestamp/metric tidak diganti, versi dataset tetap 1.0.0.

`verification.json` membuktikan 120 record dan semua field dalam urutan yang sama
dengan bytes Q-001 dan rekonstruksi CRLF. Hash rekonstruksi cocok dengan hash
historis; ini bukan bukti memiliki file CRLF original terpisah dari era training.
CSV frozen tidak berubah satu byte pun. Manifest migrasi bukan benchmark baru.

## File berubah

- `.gitattributes`: aturan LF eksplisit untuk CSV challenge.
- `datasets/challenge/external-challenge-v1/manifest.json`: metadata integrity
  additive; hash historis dan policy evaluation-only dipertahankan.
- `bert-service/scripts/evaluate_external_challenge.py`: strict migration verifier,
  identitas raw/canonical/legacy, metadata laporan baru.
- `bert-service/scripts/evaluate_tfidf_logreg_baseline.py`: bawa raw/canonical
  identity sampai laporan, tanpa mengubah logika fit/prediction/comparison.
- `bert-service/scripts/build_external_challenge.py`: serializer candidate BOM/LF.
- `bert-service/tests/test_external_challenge_evaluator.py`: LF/CRLF, perubahan
  record/order/count, policy/hash rusak, serialisasi tak diizinkan, legacy strict,
  validasi checkout Linux asli.
- `bert-service/tests/test_external_challenge_builder.py`: byte output candidate
  deterministic termasuk quoted comma/quote dan multiline text.
- `bert-service/tests/test_tfidf_logreg_baseline.py`: identitas comparison historis
  dan raw/canonical metadata output baru.
- Direktori laporan ini: log, runner, verifier, verification JSON dan environment.

Dua file untracked awal pengguna dipertahankan; hash diperiksa terhadap Q-001.
Tidak mengubah API/model/RAG runtime, UI, dependency repo, atau lockfile.

## Command dan hasil

Semua command dari root repo kecuali workdir container yang ditentukan runner.

1. `git status --short --branch`: awal hanya dua file untracked pengguna.
2. Python stdlib `csv.reader`, checksum LF dan rekonstruksi CRLF: 120 record,
   tanpa embedded newline; kedua hash cocok bukti audit/Q-001. Tidak mencetak teks.
3. Setup dependency test sementara (bukan instalasi global/image atau upgrade repo):

   ```bash
   mkdir -p /tmp/opencode/q002-deps /tmp/opencode/q002-evidence
   docker run --rm --pull=never --network bridge --cap-drop ALL \
     --security-opt no-new-privileges --memory 2g --cpus 2 \
     --user "$(id -u):$(id -g)" -v /tmp/opencode/q002-deps:/test-deps \
     --entrypoint python hoaxlin-bert:v1.0.0 -m pip install --no-cache-dir \
     --target /test-deps 'pytest>=8,<9' 'scikit-learn==1.8.0' \
     'matplotlib>=3.8,<4' 'typer>=0.12,<1' 'beautifulsoup4>=4.12,<5'
   ```

   Exit 0. Percobaan pertama `--user 0:0` exit 2 pada penyalinan target mount
   (`PermissionError`); retry memakai UID/GID host berhasil. Tidak mengubah izin
   filesystem, tidak mengunduh model. Unduhan wheel sekitar 100 MiB, resource
   maksimal 2 CPU/2 GiB; peak RSS tidak diukur. Dependency efektif di environment
   tersimpan, termasuk sklearn 1.8.0 dan pytest 8.4.2.
4. Semua test memakai image ID
   `sha256:046ca71ead3af0bbb8a6be04e109f41b86dc29e1314eb19f899f46c53cafd688`,
   source checkout mount read-only (bukan serving source image yang drift), model
   tidak di-load untuk test Q-002. Runner `run-tests.sh` memuat command lengkap.
5. **RED**, sebelum implementasi:
   `pytest -p no:cacheprovider tests/test_external_challenge_evaluator.py tests/test_external_challenge_builder.py`:
   exit 1, **8 gagal / 13 lulus**. Bug nyata: LF ditolak, policy rusak diabaikan,
   fresh checkout ditolak, serializer belum canonical. CRLF juga menunjukkan field
   identitas baru belum tersedia. Log sementara: `/tmp/opencode/q002-evidence/red.log`.
6. **GREEN awal**, evaluator/builder/baseline/error-analysis: **32 lulus** (3,43 s).
7. Tambah integrity edge cases dan baseline compatibility; **RED report identity**:
   test baseline `-k lf_checkout` exit 1, `KeyError: raw_sha256`. Implementasi
   meneruskan identity ke report kemudian ditambahkan.
8. **GREEN final**:

   ```bash
   bash reports/ai-quality/20261029-q002/run-tests.sh \
     tests/test_external_challenge_evaluator.py tests/test_external_challenge_builder.py \
     tests/test_tfidf_logreg_baseline.py tests/test_external_challenge_error_analysis.py
   ```

   **36 lulus**, exit 0, 3,24 s; rerun melalui runner tersimpan **36 lulus dalam
   3,19 s**. Tidak ada warnings pada run targeted. Ringkasan tersimpan di
   `test-results.json`; dependency efektif pada `environment.json`.
9. **Suite BERT penuh** (`bash reports/ai-quality/20261029-q002/run-tests.sh`):
   exit 1, **105 lulus / 5 gagal / 2 warnings**, 12,61 s; tidak ada skip.
   Suite telah dijalankan dua kali dengan failure set yang sama. Semua gagal di
   `tests/test_api.py` karena fixture model path Windows hardcoded tidak ada di
   Linux, sehingga readiness/predict 503; tidak diubah/dilemahkan dalam Q-002:
   - `test_readiness_200_when_ready`
   - `test_version_exposes_labels_and_threshold`
   - `test_predict_happy_path_hoax_and_valid`
   - `test_predict_threshold_meragukan`
   - `test_request_id_propagation`

   Warnings: Starlette deprecation `httpx` TestClient dan anyio BlockingPortal alias.
   Tidak meng-upgrade dependency untuk menutupi warning. Log sementara tersedia
   `/tmp/opencode/q002-evidence/full-final.log`.
10. Validasi fresh Linux checkout langsung dengan Python host stdlib:
    `PYTHONDONTWRITEBYTECODE=1 PYTHONPATH=bert-service python3 ...validate_frozen_dataset(...)`:
    exit 0, 120 record, raw/canonical/legacy hash sesuai. Tidak memerlukan ML deps.
11. Bukti file/provenance:

    ```bash
    python3 -B reports/ai-quality/20261029-q002/verify_migration.py > reports/ai-quality/20261029-q002/verification.json
    git diff --check
    ```

    Verifier awal exit 1 karena masih memasukkan `.gitattributes` dalam expected
    unchanged files. Daftar pengecualian diperbaiki hanya untuk dua file Q-001
    yang memang berubah di Q-002 (manifest dan `.gitattributes`), lalu exit 0.
    Semua 47 file Q-001 selain manifest
    dan `.gitattributes` yang sengaja dimigrasi diperiksa tetap identik, termasuk semua hasil historis dan
    file pengguna. Tidak ada klaim seluruh test suite hijau.

## Artefak dan batas kesimpulan

- Source dasar Q-002 `6979509` (full SHA pada verification JSON).
- BERT/embedding/corpus tidak diganti; hash release tetap tercatat Q-001. Tidak
  memerlukan reviewer/data training baru untuk task integritas ini.
- Model/tokenizer upstream revision, calibration/threshold: tidak berubah.
- Seed: N/A untuk checksum; baseline unit fixtures memakai seed existing 42,
  bukan training model produk. Sample inference benchmark baru: **0**.
- Kualitas accuracy/F1/relevance: **tidak diukur dan tidak diklaim meningkat**.
- Q-002 DONE terbatas integritas; full-suite API verification masih bermasalah
  karena fixture path. Q-003 berikutnya eligible dan harus memakai identitas source
  serta runtime yang benar; corpus training/adapter cold-warm dapat tetap BLOCKED
  sesuai ketersediaan, tanpa melompati dependency.
- Tidak ada merge/push/PR/deploy atau perubahan stack aktif.
