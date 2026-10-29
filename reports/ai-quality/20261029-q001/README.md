# Q-001 — Checkout dan inventaris artefak aktual

**Status: DONE (inventory, bukan baseline kualitas inference).**

Run: `20261029-q001`; observasi 29 Oktober 2026. Source HEAD dan commit audit:
`3f17a203d338cec837b48e4ce24f48162f2457ec`, branch `quality/01-baseline`.
Tidak ada perubahan tracked terhadap commit audit saat mulai. File untracked
pengguna `HOAXLIN-BERT-RAG-AUDIT-TASKS.md` dan
`tests/Feature/FilamentAuditObserverTest.php` dipertahankan byte-for-byte;
hash awal tersimpan di `inventory.json`. Status pada dokumen task pengguna
tidak ditulis ulang; execution log ini menjadi status run Q-001.

## Bukti dan batasnya

- **Observed**: diperiksa dari filesystem, metadata Docker, payload image,
  validator existing atau probe localhost pada run ini.
- **Inferred**: implikasi konfigurasi/ketersediaan, bukan pengukuran runtime.
- **Unavailable**: tidak tersedia pada lokasi/konteks yang diperiksa; bukan
  pernyataan bahwa artefak tidak ada di semua mesin atau penyimpanan owner.
- Tidak mengakses database, storage submission, API berbayar, atau endpoint
  eksternal. Tidak ada inference, training, calibration, tuning, review label
  baru, atau pembukaan final holdout. Seed, metric, denominator evaluasi dan
  latency inference: **N/A**, ukuran sampel inference baru **0**.

`inventory.json` menyimpan timestamp, command lengkap beserta stdin inspeksi,
exit code, durasi, versi dependency, ukuran/hash file, manifest dan perbandingan
source. Tidak memuat isi CSV, raw input pengguna, secret/token, atau bobot.

## 1. Source dan environment

| Pemeriksaan | Status | Hasil |
| --- | --- | --- |
| HEAD vs audit | Observed | Sama; bukan source implementasi kualitas baru |
| Instruksi | Observed | `AGENTS.md` root; tidak ditemukan AGENTS bersarang |
| Host | Observed | Linux x86_64, Python 3.14.8, PHP 8.5.12, Node 26.12.1, npm 12.2.1; 4 logical CPU, RAM total 8.002.676 KiB |
| ML host | Unavailable | torch, transformers, tokenizers, sentence-transformers, FastAPI, uvicorn, pytest, numpy, scikit-learn tidak terpasang pada Python host; tidak ada venv repo |
| Laravel host | Unavailable | `vendor/` tidak ada; jangan mengasumsikan `php artisan test` dapat dijalankan di host |
| Frontend | Observed | `node_modules/` ada, tidak disentuh karena di luar scope |
| Compose project | Observed | `docker compose ps --all --format json`: exit 0, tanpa container |
| Runtime live | Unavailable | GET loopback port 8001/8002, `/version` dan `/health/ready`: connection refused (4 probe) |
| GPU | Unavailable | `nvidia-smi` tidak ditemukan; tidak melakukan discovery/benchmark GPU lebih jauh |

Konfigurasi lokal diambil hanya dari allowlist `.env` dan process environment;
tidak melakukan dump environment, Compose config terinterpolasi, atau token.
`bootstrap/cache/config.php` tidak ada. Konfigurasi tersebut **bukan config live**:
BERT URL `http://bert:8001`, versi `v1.0.0`, threshold Laravel `0.99`;
RAG URL `http://rag:8002`, Top-K `3`, min-score kosong. Nama host Compose ini
bukan endpoint host yang bisa dianggap sedang berjalan.

Default source tetap berbeda menurut konteks: Laravel `config/services.php`
threshold `0.65`, Compose `0.99`; BERT default serving sequence `512` dan
20.000 karakter; training default sequence `256`, seed `42`. Nilai ini hanya
observasi konfigurasi, **bukan bukti panjang token training release aktual atau
efek kualitasnya**. Tidak ada override/policy yang diubah pada Q-001.

## 2. BERT release dan tokenizer

**Observed:** bobot tidak ada pada `models/` atau `artifacts/` checkout, tetapi
tersedia di image lokal. Jangan menandai inference terblokir karena bobot hilang.

| Identitas | Nilai observed |
| --- | --- |
| Image | `hoaxlin-bert:v1.0.0` |
| Image ID / local RepoDigest suffix | `sha256:046ca71ead3af0bbb8a6be04e109f41b86dc29e1314eb19f899f46c53cafd688` |
| Release dalam image | `/app/models/indobert-hoax/v1.0.0` |
| Manifest SHA-256 | `25d740d8f06dfc9b7727e623f9bddb9de6be5e37b86d3c11a19a647cd67ccd72` |
| Bobot SHA-256 | `0fea71d7d5fd18c61f4e854d1059bb19ce62e3ec3f573718166417e18f2a1e40` |
| Tokenizer SHA-256 | `da8791ba8d7a1459108876b0a0e105c5df39929cc8e7826499f63e74239b0181` |
| Calibration SHA-256 | `47bbeee876f89a2d75f8641c19b8743ed023908df4b18b74b31ad4801e1be21e` |
| Threshold SHA-256 | `3e16e8f0839721c8e59fccc4e497c27a80d9e04edd2d8e92e79974f91ef2591a` |
| Contract | `0=valid`, `1=hoax`; threshold `0.99`; temperature `0.8706620666110391` |
| Validator existing | `app.model_release.validate_release`: lulus; 8 payload files, 498.522.007 byte; hash frozen cocok |
| Dependency image | Python 3.12.12; torch 2.13.0+cpu; transformers 5.14.1; tokenizers 0.22.2; FastAPI 0.136.3; uvicorn 0.52.0 |

Manifest mengklaim base model `indobenchmark/indobert-base-p1`, checkpoint
`checkpoint-391`, export `2026-08-21T01:03:52.509812+00:00`, source manifest hash
`1ca6b737fbf4a6e541e0e57b083330bf9495d5114c0a456f671e5c6776fe440c`.
Model card menyebut dataset `komdigi-antara v1.0.0`. Ini **observasi metadata**,
bukan verifikasi ulang dataset/checkpoint. Base-model/tokenizer upstream revision,
preprocessing version dan kontrak sequence training yang immutable belum tercantum.
Tokenizer config berisi sentinel `model_max_length` sangat besar; itu bukan bukti
bahwa serving menerima sequence sebesar itu.

**Drift source image vs HEAD:** `app/contracts.py`, `app/inference.py`, dan
`app/main.py` berbeda hash. `app/config.py` dan `app/model_release.py` cocok.
Per-file hash keduanya tersimpan di inventory. Tidak ada klaim image dibangun dari
HEAD. Q-003 harus memisahkan baseline image lama dari serving source saat ini,
misalnya memakai source checkout read-only dengan release image yang sama.

`evaluation.json` internal dan threshold-search summary dalam image adalah
**hasil historis**, bukan inference baru: test internal n=781, calibration/threshold
summary n=782. Public redistribution tetap `not_verified`; tidak ada image/bobot
yang dipublish atau dimasukkan Git.

## 3. Dataset dan prediction files

| Artefak | Status | Hasil |
| --- | --- | --- |
| `datasets/komdigi_hoaks.csv`, `datasets/valid_antara.csv` | Unavailable | Tidak ada di checkout |
| `datasets/processed/`, `models/runs` | Unavailable | Data train/val/calibration/test aktual dan checkpoint run tidak ada |
| Manifest dataset training | Unavailable | Hanya referensi hash source manifest pada release; tidak bisa mengaudit row/label/split dari referensi itu |
| `datasets/challenge/external-challenge-v1/challenge.csv` | Observed | 120 record; raw SHA-256 LF `d9237395948dc42cbcfec328a4f2a74bbff73d66537bced27ef4746b76c32f7b` |
| Manifest challenge | Observed | Mencatat 120 record, 60 per kelas, raw hash CRLF `8ff83e37e7db10b0ab231618c6834d45325a9aacb2676ae0fcd2069b30e51708`; **mismatch raw bytes** dengan checkout, Q-002 diperlukan |
| `results/predictions.csv` | Observed | 120 record; SHA-256 `1d85ee56eb217b36a9d3e39bdd7e946797be185777744cd70aee9239cee0bf2c` |
| `results/baseline_external_predictions.csv` | Observed | 120 record; SHA-256 `6afbdf854b99e18467be900f19b019c5ce6c3aa03859e59201a563fcb2100e4f` |
| `results/baseline_internal_predictions.csv` | Observed | 781 record; SHA-256 `c2e481e07e7335d0375048da9e015f9157151838c7b42068298baffd7d489ee4` |

Semua file dataset yang ditemukan, manifest KB lintas versi, laporan historis,
prediction files dan checksum-nya tercatat dalam `inventory.json` (49 file).
Record hanya dihitung, tidak dimuat ke laporan. Tidak melakukan perhitungan ulang
metric Q-003 atau menyamakan manifest review historis dengan approval reviewer baru.
Challenge v1 tetap evaluation-only, bukan final blind holdout baru atau tuning set.

## 4. RAG corpus dan embedding

| Identitas | Nilai observed |
| --- | --- |
| Image | `hoaxlin-rag:local` |
| Image ID / local RepoDigest suffix | `sha256:826666a6ff0ee45de6251b250a7d3e368d2813710d724ecac584b58cf548a98b` |
| KB yang dipilih source dan payload image | `knowledge-base-v1.1.1`, versi `1.1.1`, 61 dokumen |
| Documents SHA-256 | `c937f0ac221b3d6fd5ce829a969c0712832e1c873959ed531ff8bc363366877d` |
| Validator existing | Host CLI dan loader image lulus; bukan validasi semantic relevance |
| Embedding | `sentence-transformers/paraphrase-multilingual-MiniLM-L12-v2` |
| Revision snapshot tersedia di image | `e8f8c211226b894fcb81acc59f3b34ba3efd5f42` |
| Embedding weights SHA-256 | `eaa086f0ffee582aeb45b36e34cdd1fe2d6de2bef61f8a559a1bbc9bd955917b` |
| Embedding tokenizer SHA-256 | `2c3387be76557bd40970cec13153b3bbf80407865484b209e655e5e4729076b8` |
| Sequence config snapshot | `max_seq_length=128`, `do_lower_case=false`; tidak diukur dengan encoding baru |
| Dependency image | Python 3.12.12; torch 2.13.0+cpu; transformers 5.18.0; tokenizers 0.23.2; sentence-transformers 5.7.0; numpy 2.5.3; scikit-learn 1.9.1 |
| Source image | Seluruh 7 file `app/*.py` yang diperiksa cocok dengan checkout |
| Query manifest v1 | 20 query; SHA-256 `0010336a9a3c1060cf0d12e5ddf6772448d74a46960eb049098378cffa3bc0de` |
| Judgment scope manifest | KB `1.0.0`, documents SHA-256 `1f275303f523be70c5f507b199eeb418f06b7d85a8f1dba449537b386a3ef1e2`, **bukan KB 1.1.1** |

Cache snapshot ada di `/opt/huggingface/hub/` dalam image; hash seluruh payload
snapshot disimpan, bukan file cache/log yang tidak terkait. Cache HF host tidak
ditemukan pada lokasi default. **Inferred:** evaluasi RAG offline berikutnya
memungkinkan tanpa download baru; keberhasilan load/inference belum diuji.

Image aplikasi lokal tersedia untuk tahap adapter/test, tetapi identitas source
dan runtime aplikasi belum diverifikasi: `hoaxlin-app:local` ID
`sha256:35a8f57a7eff53ab6073d6b7642bceef30ee4dea87e8d8c1ef016a943d106dc7`;
`hoaxlin-app:test-rc006` ID
`sha256:7a9c88f9b9345037ef841e4366bc31abe57b7ef29bdd449b9f7f599bfd9e7eb3`.
Ketersediaan tag bukan bukti test berjalan pada HEAD.

## 5. Dependency dan blocker konkret

| Task berikut | Status dependency / blocker | Langkah yang sah |
| --- | --- | --- |
| Q-002 | **READY** setelah Q-001; checksum challenge belum diperbaiki | Reproduksi LF/CRLF dan perbaikan integritas dengan test existing; jangan melewati checksum |
| Q-003 | **WAITING Q-002**; bobot dan embedding tersedia; live service belum tersedia; source BERT image drift | Validasi integritas dahulu, lalu run terisolasi dengan identitas source/release jelas; cold/warm adapter perlu environment test terpisah |
| Q-004 | **WAITING Q-002 dan bukti Q-003 yang tersedia** | Protokol dapat memakai bukti historis berlabel bila ada bagian inference BLOCKED |
| Q-008 | **Artefak belum tersedia**, di samping dependency Q-007 | Perlu raw CSV Komdigi/ANTARA, processed JSONL, split/dedup report dan manifest training aktual yang diotorisasi; prediction internal bukan pengganti corpus |
| Q-010/Q-011 | **Data/reviewer belum tersedia**, dependency sebelumnya belum selesai | Perlu akses acquisition dan reviewer manusia beserta review/provenance nyata; tidak mengarang approval/label |
| Q-014/Q-015/Q-016 | **Belum eligible** | Menunggu dataset/split sah, protokol dan run-config; base training checkpoint/revision dan resource budget belum diverifikasi; jangan training dari release inference sebagai jalan pintas |
| Q-018/Q-019 | **Reviewer/judgment baru belum tersedia**, dependency sebelumnya belum selesai | Review independen corpus-aligned dan no-answer; judgment v1 tidak mencakup KB aktif |

Q-001 sendiri tidak BLOCKED: ketidaktersediaan telah dicatat sesuai acceptance
inventory. Tidak ada downstream task yang dianggap DONE. Sesi ini dibatasi pada
satu task Q-001; Q-002 adalah task aktif berikutnya yang dependency-nya terpenuhi.

## 6. Reproduksi dan resource

Jalankan dari root repository, dengan image lokal yang sudah tersedia:

```bash
python3 -B reports/ai-quality/20261029-q001/collect_inventory.py > /tmp/opencode/q001-inventory-rerun.json
```

Collector menggunakan image ID immutable, `--pull=never --network none
--read-only --cap-drop ALL --security-opt no-new-privileges`, maksimal 1 CPU dan
512 MiB per container, serial. Hash bobot dihitung streaming; tidak memuat model.
Tidak membuat stack, port mapping, volume, migration, API request inference atau
download. Run inventory tersimpan memerlukan sekitar 10 detik wall time; peak
RSS tidak diukur. Rerun disimpan terpisah agar snapshot awal tidak tertimpa.

Lihat `execution-log.md` untuk command, hasil validasi dan daftar file berubah.
