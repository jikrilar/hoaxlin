# Processing Time Test

## Environment

- Tanggal pengujian: 24 September 2026, zona waktu aplikasi Asia/Jakarta.
- Branch/commit: `test/blackbox-playwright` / `fc702f7`.
- Aplikasi: Laravel 12.64.0, PHP 8.4.11, `APP_ENV=local`, Docker Compose; host `http://localhost:8000`.
- Database: MySQL 8.0.44, migrasi current; Redis untuk queue/cache/session.
- Queue dan scheduler: container `queue` dan `scheduler` running/healthy. Container `app`, `mysql`, `redis`, dan `bert` juga running/healthy; `hoaxlin:doctor` PASS.
- FastAPI/IndoBERT: runtime `v1.0.0` ready. OpenAI API key terkonfigurasi; run teks #83 dan URL #88 mencatat event penjelasan dari `openai` dengan `explanation_cached=false` dan status `ready`. OCR/transkripsi pada seri ini tidak membuktikan panggilan OpenAI baru karena cache hit.
- Perangkat host: Intel Core i5-6200U 2,30 GHz, 4 logical processor, RAM fisik sekitar 8 GB. Ini adalah spesifikasi host, bukan jaminan alokasi CPU/RAM container.
- Akun uji lokal terverifikasi terpisah, user ID 44. Data non-uji tidak diubah.
- Input tetap per jenis: teks Indonesia tentang 15.722 gempa susulan (sama persis untuk lima run); URL artikel ANTARA [BMKG: Ada 15.722 gempa susulan](https://www.antaranews.com/berita/5742128/bmkg-ada-15722-gempa-susulan-dalam-30-hari-pasca-gempa-ntt) (hasil `extracted_text` run #88 diperiksa dan sesuai isi artikel); gambar [fixture PNG](BLACKBOX-EVIDENCE/BB-09-source-paragraph.png) (37.707 byte; SHA-256 `c3345065a0903f3550903913559ae2a4e58a4b9a3315415054416d6e03d3abad`); video [fixture MP4](BLACKBOX-EVIDENCE/BB-11-spoken-video.mp4) (115.992 byte, sekitar 11,2 detik, ucapan Inggris; SHA-256 `38206716aa99aa823daf7e75e758f81dee93611847b81d7fae7e81dafba69545`). Input media sama persis pada lima run masing-masing.
- Definisi waktu total: `submission.processing_started_at` sampai event `done/succeeded.created_at` terakhir. `processing_completed_at` cocok dengan event tersebut pada 19/20 run; pada #100 event `done` tercatat satu detik setelahnya, sehingga event digunakan konsisten untuk semua run. Timestamp DB hanya berpresisi detik: angka 0 s berarti mulai dan selesai pada detik kalender yang sama, bukan waktu proses nol. `inference_ms` berasal dari `detection_results`; stage dari `submission_processing_events.duration_ms`. Tidak digunakan stopwatch.
- Keterbatasan cache: cache tidak dimatikan atau dibersihkan. Karena input diulang identik, hanya #83 (teks) dan #88 (URL) non-cached pada classifier dan explanation. Gambar/video sudah cache-hit sejak run pertama karena fixture dipakai pada Black Box Testing sebelumnya. **Rata-rata seri campuran di bawah bukan estimasi waktu OCR/transkripsi atau inferensi IndoBERT cold-run.** `inference_ms` pada cache-hit adalah nilai yang tersimpan dari inferensi terdahulu, bukan inferensi yang dieksekusi lagi pada run tersebut.

## Raw Results

| Run | Input Type | Submission ID | Mulai (Asia/Jakarta) | Selesai (Asia/Jakarta) | End-to-End (s) | Inference field (ms) | Cached classifier / explanation | Status |
|---|---|---:|---|---|---:|---:|---|---|
| T1 | Teks | 83 | 2026-09-24 07:25:11 | 2026-09-24 07:25:48 | 37 | 29154 | Tidak / Tidak | completed |
| T2 | Teks | 84 | 2026-09-24 07:26:48 | 2026-09-24 07:26:49 | 1 | 29154 | Ya / Ya | completed |
| T3 | Teks | 85 | 2026-09-24 07:26:58 | 2026-09-24 07:27:00 | 2 | 29154 | Ya / Ya | completed |
| T4 | Teks | 86 | 2026-09-24 07:27:11 | 2026-09-24 07:27:12 | 1 | 29154 | Ya / Ya | completed |
| T5 | Teks | 87 | 2026-09-24 07:27:21 | 2026-09-24 07:27:23 | 2 | 29154 | Ya / Ya | completed |
| U1 | URL | 88 | 2026-09-24 07:27:51 | 2026-09-24 07:28:06 | 15 | 8752 | Tidak / Tidak | completed |
| U2 | URL | 89 | 2026-09-24 07:28:43 | 2026-09-24 07:28:44 | 1 | 8752 | Ya / Ya | completed |
| U3 | URL | 90 | 2026-09-24 07:28:55 | 2026-09-24 07:28:56 | 1 | 8752 | Ya / Ya | completed |
| U4 | URL | 91 | 2026-09-24 07:29:06 | 2026-09-24 07:29:07 | 1 | 8752 | Ya / Ya | completed |
| U5 | URL | 92 | 2026-09-24 07:29:18 | 2026-09-24 07:29:19 | 1 | 8752 | Ya / Ya | completed |
| G1 | Gambar | 93 | 2026-09-24 07:30:05 | 2026-09-24 07:30:06 | 1 | 2079 | Ya / Ya | completed |
| G2 | Gambar | 94 | 2026-09-24 09:53:30 | 2026-09-24 09:53:32 | 2 | 2079 | Ya / Ya | completed |
| G3 | Gambar | 95 | 2026-09-24 09:54:06 | 2026-09-24 09:54:08 | 2 | 2079 | Ya / Ya | completed |
| G4 | Gambar | 96 | 2026-09-24 09:54:19 | 2026-09-24 09:54:23 | 4 | 2079 | Ya / Ya | completed |
| G5 | Gambar | 97 | 2026-09-24 09:54:47 | 2026-09-24 09:54:47 | 0 | 2079 | Ya / Ya | completed |
| V1 | Video | 98 | 2026-09-24 09:55:21 | 2026-09-24 09:55:22 | 1 | 1855 | Ya / Ya | completed |
| V2 | Video | 99 | 2026-09-24 09:56:00 | 2026-09-24 09:56:02 | 2 | 1855 | Ya / Ya | completed |
| V3 | Video | 100 | 2026-09-24 09:56:25 | 2026-09-24 09:56:27 | 2 | 1855 | Ya / Ya | completed |
| V4 | Video | 101 | 2026-09-24 09:56:50 | 2026-09-24 09:56:51 | 1 | 1855 | Ya / Ya | completed |
| V5 | Video | 102 | 2026-09-24 09:57:11 | 2026-09-24 09:57:11 | 0 | 1855 | Ya / Ya | completed |

Seluruh 20 run berstatus `completed`, `processing_stage=done`, dan memiliki `DetectionResult`. Tidak ada kegagalan pipeline pada 20 submission. Satu upaya pemilihan file gambar tidak tersimpan pada form browser dan berhenti sebelum submission dibuat; bukan run yang dihitung atau kegagalan pipeline.

## Stage Timing

Rata-rata dari event `succeeded` yang memiliki `duration_ms`, dalam milidetik. Kolom *classifying* ialah durasi stage job (termasuk lookup cache/overhead), bukan sinonim `inference_ms`. Stage *translating* juga mencakup deteksi bahasa; pada teks/URL/gambar Bahasa Indonesia tidak ada panggilan terjemahan. Video berbahasa Inggris, tetapi translation cache-hit.

| Input Type | Extraction (ms) | Translation/language (ms) | Classification stage (ms) | Explanation (ms) |
|---|---:|---:|---:|---|
| Teks | 230.2 | 254.6 | 6178.4 | n/a |
| URL | 410.2 | 264.0 | 1900.2 | n/a |
| Gambar | 410.8 | 355.8 | 167.8 | n/a |
| Video | 145.2 | 147.4 | 88.0 | n/a |

`explaining/succeeded.duration_ms` bernilai `null` pada seluruh run; durasi explanation tidak dapat dihitung dari field event. Event `started` dan `done` juga memiliki `duration_ms=null`. Daftar berikut memuat **setiap** nilai event, termasuk `null`, sesuai urutan rekaman DB (ms):

```text
83: extracting/started=null; extracting/succeeded=420; translating/started=null; translating/succeeded=205; classifying/started=null; classifying/succeeded=30037; explaining/started=null; explaining/succeeded=null; done/succeeded=null
84: extracting/started=null; extracting/succeeded=251; translating/started=null; translating/succeeded=122; classifying/started=null; classifying/succeeded=142; explaining/started=null; explaining/succeeded=null; done/succeeded=null
85: extracting/started=null; extracting/succeeded=81; translating/started=null; translating/succeeded=531; classifying/started=null; classifying/succeeded=267; explaining/started=null; explaining/succeeded=null; done/succeeded=null
86: extracting/started=null; extracting/succeeded=106; translating/started=null; translating/succeeded=160; classifying/started=null; classifying/succeeded=194; explaining/started=null; explaining/succeeded=null; done/succeeded=null
87: extracting/started=null; extracting/succeeded=293; translating/started=null; translating/succeeded=255; classifying/started=null; classifying/succeeded=252; explaining/started=null; explaining/succeeded=null; done/succeeded=null
88: extracting/started=null; extracting/succeeded=1323; translating/started=null; translating/succeeded=295; classifying/started=null; classifying/succeeded=8958; explaining/started=null; explaining/succeeded=null; done/succeeded=null
89: extracting/started=null; extracting/succeeded=258; translating/started=null; translating/succeeded=152; classifying/started=null; classifying/succeeded=175; explaining/started=null; explaining/succeeded=null; done/succeeded=null
90: extracting/started=null; extracting/succeeded=250; translating/started=null; translating/succeeded=166; classifying/started=null; classifying/succeeded=197; explaining/started=null; explaining/succeeded=null; done/succeeded=null
91: extracting/started=null; extracting/succeeded=103; translating/started=null; translating/succeeded=213; classifying/started=null; classifying/succeeded=49; explaining/started=null; explaining/succeeded=null; done/succeeded=null
92: extracting/started=null; extracting/succeeded=117; translating/started=null; translating/succeeded=494; classifying/started=null; classifying/succeeded=122; explaining/started=null; explaining/succeeded=null; done/succeeded=null
93: extracting/started=null; extracting/succeeded=355; translating/started=null; translating/succeeded=411; classifying/started=null; classifying/succeeded=85; explaining/started=null; explaining/succeeded=null; done/succeeded=null
94: extracting/started=null; extracting/succeeded=819; translating/started=null; translating/succeeded=153; classifying/started=null; classifying/succeeded=121; explaining/started=null; explaining/succeeded=null; done/succeeded=null
95: extracting/started=null; extracting/succeeded=325; translating/started=null; translating/succeeded=582; classifying/started=null; classifying/succeeded=196; explaining/started=null; explaining/succeeded=null; done/succeeded=null
96: extracting/started=null; extracting/succeeded=496; translating/started=null; translating/succeeded=560; classifying/started=null; classifying/succeeded=395; explaining/started=null; explaining/succeeded=null; done/succeeded=null
97: extracting/started=null; extracting/succeeded=59; translating/started=null; translating/succeeded=73; classifying/started=null; classifying/succeeded=42; explaining/started=null; explaining/succeeded=null; done/succeeded=null
98: extracting/started=null; extracting/succeeded=248; translating/started=null; translating/succeeded=91; classifying/started=null; classifying/succeeded=54; explaining/started=null; explaining/succeeded=null; done/succeeded=null
99: extracting/started=null; extracting/succeeded=173; translating/started=null; translating/succeeded=107; classifying/started=null; classifying/succeeded=114; explaining/started=null; explaining/succeeded=null; done/succeeded=null
100: extracting/started=null; extracting/succeeded=158; translating/started=null; translating/succeeded=321; classifying/started=null; classifying/succeeded=68; explaining/started=null; explaining/succeeded=null; done/succeeded=null
101: extracting/started=null; extracting/succeeded=94; translating/started=null; translating/succeeded=87; classifying/started=null; classifying/succeeded=149; explaining/started=null; explaining/succeeded=null; done/succeeded=null
102: extracting/started=null; extracting/succeeded=53; translating/started=null; translating/succeeded=131; classifying/started=null; classifying/succeeded=55; explaining/started=null; explaining/succeeded=null; done/succeeded=null
```

Cache stage extraction: teks 0/5 hit, URL 4/5 hit, gambar 5/5 hit, video 5/5 hit. Cache classifier dan explanation: teks 4/5 hit; URL 4/5; gambar 5/5; video 5/5. Cache translation video 5/5 hit. Tidak ada event failed/retried pada seri ini.

## Summary

Mean/min/max total dihitung dari 5 run completed per jenis tanpa membulatkan sebelum agregasi. Mean inference adalah mean nilai **field** `inference_ms`; pada cache-hit angka tersebut diulang dari hasil lama.

| Input Type | Runs | Mean Total (s) | Min (s) | Max (s) | Mean Inference (ms) | Run classifier+explanation non-cached |
|---|---:|---:|---:|---:|---:|---:|
| Teks | 5 | 8.60 | 1 | 37 | 29154 | 1 |
| URL | 5 | 3.80 | 1 | 15 | 8752 | 1 |
| Gambar | 5 | 1.80 | 0 | 4 | 2079 | 0 |
| Video | 5 | 1.20 | 0 | 2 | 1855 | 0 |

Cohort non-cached yang benar-benar tersedia hanya #83 (teks: total 37 s, `inference_ms=29.154`) dan #88 (URL: total 15 s, `inference_ms=8.752`). Tidak cukup run non-cached untuk menghitung mean lima pengulangan per jenis; gambar dan video tidak memiliki sampel non-cached pada seri ini. Mean gabungan tidak dipakai sebagai pengganti mean non-cached.

## Interpretation

- Pada **kondisi cache campuran yang diuji**, video memiliki mean total terendah (1,20 s), sedangkan teks tertinggi (8,60 s). Ini tidak membuktikan video secara intrinsik lebih singkat: kelima run video adalah cache-hit, sementara teks mencakup satu inferensi BERT dan penjelasan OpenAI baru.
- Pada dua cold run yang teramati, inferensi IndoBERT merupakan porsi besar dari total yang terukur: 29,154/37 s (sekitar 78,8%) untuk teks dan 8,752/15 s (sekitar 58,3%) untuk URL. Jadi data ini **tidak** mendukung klaim bahwa inferensi selalu kecil dibanding seluruh pipeline.
- Ekstraksi artikel, OCR, transkripsi, terjemahan, dan penjelasan dari layanan eksternal berpotensi menambah total waktu. Seri ini hanya mengukur ekstraksi artikel baru pada #88 dan penjelasan OpenAI baru pada #83/#88; durasi OCR/transkripsi/terjemahan OpenAI cold-run tidak terukur karena cache.
- Resolusi timestamp detik membuat run singkat terkuantisasi (termasuk dua nilai 0 s). Event `duration_ms` memberi rincian stage, tetapi tidak dapat menggantikan total end-to-end karena antrean dan overhead antar-stage tetap ada.
- Hasil ini adalah pengukuran fungsional pada satu host, satu fixture per jenis, dan komposisi cache di atas; bukan evaluasi akurasi model atau generalisasi performa produksi.

## Cleanup dan keterlacakan

Submission ID 83–102 serta akun test ID 44 **dipertahankan sementara** agar angka laporan dapat dicocokkan kembali dengan event dan `DetectionResult` database. Menghapusnya sekarang akan menghilangkan sumber timing primer sehingga tidak aman untuk audit Bab IV. File bukti media yang sudah ada tidak diubah. Tidak ada source code aplikasi, model, dataset, threshold, atau artifact ML yang diubah.
