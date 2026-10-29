# Q-008 tambahan — Komdigi terbaru, bukan corpus training historis

**Audit otomatis snapshot terbaru selesai; Q-008 corpus historis tetap PARTIAL/BLOCKED.**
Branch `quality/03-bert-data`, base commit `2e76f63`. Owner menyatakan CSV ini
diunduh ulang dari Kaggle setelah author memperbaruinya. Slug/version Kaggle dan
waktu pengambilan belum diberikan; tidak ditebak. Tidak ada klaim reproduksi
IndoBERT v1.0.0 atau kesamaan record dengan corpus training lama.

## Identitas dan struktur aktual

- Path lokal: `datasets/komdigi_hoaks.csv` (ignored; tidak dimasukkan Git).
- SHA-256: `32fc99f809a39ab6528430fb2966577ffdfbc0f6c2f6533bb420add0fb9face7`.
- 11.436.440 bytes; **3.480 record CSV**, bukan jumlah baris fisik.
- UTF-8-sig dapat dibaca; 13 kolom memenuhi EXPECTED_COLUMNS adapter, tanpa
  kolom ekstra atau row ragged. ID sumber unik: 3.480.
- Rentang published_at yang diparse adapter: 2024-10-01 23:04:00 sampai
  2026-10-27 23:04:00; tidak mengklaim timezone yang tidak disimpan sumber.
- Category seluruh record: `Klarifikasi Hoaks`. Label `hoax` berasal adapter,
  **bukan anotasi kebenaran masing-masing klaim yang diverifikasi ulang**.

## Metode

`audit.py` menjalankan KomdigiCsvAdapter dan `clean_text`, `cut_claim`,
`is_indonesian`, `normalize_key` existing tanpa mengubahnya. Penjelasan reason
cut mereplikasi daftar marker untuk membedakan false flag, tetapi hasil teks
dan text_source diverifikasi langsung terhadap setiap output adapter. Tidak
menjalankan preparation pipeline yang menulis corpus/manifest atau split baru.

Tahap yang dibedakan: cleaned body → adapter full setelah link-counter pretrim
→ selected text → default quality checks sebelum dedup. Full pada adapter bukan
body mentah: link-counter pretrim terjadi pada **3.477/3.480** record.
Pemeriksaan panjang minimum 100 dan bahasa min_id_ratio 0,20 adalah baseline
QualityConfig, bukan konfigurasi training historis yang diketahui.

## Statistik aktual

Denominator seluruh hasil di bawah 3.480 emitted records kecuali dinyatakan lain.

| Pemeriksaan | Hasil |
| --- | --- |
| Adapter emitted / skipped | 3.480 / 0 |
| cut_claim true / selected claim | **3.393 (97,50%)** |
| cut_claim false / selected full fallback | **87 (2,50%)** |
| Fallback: marker pertama <120 | **86** (98,85% dari 87 fallback) |
| Fallback: tidak ada marker | **1** |
| Lolos default length/language sebelum dedup | 3.480 |
| Normalized selected duplicate | **8 grup, 16 anggota**, excess first-wins 8 |
| Normalized raw cleaned body duplicate | 6 grup |
| Selected verdict-phrase indicator | **86 (2,47%)**: 84 full, 2 claim |
| Selected source-marker indicator | **86** |
| Selected dateline/byline indicator | 0 terdeteksi oleh regex terbatas |
| Judul mengandung bracket hoaks/hoax | 3.479; judul **tidak** otomatis masuk selected text adapter |
| Kandidat quarantine unik (union reason) | **116 (3,33%)**, bukan penjumlahan reason |

Tidak ada empty body/title/ID, repeated source ID, missing/invalid date,
missing topics/URL, atau gagal quality heuristic yang terdeteksi pada run ini.
Nol hasil regex bukan bukti tidak ada pola lain atau cacat semantik.

Panjang **karakter Unicode, bukan token**; kuantil sorted floor((n−1)×p):

| Tahap | Min | Median | p90 | p95 | Maks | Rata-rata |
| --- | ---: | ---: | ---: | ---: | ---: | ---: |
| Raw cleaned body | 377 | 984 | 1.317 | 1.415 | 1.992 | 1.003,3325 |
| Adapter full setelah pretrim | 310 | 815 | 1.101 | 1.180 | 1.987 | 832,8359 |
| Selected text | 119 | 211 | 350 | 420 | 1.189 | 238,7026 |

`matrix.json` berisi **184 strata** label × source × kombinasi topic × bulan
× bucket panjang × text_source, berjumlah tepat 3.480. Semua label/source tunggal;
ini tidak dapat menggantikan perbandingan antarlabel/Antara.

## Potensi leakage: terukur vs belum terbukti

- Regex verdict sempit mendeteksi faktanya, frasa klaim/informasi + verdict,
  dan hasil/setelah dilakukan penelusuran. Tidak menghitung semua kata hoaks.
  Pada raw cleaned body dan adapter full: **3.442** indicator matches per record;
  setelah pemilihan klaim: **86**. Ini indikasi tekstual residu verdict, bukan
  precision/recall detector, evaluasi causal leakage atau keputusan label salah.
- **84/87 fallback** memuat indicator; **2/3.393 claim** juga masih memuatnya.
  Perlu review konteks quoted claim vs actual verdict. Cut success tidak otomatis
  berarti ekstraksi bersih; cut false tidak otomatis berarti label salah.
- Pretrim dan cut membuang teks cukup banyak. Tidak ada penilaian apakah entitas,
  negasi atau konteks klaim hilang; panjang saja tidak mengukur fidelity.
- Source label dan kategori seragam tidak memungkinkan uji source shortcut
  antarlabel. Hipotesis Q-013 sebelumnya tetap belum diuji.
- Duplicate = equality setelah normalize_key (case/punctuation/URL folding),
  bukan bukti semua pasangan identik secara semantik. Near-duplicate belum diukur;
  konflik antarlabel tidak assessable karena adapter memberi satu label implied.
  Tidak menjalankan first-wins sehingga semua pasangan tetap tercatat.

## Sampling dan quarantine (pending review)

`quarantine-candidates.jsonl` menyimpan seluruh **116 kandidat**: source record ID,
audit ID `komdigi:<id>@row-<ordinal>`, row ordinal CSV, hash selected text, panjang,
reason codes dan `pending_human_review`. Tanpa teks, judul, URL, preview, label baru
atau tindakan penghapusan. Reason mencakup fallback (87), verdict (86), source
marker (86), normalized duplicate (16); kategorinya overlap dan tidak sama severity.

`sampling.json`: seed `Q008-42`, SHA-256 `seed|audit_id` + tie-break ID, min(5,n)
per reason dan per 184 strata matriks. Audit ID menambahkan row ordinal agar
record tetap dapat dirujuk bila source ID berulang; source ID terpisah tersedia.
Sampling terikat hash snapshot ini, bukan stabil terhadap reorder CSV baru.
Sampel belum disetujui manusia; tidak diklaim sebagai estimasi prevalensi manual.

Contoh pointer sampel (tanpa preview):
- Early-marker fallback/verdict: `komdigi:59244@row-3015`, `komdigi:64714@row-438`,
  `komdigi:61030@row-2153`, `komdigi:60173@row-2550`, `komdigi:64172@row-681`.
- No-marker fallback: `komdigi:60724@row-2309`.
- Duplikat: pasangan lengkap ada di duplicates.json, misalnya
  `komdigi:60420@row-2440` dan `komdigi:60421@row-2439`.

## Masih BLOCKED / tidak dilakukan

1. Corpus asli v1.0.0, manifest/checksum training, processed splits dan dedup reports
   tetap tidak tersedia. Tidak membuat manifest historis baru atau menyesuaikan hash.
2. Antara belum tersedia; tidak crawling. Distribusi dua label/sumber, konflik
   antarlabel dan reproduksi training tetap BLOCKED.
3. Tidak ada historical split: overlap train/val/test dan semantic event leakage
   tidak dapat diukur. Near-duplicates dan anotasi claim correctness belum dilakukan.
4. Kandidat quarantine memerlukan review manusia dan bukti klaim; tidak ada
   automatic relabel/exclusion, training, inference model, atau perubahan release.
5. Tidak menguji performa classifier atau membuktikan sebab kesalahan model.

## Reproduksi dan validasi

```bash
bash reports/ai-quality/20261029-q008/komdigi-latest/run-audit.sh new-run-name
bash reports/ai-quality/20261029-q002/run-tests.sh tests/test_komdigi_audit.py tests/test_normalize.py tests/test_reconcile.py tests/test_prepare.py tests/test_dedup.py tests/test_split.py
```

Output baru dibuat di `/tmp/opencode/q008-latest/<name>`; menolak overwrite.
Dua run final menghasilkan **semua enam output byte-identik**, termasuk metadata
3.480 records (records.jsonl disimpan lokal, tidak perlu diduplikasi Git).
Lima output ringkas/ID-list disalin ke direktori laporan ini. Checksum artefak
dan validasi invariants disimpan terpisah; bukan manifest dataset training.

Targeted **42 passed (0,75s)**. Suite Python penuh: **153 passed, 5 failed,
2 warnings (27,54s)**; lima baseline test_api.py masih mengharapkan path Windows
model yang tidak ada: readiness_200_when_ready, version_exposes_labels_and_threshold,
predict_happy_path_hoax_and_valid, predict_threshold_meragukan,
request_id_propagation (semua dengan prefix test_). Tidak memperbaiki/melemahkan
tes tersebut. Tidak ada PHP/UI/model changes sehingga Pint/build/browser tidak
dijalankan. Snapshot CSV dan bukti lama diverifikasi tidak berubah.
