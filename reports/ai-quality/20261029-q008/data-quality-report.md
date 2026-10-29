# Q-008 — Audit corpus training dan shortcut sumber

**Status: BLOCKED — hanya review kode dan pemeriksaan ketersediaan artefak.**
Tanggal: 2026-10-29. Branch `quality/03-bert-data` sudah aktif saat pekerjaan dimulai.
HEAD: `32e59a5328f958efec17f1b16f39e20d74584f5e`.
Dependency Q-007 telah di-commit; blocker data Q-003 belum teratasi.

## 1. Bukti ketersediaan dan batas audit

Pemeriksaan filesystem checkout aktual (termasuk file ignored lewat `find`/Path.exists):

| Artefak | Ditemukan |
| --- | --- |
| `datasets/komdigi_hoaks.csv` | Tidak |
| `datasets/valid_antara.csv` | Tidak |
| `datasets/processed/komdigi-antara-v1/{manifest.json,all.jsonl,train.jsonl,val.jsonl,test.jsonl,rejected.jsonl}` | Tidak |
| `models/runs` | Tidak |
| Manifest dan keluaran dedup/split corpus training di area datasets/bert-service | Tidak ditemukan |

`find` awal juga melaporkan direktori root `models` tidak ada (exit 1); pemeriksaan
Path.exists per path mengonfirmasi absennya, bukan menganggap error sebagai data kosong.
Riwayat `git log -1 -- ...` pada path CSV/processed tidak menghasilkan commit.
Ini bukan bukti bahwa salinan tidak ada di komputer lain, backup atau storage owner.
Tidak mencari filesystem pribadi, volume DB produksi atau secret. Tidak mengunduh
ulang berita untuk menggantikan corpus historis.

Challenge historis dan KB RAG tersedia, tetapi **bukan corpus training pengganti**.
Tidak melakukan statistik training pada challenge, tidak tuning dan tidak memberi
status blind holdout pada challenge yang hasilnya sudah diketahui. Snapshot Q-003
dipakai sebagai konteks blocker, bukan bukti statistik corpus baru.

## 2. Matriks dan sampling yang belum dapat diukur

Unit yang diperlukan: record sumber sebelum rekonsiliasi, record canonical, dan
keanggotaan split; join melalui source/source_id/record_id serta manifest hash.

| Keluaran wajib | Hasil saat ini |
| --- | --- |
| Label × sumber × topik × tanggal × panjang × text_source | **null / NOT_MEASURED**, bukan matriks nol |
| Proporsi `cut_claim` gagal dan fallback full | **null / NOT_MEASURED**; denominator sumber belum ada |
| Verdict, dateline, author/source marker tersisa | Belum ada sampel record yang ditinjau |
| Duplikat, label konflik, overlap split | Belum diukur; tidak menyimpulkan bersih dari kode splitter |
| Perbandingan panjang/gaya klaim hoax vs artikel valid | Hipotesis kode saja, belum statistik corpus |
| Quarantine record aktual | Belum dapat dibuat; lihat quarantine.json |

Tidak ada ID bermasalah yang direkayasa dan tidak ada approval manusia fiktif.

## 3. Risiko yang benar-benar terlihat di kode

Nomor baris merujuk HEAD di atas; checksum sumber ada di verification.json.

| ID | Lokasi | Observasi kode | Batas kesimpulan |
| --- | --- | --- | --- |
| R1 | `bert-service/dataset/sources/komdigi_csv.py:103–125` | Semua row diberi raw_label hoax; jika cut false, full text dipakai dan text_source=full. Docstring :35–39 menyatakan verdict tidak pernah bocor, lebih kuat daripada guard aktual | Kategori klarifikasi bukan bukti semua kalimat salah; jumlah label salah/leakage belum diketahui |
| R2 | `bert-service/dataset/normalize.py:111–148` | Cut memakai substring marker pertama; marker tidak ditemukan atau posisi <120 menghasilkan cleaned full text, false. Marker evidensial seperti dilansir/dikutip juga dapat muncul dalam konteks klaim | Bisa menyisakan verdict atau memotong konteks sah; tidak boleh menghapus negasi/hoaks global sebagai perbaikan |
| R3 | `bert-service/scripts/build_valid_news.py:207–227` | text = title + body; label valid ditulis tanpa review klaim individual. `_extract_body:132–164` menghapus sebagian footer tetapi tidak menghapus dateline/source dalam paragraf | Berita tepercaya bisa mengutip hoaks/opini; source label/gaya/panjang dapat berkorelasi dengan kelas, belum kausal |
| R4 | `bert-service/scripts/build_valid_news.py:36–47,85–111,167–177,225` | Discovery kategori berurutan dan dibatasi jumlah URL; fetch tidak menyimpan kategori sehingga writer umumnya menulis kosong | Distribusi topik tidak otomatis seimbang; frekuensi kategori hilang belum diukur |
| R5 | `bert-service/dataset/sources/labeled_csv.py:62–82`; `schema.py:55` | Adapter generik membersihkan text, default text_source full; topics selalu []; tidak meneruskan topics opsional ke canonical | Topik missing bukan topik tertentu, dan full pada sumber valid tidak berarti cut_claim gagal |
| R6 | `bert-service/dataset/dedup.py:25–37`; `reconcile.py:121–139` | Exact dedup normalized text, first occurrence wins tanpa membandingkan label. Detail duplicate memakai zip daftar removed dengan daftar kept (bukan mapping survivor per key) | Konflik dapat tersembunyi dan pointer detail dapat keliru; jumlah/ID konflik belum diketahui. Perlu join ulang dari raw, bukan mempercayai pointer rejected saja |
| R7 | `bert-service/dataset/split.py:125–148` | Split berdasar claim_group dari clustering kemiripan, bukan anotasi event; seed tercatat tetapi group-mode assignment disortir deterministik | Group disjoint tidak menjamin event disjoint atau bebas semantic leakage; perlu audit record aktual |
| R8 | `bert-service/dataset/manifest.py:80–85`; `prepare.py:211–214` | Teks metodologi menyiratkan verdict dikeluarkan; penjelasan ambang marker pada manifest tidak sesuai guard aktual (cut hanya jika posisi >=120) | Manifest prose bukan pengukuran keberhasilan ekstraksi |

Audit ini tidak mengubah collector, normalizer, label, splitter, threshold atau model.
Risiko tersebut bukan temuan record aktual dan bukan pembuktian bahwa shortcut
menyebabkan performa eksternal buruk.

## 4. Protokol audit ketika data diberikan

1. Dapatkan salinan berizin read-only kedua CSV asli, manifest prep, all/rejected
   dan ketiga split serta laporan dedup/split yang digunakan release. Cocokkan
   SHA-256 input/output dengan manifest; bila beda, audit sebagai versi berbeda.
2. Buat matriks sparse dengan label, source, topic (daftar diurutkan sebagai satu
   kombinasi, missing eksplisit), bulan publikasi (missing/invalid terpisah),
   panjang Unicode karakter (`<100`, `100–255`, `256–999`, `1000–3999`, `>=4000`),
   dan text_source. Ini bucket **karakter**, bukan tokenizer. Catat total per tahap
   ingested/accepted/rejected, jangan menjumlahkan multi-topic seolah row unik.
3. Sampling deterministik: versi `q008-sampling-v1`, seed string `Q008-42`;
   dalam setiap stratum urutkan SHA-256 UTF-8 `Q008-42|<record_id>` lalu record_id,
   pilih min(5,n). Dedup ID, simpan sampling manifest dengan input hashes dan
   seluruh stratum counts. Sampling belum dijalankan; 5/stratum bukan gate kualitas.
4. Review teks di workspace berizin (bukan log publik): sisa verdict, quoted claim
   vs denial, dateline/byline/sumber, negasi/entitas/angka/tanggal/satuan yang hilang.
   Laporan hanya ID, reason code, reviewer/status dan pointer artefak, tanpa preview.
   Semua flagged record tetap pending review manusia; jangan relabel otomatis.
5. Jalankan ulang cut pada raw Komdigi dengan versi kode frozen yang benar,
   termasuk pre-cut link-counter adapter. Laporkan jumlah cut true/false per
   reason (no marker / earliest marker <120), denominator nonempty eligible
   Komdigi; hitung terpisah skipped empty title/text dan yang lolos quality gates.
   `with_full_text` gabungan semua sumber tidak cukup untuk fallback rate.
6. Kelompokkan normalized keys **sebelum** first-wins dedup, bandingkan label dan
   source; tinjau near-duplicate/event groups lintas split, verifikasi rejected
   pointer dari key asli. Normalized match tidak otomatis semantic equivalence.
7. Bandingkan distribusi representasi per label/source dengan konteks missingness,
   tanggal/topik dan panjang; laporkan asosiasi/deskriptif dahulu. Jangan mengubah
   split historis, tuning pada challenge, atau menyatakan mekanisme kausal.

## 5. Hipotesis terdaftar untuk Q-013 (belum diuji)

| Hipotesis | Uji yang direncanakan pada dev berizin | Risiko kontrol |
| --- | --- | --- |
| H1 source/dateline/byline memberi shortcut | Probe source-only dan controlled ablation marker pada paired dev, label/source diseimbangkan | Ablation dapat menghapus fakta; review semantic preservation |
| H2 verdict full-text fallback memudahkan in-domain | Bandingkan reviewed claim-only vs full-text dengan event split dan budget sama | Tidak memilih marker/threshold berdasar challenge historis |
| H3 title+article vs claim confound panjang/gaya | Matched length/topic/date/source dan pasangan klaim dari artikel yang sama | Pemotongan tidak boleh membuang klaim/negasi |
| H4 duplicates/conflicts menaikkan skor internal | Audit pre-dedup label conflict dan disjoint event groups, bandingkan versi dataset terpisah | Tanpa menimpa corpus/release/split lama |
| H5 source/topic/date skew mengurangi generalisasi | Held-out-source/time dev dan bootstrap per claim group sesuai Q-004 | Asosiasi bukan sebab; holdout blind tetap tak disentuh |

## 6. Blocker / cara melanjutkan

Owner perlu menyediakan lokasi artefak sumber dan processed yang diotorisasi,
hash manifest release-data, serta reviewer klaim. Tidak perlu menaruh data besar
ke Git atau mengirim artikel/PII lewat chat. Setelah tersedia, jalankan audit
record-level di atas dan tambahkan evidence/ID quarantine yang benar-benar diamati.
Q-008 tetap BLOCKED sampai statistik dan sampling corpus aktual dapat diverifikasi.
Q-009 dokumentasi protokol dapat dikerjakan terpisah bila dependensinya terpenuhi;
tidak otomatis melanjutkan tugas lain dalam commit ini.
